<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\{
    CacheStrategy,
    ClassFilter,
    DirectoryScanner as DirectoryScannerContract,
    DiscoveryResult as DiscoveryResultContract,
    NamespaceResolver,
};
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;

/**
 * Recursive directory scanner with depth, symlink, and pattern constraints.
 *
 * Collects PHP files respecting max depth, glob pattern, and symlink policy,
 * then delegates token-based parsing to FileScanner. This two-phase approach
 * keeps traversal policy separate from parsing logic (SRP).
 *
 * @package KaririCode\ClassDiscovery\Scanner
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class DirectoryScanner implements DirectoryScannerContract
{
    private int $maxDepth = 10;
    private string $pattern = '*.php';
    private bool $followSymlinks = false;

    /** @var array<ClassFilter> */
    private array $filters = [];

    private ?CacheStrategy $cache = null;
    private readonly FileScanner $fileScanner;

    public function __construct(
        /** @phpstan-ignore property.onlyWritten (stored for future use / interface requirement) */
        private readonly NamespaceResolver $namespaceResolver,
    ) {
        $this->fileScanner = new FileScanner($namespaceResolver);
    }

    #[\Override]
    public function scan(array $paths): DiscoveryResultContract
    {
        // Collect files respecting depth/pattern/symlink constraints
        $filePaths = [];
        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath === false) {
                throw DiscoveryException::pathNotFound($path);
            }

            if (is_file($realPath) && $this->matchesPattern($realPath)) {
                $filePaths[] = $realPath;

                continue;
            }

            if (is_dir($realPath)) {
                $filePaths = array_merge(
                    $filePaths,
                    $this->collectFromDirectory($realPath, $realPath, 0),
                );
            }
        }

        if ($filePaths === []) {
            return new DiscoveryResult();
        }

        // Apply filters and cache to inner scanner
        foreach ($this->filters as $filter) {
            $this->fileScanner->addFilter($filter);
        }

        if ($this->cache !== null) {
            $this->fileScanner->setCacheStrategy($this->cache);
        }

        // Pass individual file paths — FileScanner::collectPhpFiles handles
        // single files via its is_file() branch, bypassing its own traversal.
        return $this->fileScanner->scan($filePaths);
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
        $this->fileScanner->clearCache();
    }

    #[\Override]
    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = max(1, $depth);

        return $this;
    }

    #[\Override]
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    #[\Override]
    public function setFollowSymlinks(bool $follow): self
    {
        $this->followSymlinks = $follow;

        return $this;
    }

    /**
     * Recursively collect files from a directory with constraint enforcement.
     *
     * @param string $directory Current directory to scan
     * @param string $root      Original scan root (for symlink containment)
     * @param int    $depth     Current recursion depth
     *
     * @return array<string> Collected file paths
     *
     * @throws DiscoveryException On max depth exceeded or symlink escape
     */
    private function collectFromDirectory(string $directory, string $root, int $depth): array
    {
        if ($depth > $this->maxDepth) {
            throw DiscoveryException::maxDepthExceeded($depth, $this->maxDepth);
        }

        $files = [];
        $entries = @scandir($directory);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $directory . DIRECTORY_SEPARATOR . $entry;

            // Symlink handling
            if (is_link($fullPath)) {
                if (! $this->followSymlinks) {
                    continue;
                }

                $target = realpath($fullPath);
                if ($target === false || ! str_starts_with($target, $root)) {
                    throw DiscoveryException::symlinkEscape($fullPath, $target ?: 'unresolvable', $root);
                }

                $fullPath = $target;
            }

            // $fullPath is guaranteed to be non-empty-string at this point.
            // Psalm cannot infer this after the symlink branch, so we assert.
            /** @var non-empty-string $fullPath */
            if (is_dir($fullPath)) {
                $files = array_merge(
                    $files,
                    $this->collectFromDirectory($fullPath, $root, $depth + 1),
                );

                continue;
            }

            if (is_file($fullPath) && $this->matchesPattern($fullPath)) {
                $files[] = $fullPath;
            }
        }

        return $files;
    }

    /**
     * Check if a file path matches the configured glob pattern.
     */
    private function matchesPattern(string $filePath): bool
    {
        return fnmatch($this->pattern, basename($filePath));
    }
}
