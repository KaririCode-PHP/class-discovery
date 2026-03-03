<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\NamespaceResolver;

/**
 * PSR-4 namespace resolver using composer.json autoload mappings.
 *
 * Parses composer.json from the project root to build namespace prefix
 * to directory mappings. Supports both autoload and autoload-dev sections.
 *
 * @package KaririCode\ClassDiscovery\Scanner
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class ComposerNamespaceResolver implements NamespaceResolver
{
    /** @var array<string, string> Prefix => directory */
    private array $mappings = [];

    /**
     * @param string|null $composerJsonPath Path to composer.json (auto-detected if null)
     * @param bool        $includeDevAutoload Include autoload-dev mappings
     */
    public function __construct(
        ?string $composerJsonPath = null,
        bool $includeDevAutoload = false,
    ) {
        $composerJsonPath ??= $this->detectComposerJson();

        if ($composerJsonPath !== null && is_file($composerJsonPath)) {
            $this->loadFromComposerJson($composerJsonPath, $includeDevAutoload);
        }
    }

    #[\Override]
    public function resolve(string $filePath): ?string
    {
        $realPath = realpath($filePath);
        if ($realPath === false) {
            return null;
        }

        // Normalize path separators
        $normalizedPath = str_replace('\\', '/', $realPath);

        foreach ($this->mappings as $prefix => $directory) {
            $normalizedDir = str_replace('\\', '/', rtrim($directory, '/')) . '/';

            if (! str_starts_with($normalizedPath, $normalizedDir)) {
                continue;
            }

            $relativePath = substr($normalizedPath, \strlen($normalizedDir));

            // Remove .php extension
            $relativePath = preg_replace('/\.php$/i', '', $relativePath);
            if ($relativePath === null) {
                continue;
            }

            // Convert path separators to namespace separators
            $className = str_replace('/', '\\', $relativePath);

            return $prefix . '\\' . $className;
        }

        return null;
    }

    #[\Override]
    public function addMapping(string $prefix, string $directory): self
    {
        $resolved = realpath($directory);
        if ($resolved !== false) {
            $this->mappings[rtrim($prefix, '\\')] = $resolved;
        }

        return $this;
    }

    /**
     * Load PSR-4 mappings from composer.json.
     */
    private function loadFromComposerJson(string $path, bool $includeDevAutoload): void
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (! \is_array($data)) {
            return;
        }

        $baseDir = \dirname(realpath($path) ?: $path);

        $this->extractPsr4Mappings($data['autoload']['psr-4'] ?? [], $baseDir);

        if ($includeDevAutoload) {
            $this->extractPsr4Mappings($data['autoload-dev']['psr-4'] ?? [], $baseDir);
        }
    }

    /**
     * Extract and register PSR-4 prefix-to-directory mappings.
     *
     * @param array<string, string|array<string>> $psr4 Composer PSR-4 autoload config
     * @param string $baseDir Project root directory
     */
    private function extractPsr4Mappings(array $psr4, string $baseDir): void
    {
        foreach ($psr4 as $prefix => $paths) {
            $paths = \is_array($paths) ? $paths : [$paths];

            foreach ($paths as $dir) {
                $absoluteDir = $baseDir . '/' . rtrim($dir, '/');
                $resolved = realpath($absoluteDir);

                if ($resolved !== false) {
                    $this->mappings[rtrim($prefix, '\\')] = $resolved;
                }
            }
        }
    }

    /**
     * Auto-detect composer.json by walking up from CWD.
     */
    private function detectComposerJson(): ?string
    {
        $dir = getcwd();
        if ($dir === false) {
            return null;
        }

        while ($dir !== \dirname($dir)) {
            $candidate = $dir . '/composer.json';
            if (is_file($candidate)) {
                return $candidate;
            }
            $dir = \dirname($dir);
        }

        return null;
    }
}
