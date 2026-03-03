<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\{
    CacheStrategy,
    ClassFilter,
    DiscoveryResult as DiscoveryResultContract,
    NamespaceResolver,
    Scanner as ScannerContract,
};
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\{
    AttributeMetadata,
    ClassMetadata,
    DiscoveryResult,
};

/**
 * Token-based PHP file scanner.
 *
 * Uses token_get_all() for parsing without class loading.
 * This is the core scanning engine composed by AttributeScanner,
 * DirectoryScanner, and ReflectionScanner (ADR-005: Composition Over Inheritance).
 *
 * ## Security
 *
 * - Never executes scanned code (token-only parsing)
 * - Max file size: 10MB (configurable)
 * - Path traversal prevention via realpath()
 * - Symlinks not followed by default (DirectoryScanner controls this)
 *
 * @package KaririCode\ClassDiscovery\Scanner
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class FileScanner implements ScannerContract
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const int MAX_FILES_PER_SCAN = 10_000;

    /** @var array<ClassFilter> */
    private array $filters = [];

    private ?CacheStrategy $cache = null;

    public function __construct(
        private readonly NamespaceResolver $namespaceResolver,
        private readonly int $maxFileSize = self::MAX_FILE_SIZE,
        private readonly int $maxFiles = self::MAX_FILES_PER_SCAN,
    ) {
    }

    #[\Override]
    public function scan(array $paths): DiscoveryResultContract
    {
        $cacheKey = $this->computeCacheKey($paths);

        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $startTime = hrtime(true);
        $classes = [];
        $errors = [];
        $fileCount = 0;

        foreach ($this->collectPhpFiles($paths) as $filePath) {
            if (++$fileCount > $this->maxFiles) {
                throw DiscoveryException::maxFilesExceeded($fileCount, $this->maxFiles);
            }

            try {
                $metadata = $this->parseFile($filePath);
                if ($metadata !== null && $this->passesFilters($metadata)) {
                    $classes[$metadata->name] = $metadata;
                }
            } catch (\Throwable $e) {
                $errors[$filePath] = $e->getMessage();
            }
        }

        $duration = (float) (hrtime(true) - $startTime) / 1e9;
        $result = new DiscoveryResult($classes, $errors, $duration);

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $result);
        }

        return $result;
    }

    #[\Override]
    public function addFilter(ClassFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    #[\Override]
    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    #[\Override]
    public function clearCache(): void
    {
        $this->cache?->clear();
    }

    /**
     * Parse a single PHP file into ClassMetadata.
     *
     * Uses token_get_all() — never loads or executes the class.
     */
    private function parseFile(string $filePath): ?ClassMetadata
    {
        $realPath = realpath($filePath);
        if ($realPath === false || ! is_file($realPath)) {
            return null;
        }

        $fileSize = filesize($realPath);
        if ($fileSize === false || $fileSize > $this->maxFileSize) {
            return null;
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            return null;
        }

        $tokens = token_get_all($content);
        $tokenCount = \count($tokens);

        $namespace = '';
        $className = null;
        $isFinal = false;
        $isAbstract = false;
        $isReadonly = false;
        $isInterface = false;
        $isEnum = false;
        $isTrait = false;
        $parent = null;
        $interfaces = [];
        $attributes = [];

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            // Single-char tokens ('{', ';', etc.) are strings, not arrays.
            // Stop scanning after the class opening brace — everything
            // beyond it (methods, properties) cannot produce class-level metadata.
            if (! \is_array($token)) {
                if ($token === '{' && $className !== null) {
                    break;
                }

                continue;
            }

            switch ($token[0]) {
                case T_NAMESPACE:
                    $namespace = $this->extractNamespace($tokens, $i, $tokenCount);

                    break;

                case T_ATTRIBUTE:
                    $attrMeta = $this->extractAttribute($tokens, $i, $tokenCount, AttributeTarget::Class_);
                    if ($attrMeta !== null) {
                        $attributes[$attrMeta->name] = $attrMeta;
                    }

                    break;

                case T_READONLY:
                    $isReadonly = true;

                    break;

                case T_FINAL:
                    $isFinal = true;

                    break;

                case T_ABSTRACT:
                    $isAbstract = true;

                    break;

                case T_CLASS:
                    // Skip anonymous classes
                    if ($this->isAnonymousClass($tokens, $i)) {
                        break;
                    }
                    $className = $this->extractIdentifier($tokens, $i, $tokenCount);

                    break;

                case T_INTERFACE:
                    $isInterface = true;
                    $className = $this->extractIdentifier($tokens, $i, $tokenCount);

                    break;

                case T_ENUM:
                    $isEnum = true;
                    $className = $this->extractIdentifier($tokens, $i, $tokenCount);

                    break;

                case T_TRAIT:
                    $isTrait = true;
                    $className = $this->extractIdentifier($tokens, $i, $tokenCount);

                    break;

                case T_EXTENDS:
                    if ($className !== null && ! $isInterface) {
                        $parent = $this->extractQualifiedName($tokens, $i, $tokenCount);
                    }

                    break;

                case T_IMPLEMENTS:
                    $interfaces = $this->extractInterfaceList($tokens, $i, $tokenCount);

                    break;
            }
        }

        if ($className === null) {
            return null;
        }

        $fqcn = $namespace !== '' ? "{$namespace}\\{$className}" : $className;

        return new ClassMetadata(
            name: $fqcn,
            filePath: $realPath,
            namespace: $namespace,
            shortName: $className,
            isFinal: $isFinal,
            isAbstract: $isAbstract,
            isReadonly: $isReadonly,
            isInterface: $isInterface,
            isEnum: $isEnum,
            isTrait: $isTrait,
            interfaces: $interfaces,
            parent: $parent,
            attributes: $attributes,
        );
    }

    /**
     * Extract namespace from token stream.
     */
    private function extractNamespace(array $tokens, int &$position, int $count): string
    {
        $namespace = '';
        $position++;

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token === ';' || $token === '{') {
                break;
            }

            if (\is_array($token)) {
                if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_STRING) {
                    $namespace .= $token[1];
                }
            }

            $position++;
        }

        return trim($namespace);
    }

    /**
     * Extract class/interface/enum/trait name.
     */
    private function extractIdentifier(array $tokens, int &$position, int $count): ?string
    {
        $position++;

        while ($position < $count) {
            $token = $tokens[$position];

            if (\is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (\is_array($token) && $token[0] !== T_WHITESPACE) {
                return null;
            }

            $position++;
        }

        return null;
    }

    /**
     * Extract a qualified name (e.g., parent class).
     */
    private function extractQualifiedName(array $tokens, int &$position, int $count): ?string
    {
        $name = '';
        $position++;

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token === '{' || $token === ',') {
                break;
            }

            // Stop AT T_IMPLEMENTS so the outer loop can process it.
            // Decrement so $position++ in the outer for-loop lands on T_IMPLEMENTS.
            if (\is_array($token) && $token[0] === T_IMPLEMENTS) {
                $position--;

                break;
            }

            if (\is_array($token)) {
                if (\in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $name .= $token[1];
                }
            }

            $position++;
        }

        $name = trim($name);

        return $name !== '' ? $name : null;
    }

    /**
     * Extract interface list from implements clause.
     *
     * @return array<string>
     */
    private function extractInterfaceList(array $tokens, int &$position, int $count): array
    {
        $interfaces = [];
        $current = '';
        $position++;

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token === '{') {
                if (trim($current) !== '') {
                    $interfaces[] = trim($current);
                }

                break;
            }

            if ($token === ',') {
                if (trim($current) !== '') {
                    $interfaces[] = trim($current);
                }
                $current = '';
                $position++;

                continue;
            }

            if (\is_array($token)) {
                if (\in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $current .= $token[1];
                }
            }

            $position++;
        }

        return $interfaces;
    }

    /**
     * Extract attribute metadata from #[...] syntax.
     */
    private function extractAttribute(
        array $tokens,
        int &$position,
        int $count,
        AttributeTarget $target,
    ): ?AttributeMetadata {
        $depth = 0;
        $name = '';
        $position++;

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token === '[' || (\is_array($token) && $token[0] === T_ATTRIBUTE)) {
                $depth++;
            }

            if ($token === ']') {
                $depth--;
                if ($depth <= 0) {
                    break;
                }
            }

            if (\is_array($token)) {
                if (\in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && $name === '') {
                    $name = $token[1];
                }
            }

            $position++;
        }

        if ($name === '') {
            return null;
        }

        return new AttributeMetadata(
            name: $name,
            target: $target,
        );
    }

    /**
     * Check if a T_CLASS token is an anonymous class.
     */
    private function isAnonymousClass(array $tokens, int $position): bool
    {
        // Look backwards for T_NEW
        for ($j = $position - 1; $j >= 0; $j--) {
            if (! \is_array($tokens[$j])) {
                return false;
            }

            if ($tokens[$j][0] === T_NEW) {
                return true;
            }

            if ($tokens[$j][0] !== T_WHITESPACE) {
                return false;
            }
        }

        return false;
    }

    /**
     * Collect all .php files from given paths.
     *
     * @param array<string> $paths
     *
     * @return \Generator<string>
     */
    private function collectPhpFiles(array $paths): \Generator
    {
        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath === false) {
                throw DiscoveryException::pathNotFound($path);
            }

            if (is_file($realPath) && str_ends_with($realPath, '.php')) {
                yield $realPath;

                continue;
            }

            if (! is_dir($realPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $realPath,
                    \RecursiveDirectoryIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file->getRealPath();
                }
            }
        }
    }

    /**
     * Check if a class metadata passes all active filters.
     */
    private function passesFilters(ClassMetadata $metadata): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter->accept($metadata)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute deterministic cache key from scan parameters.
     */
    private function computeCacheKey(array $paths): string
    {
        $parts = [
            static::class,
            implode('|', array_map('realpath', array_filter($paths, 'is_string'))),
            implode('|', array_map(static fn (ClassFilter $f): string => $f::class, $this->filters)),
        ];

        return hash('xxh128', implode("\0", $parts));
    }
}
