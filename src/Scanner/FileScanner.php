<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Contract\Scanner as ScannerContract;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Exception\PathNotFoundException;
use KaririCode\ClassDiscovery\Exception\ScanLimitExceededException;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;

/**
 * Token-based PHP file scanner.
 *
 * Tokenizes with PhpToken::tokenize() — the object form of token_get_all() —
 * and never loads or executes the scanned code. This is the core engine composed
 * by AttributeScanner, DirectoryScanner and ReflectionScanner (ADR-005).
 *
 * Parsing is anchored on the declaration keyword: the first top-level
 * `class`/`interface`/`trait`/`enum` followed by a name. Names (parent, interfaces,
 * attributes) are resolved to FQCNs with PHP's own compile-time rules — imports,
 * aliases, groups, relative and fully qualified names — so token mode reports
 * the same identifiers as reflection mode. Everything the scanner
 * reports is read from the tokens *before* the class body — the preamble
 * (namespace, attributes, modifiers) and the header (`extends`/`implements`) —
 * so members can never leak into class-level metadata.
 *
 * ## Security
 *
 * - Never executes scanned code (token-only parsing)
 * - Max file size and max files per scan are bounded
 * - Paths are resolved with realpath(); symlink policy lives in DirectoryScanner
 *
 * ```php
 * $result = new FileScanner()->scan(['src/']);
 * $result->getClass(HomeController::class)?->parent; // a FQCN, read without loading the file
 * ```
 */
