<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DirectoryScanner as DirectoryScannerContract;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Exception\PathNotFoundException;
use KaririCode\ClassDiscovery\Exception\ScanLimitExceededException;
use KaririCode\ClassDiscovery\Exception\SymlinkEscapeException;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;

/**
 * Recursive directory scanner with depth, symlink, and pattern constraints.
 *
 * Collects PHP files respecting max depth, glob pattern, and symlink policy,
 * then delegates token-based parsing to a FileScanner built per scan, so filters
 * and cache are applied exactly once per call (SRP: traversal policy here,
 * parsing there).
 *
 * Symlinks are skipped by default. When followed, a link must resolve *inside*
 * the scan root — the containment check is separator-aware, so `/app/src-evil`
 * is not accepted as being inside `/app/src` (fail closed, CWE-22).
 *
 * ```php
 * $result = new DirectoryScanner()
 *     ->setMaxDepth(4)
 *     ->setFollowSymlinks(false)
 *     ->scan(['src/']);
 * ```
 */
final class DirectoryScanner implements DirectoryScannerContract
{
    private int $maxDepth = 10;
    private string $pattern = '*.php';
    private bool $followSymlinks = false;

    /** @var list<ClassFilter> */
    private array $filters = [];
    private ?CacheStrategy $cache = null;

    public function scan(array $paths): DiscoveryResultContract
    {
        $filePaths = [];
        foreach ($paths as $path) {
            $filePaths = [...$filePaths, ...$this->collect($path)];
        }

        if ($filePaths === []) {
            return new DiscoveryResult();
        }

        $engine = new FileScanner();
        foreach ($this->filters as $filter) {
            $engine->addFilter($filter);
        }

        if ($this->cache !== null) {
            $engine->setCacheStrategy($this->cache);
        }

        return $engine->scan($filePaths);
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

    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = max(1, $depth);

        return $this;
    }

    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function setFollowSymlinks(bool $follow): self
    {
        $this->followSymlinks = $follow;

        return $this;
    }

    /**
     * @return list<string>
     *
     * @throws PathNotFoundException
     */
    private function collect(string $path): array
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw PathNotFoundException::at($path);
        }

        if (is_dir($realPath)) {
            return $this->collectFromDirectory($realPath, $realPath, 0);
        }

        return is_file($realPath) && $this->matchesPattern($realPath) ? [$realPath] : [];
    }

    /**
     * @return list<string>
     *
     * @throws ScanLimitExceededException|SymlinkEscapeException
     */
    private function collectFromDirectory(string $directory, string $root, int $depth): array
    {
        if ($depth > $this->maxDepth) {
            throw ScanLimitExceededException::depth($depth, $this->maxDepth);
        }

        $entries = is_readable($directory) ? scandir($directory) : false;
        if ($entries === false) {
            return [];
        }

        $files = [];
        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $this->resolveEntry($directory . DIRECTORY_SEPARATOR . $entry, $root);

            if ($path === null) {
                continue;
            }

            if (is_dir($path)) {
                $files = [...$files, ...$this->collectFromDirectory($path, $root, $depth + 1)];
            } elseif (is_file($path) && $this->matchesPattern($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Applies the symlink policy to one directory entry.
     *
     * @return string|null The path to traverse, or null to skip the entry
     *
     * @throws SymlinkEscapeException When a followed link resolves outside the scan root
     */
    private function resolveEntry(string $path, string $root): ?string
    {
        if (!is_link($path)) {
            return $path;
        }

        if (!$this->followSymlinks) {
            return null;
        }

        $target = realpath($path);
        if ($target === false || !self::isWithin($target, $root)) {
            throw SymlinkEscapeException::outside($path, $target === false ? 'unresolvable' : $target, $root);
        }

        return $target;
    }

    private static function isWithin(string $path, string $root): bool
    {
        return $path === $root
            || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private function matchesPattern(string $filePath): bool
    {
        return fnmatch($this->pattern, basename($filePath));
    }
}
