<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

/**
 * The class imports in effect for one declaration, and PHP's resolution rule
 * for class references: a fully qualified name stands; a first segment that
 * matches an import (case-insensitively) is replaced by it; anything else is
 * relative to the current namespace. Unqualified class names never fall back
 * to the global namespace — only functions and constants do.
 *
 * Token mode resolves attributes, parents, interfaces and traits through this
 * table, so it records the fully qualified names reflection reports (DEC-003).
 *
 * ```php
 * $imports = new ImportTable('App\\Http', ['route' => 'Framework\\Routing\\Route']);
 * $imports->resolve('Route');         // 'Framework\Routing\Route'
 * $imports->resolve('Controller');    // 'App\Http\Controller'
 * $imports->resolve('\\Attribute');   // 'Attribute'
 * ```
 *
 * @see https://www.php.net/manual/en/language.namespaces.rules.php
 *
 * @internal Built by FileScanner for the declaration it parses.
 */
final readonly class ImportTable
{
    /**
     * @param array<string, string> $imports lowercase alias => fully qualified name
     */
    public function __construct(
        public string $namespace = '',
        private array $imports = [],
    ) {}

    /**
     * Namespace and class imports in effect at token $end. A `namespace`
     * declaration starts a new scope; `use function`, `use const` and a
     * closure's `use (...)` import no classes.
     */
    public static function at(TokenStream $stream, int $end): self
    {
        $namespace = '';
        $imports = [];

        for ($i = 0; $i < $end; $i++) {
            $token = $stream->tokens[$i];
            if ($token->is(T_NAMESPACE)) {
                $namespace = self::declaredNamespace($stream, $i);
                $imports = [];
            } elseif ($token->is(T_USE) && self::importsClasses($stream, $i)) {
                [$statement, $i] = self::statement($stream, $i);
                $imports = [...$imports, ...self::aliasesIn($statement)];
            }
        }

        return new self($namespace, $imports);
    }

    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return substr($name, 1);
        }

        if (stripos($name, 'namespace\\') === 0) {
            return $this->qualify(substr($name, 10));
        }

        $separator = strpos($name, '\\');
        $first = $separator === false ? $name : substr($name, 0, $separator);
        $import = $this->imports[strtolower($first)] ?? null;

        if ($import === null) {
            return $this->qualify($name);
        }

        return $separator === false ? $import : $import . substr($name, $separator);
    }

    private function qualify(string $name): string
    {
        return $this->namespace === '' ? $name : $this->namespace . '\\' . $name;
    }

    private static function declaredNamespace(TokenStream $stream, int $keyword): string
    {
        $next = $stream->significantIndex($keyword, 1);

        return $next !== null && $stream->tokens[$next]->is([T_STRING, T_NAME_QUALIFIED]) ? $stream->tokens[$next]->text : '';
    }

    private static function importsClasses(TokenStream $stream, int $use): bool
    {
        $next = $stream->significantIndex($use, 1);

        return $next !== null && $stream->tokens[$next]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]);
    }

    /**
     * @return array{0: string, 1: int} the statement's significant text, and the index of its `;`
     */
    private static function statement(TokenStream $stream, int $use): array
    {
        $parts = [];
        for ($i = $use + 1; isset($stream->tokens[$i]) && !$stream->tokens[$i]->is(';'); $i++) {
            if (!$stream->tokens[$i]->isIgnorable()) {
                $parts[] = $stream->tokens[$i]->text;
            }
        }

        return [implode(' ', $parts), $i];
    }

    /**
     * Aliases of one `use` statement: `A\B`, `A\B as C`, lists, and groups
     * `A\{B, C as D}` — whose `function`/`const` entries import no class.
     *
     * @return array<string, string>
     */
    private static function aliasesIn(string $statement): array
    {
        $prefix = '';
        $list = $statement;
        if (preg_match('/^(?<prefix>\S+)\s*\\\\\s*\{(?<list>.*)\}$/s', $statement, $group) === 1) {
            $prefix = $group['prefix'] . '\\';
            $list = $group['list'];
        }

        $aliases = [];
        foreach (explode(',', $list) as $entry) {
            if (preg_match('/^\s*(?<name>[^\s,]+)(?:\s+as\s+(?<alias>\S+))?\s*$/i', $entry, $match) !== 1) {
                continue;
            }

            $name = ltrim($prefix . $match['name'], '\\');
            $alias = ($match['alias'] ?? '') !== '' ? $match['alias'] : self::lastSegment($name);
            $aliases[strtolower($alias)] = $name;
        }

        return $aliases;
    }

    private static function lastSegment(string $name): string
    {
        $separator = strrpos($name, '\\');

        return $separator === false ? $name : substr($name, $separator + 1);
    }
}