final class FileScanner implements ScannerContract
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const int MAX_FILES_PER_SCAN = 10_000;

    /** Keywords that open a class-like declaration. */
    private const array DECLARATION_TOKENS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /** Modifiers that may precede a class declaration. */
    private const array CLASS_MODIFIERS = [T_FINAL, T_ABSTRACT, T_READONLY];

    /** Tokens that end a statement: anything before them belongs to other code. */
    private const array STATEMENT_BOUNDARIES = [';', '{', '}'];

    /** @var list<ClassFilter> */
    private array $filters = [];
    private ?CacheStrategy $cache = null;

    /**
     * @param int $maxFileSize Maximum file size in bytes to parse (default 10MB)
     * @param int $maxFiles    Maximum number of files per scan (default 10,000)
     */
    public function __construct(
        private readonly int $maxFileSize = self::MAX_FILE_SIZE,
        private readonly int $maxFiles = self::MAX_FILES_PER_SCAN,
    ) {}

    public function scan(array $paths): DiscoveryResultContract
    {
        $cacheKey = ScanCacheKey::of(self::class, $paths, $this->filters)->value;
        $cached = $this->cache?->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $startTime = hrtime(true);
        $classes = [];
        $errors = [];
        $fileCount = 0;

        foreach ($this->collectPhpFiles($paths) as $filePath) {
            if (++$fileCount > $this->maxFiles) {
                throw ScanLimitExceededException::files($fileCount, $this->maxFiles);
            }

            try {
                $metadata = $this->parseFile($filePath);
                if ($metadata !== null && $this->passesFilters($metadata)) {
                    $classes[$metadata->name] = $metadata;
                }
            } catch (\Throwable $e) {
                // A user-supplied filter or an unreadable file must not abort the scan:
                // the failure is reported per file through DiscoveryResult::getErrors().
                $errors[$filePath] = $e->getMessage();
            }
        }

        $result = new DiscoveryResult($classes, $errors, (float) (hrtime(true) - $startTime) / 1e9);
        $this->cache?->set($cacheKey, $result);

        return $result;
    }

    public function addFilter(ClassFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    public function clearCache(): void
    {
        $this->cache?->clear();
    }

    private function parseFile(string $filePath): ?ClassMetadata
    {
        $source = $this->readSource($filePath);
        if ($source === null) {
            return null;
        }

        $stream = TokenStream::of($source);
        $declaration = $this->findDeclaration($stream);

        return $declaration === null ? null : $this->buildMetadata($stream, $declaration, $filePath);
    }

    private function readSource(string $filePath): ?string
    {
        if (!is_file($filePath)) {
            return null;
        }

        $size = filesize($filePath);
        if ($size === false || $size > $this->maxFileSize) {
            return null;
        }

        $source = file_get_contents($filePath);

        return $source === false ? null : $source;
    }

    /**
     * Index of the first class-like declaration keyword, or null when the file declares none.
     */
    private function findDeclaration(TokenStream $stream): ?int
    {
        foreach ($stream->tokens as $index => $token) {
            if ($token->is(self::DECLARATION_TOKENS) && $this->isDeclaration($stream, $index)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * A keyword declares a type only when a name follows it — which rejects
     * `new class {…}`, `new readonly class` and the `Foo::class` constant fetch
     * alike (after `::class` comes an operator or delimiter, never a name).
     */
    private function isDeclaration(TokenStream $stream, int $index): bool
    {
        $next = $stream->significantIndex($index, 1);

        return $next !== null && $stream->tokens[$next]->is(T_STRING);
    }

    private function buildMetadata(TokenStream $stream, int $declaration, string $filePath): ClassMetadata
    {
        $keyword = $stream->tokens[$declaration];
        $nameIndex = (int) $stream->significantIndex($declaration, 1);
        $shortName = $stream->tokens[$nameIndex]->text;
        $isInterface = $keyword->is(T_INTERFACE);

        $imports = ImportTable::at($stream, $declaration);
        $preamble = $this->preamble($stream, $declaration);
        $header = $this->header($stream, $nameIndex, $isInterface, $imports);
        $isReadonly = in_array(T_READONLY, $preamble['modifiers'], true);
        $members = $header['open'] === null
            ? ['methods' => [], 'properties' => [], 'traits' => []]
            : new ClassBodyParser($stream, $imports, readonlyClass: $isReadonly, interface: $isInterface)->parse($header['open']);

        return new ClassMetadata(
            name: $imports->namespace === '' ? $shortName : $imports->namespace . '\\' . $shortName,
            filePath: $filePath,
            namespace: $imports->namespace,
            shortName: $shortName,
            isFinal: in_array(T_FINAL, $preamble['modifiers'], true),
            isAbstract: in_array(T_ABSTRACT, $preamble['modifiers'], true),
            isReadonly: $isReadonly,
            isInterface: $isInterface,
            isEnum: $keyword->is(T_ENUM),
            isTrait: $keyword->is(T_TRAIT),
            interfaces: $header['interfaces'],
            parent: $header['parent'],
            traits: $members['traits'],
            attributes: ClassBodyParser::attributesFrom(
                array_map($imports->resolve(...), $preamble['attributes']),
                AttributeTarget::Class_,
            ),
            methods: $members['methods'],
            properties: $members['properties'],
        );
    }

    /**
     * Attributes and modifiers attached to the declaration. Anything separated
     * from it by a statement boundary belongs to other code and is discarded.
     *
     * @return array{attributes: list<string>, modifiers: list<int>}
     */
    private function preamble(TokenStream $stream, int $declaration): array
    {
        $attributes = [];
        $modifiers = [];

        for ($i = 0; $i < $declaration; $i++) {
            $token = $stream->tokens[$i];

            if ($token->is(T_ATTRIBUTE)) {
                [$names, $i] = $stream->attributeGroup($i);
                $attributes = [...$attributes, ...$names];
            } elseif ($token->is(self::CLASS_MODIFIERS)) {
                $modifiers[] = $token->id;
            } elseif ($token->is(self::STATEMENT_BOUNDARIES)) {
                $attributes = [];
                $modifiers = [];
            }
        }

        return ['attributes' => $attributes, 'modifiers' => $modifiers];
    }

    /**
     * The `extends` / `implements` clauses between the declared name and the body,
     * resolved to fully qualified names. An interface's `extends` lists interfaces
     * (Reflection parity); a class's names its parent.
     *
     * @return array{parent: ?string, interfaces: list<string>, open: ?int} `open` is the body's `{`
     */
    private function header(TokenStream $stream, int $nameIndex, bool $isInterface, ImportTable $imports): array
    {
        $parent = null;
        $interfaces = [];
        $clause = null;

        for ($i = $nameIndex + 1, $count = count($stream->tokens); $i < $count; $i++) {
            $token = $stream->tokens[$i];

            if ($token->is('{')) {
                return ['parent' => $parent, 'interfaces' => $interfaces, 'open' => $i];
            }

            if ($token->is([T_EXTENDS, T_IMPLEMENTS])) {
                $clause = $token->id;
            } elseif ($clause === T_EXTENDS && !$isInterface && $token->is(TokenStream::NAME_TOKENS)) {
                $parent = $imports->resolve($token->text);
            } elseif ($clause !== null && $token->is(TokenStream::NAME_TOKENS)) {
                $interfaces[] = $imports->resolve($token->text);
            }
        }

        return ['parent' => $parent, 'interfaces' => $interfaces, 'open' => null];
    }

    /**
     * @param array<string> $paths
     *
     * @return \Generator<int, string>
     */
    private function collectPhpFiles(array $paths): \Generator
    {
        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath === false) {
                throw PathNotFoundException::at($path);
            }

            if (is_file($realPath)) {
                if (str_ends_with($realPath, '.php')) {
                    yield $realPath;
                }

                continue;
            }

            yield from $this->phpFilesUnder($realPath);
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function phpFilesUnder(string $directory): \Generator
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath !== false) {
                yield $realPath;
            }
        }
    }

    private function passesFilters(ClassMetadata $metadata): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->accept($metadata)) {
                return false;
            }
        }

        return true;
    }
}
