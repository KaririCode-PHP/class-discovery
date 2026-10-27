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
 * ```php
 * $resolver = new ComposerNamespaceResolver('/app/composer.json', includeDevAutoload: true);
 * $resolver->resolve('/app/src/Http/HomeController.php'); // 'App\Http\HomeController'
 * ```
 */
final class ComposerNamespaceResolver implements NamespaceResolver
{
    /**
     * PSR-4 mappings, deepest directory first: a file under src/Legacy/ belongs to
     * the prefix mapped there, not to the one mapped to src/. A prefix may map to
     * several directories (composer.json allows a list).
     *
     * @var list<array{prefix: string, directory: string}>
     */
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

    public function resolve(string $filePath): ?string
    {
        $realPath = realpath($filePath);
        if ($realPath === false || preg_match('/\.php$/i', $realPath) !== 1) {
            return null;
        }

        $path = str_replace('\\', '/', $realPath);

        foreach ($this->mappings as ['prefix' => $prefix, 'directory' => $directory]) {
            $base = str_replace('\\', '/', $directory) . '/';
            if (str_starts_with($path, $base)) {
                $className = str_replace('/', '\\', substr($path, strlen($base), -4));

                return $prefix === '' ? $className : $prefix . '\\' . $className;
            }
        }

        return null;
    }

    public function addMapping(string $prefix, string $directory): self
    {
        $resolved = realpath($directory);
        if ($resolved === false) {
            return $this;
        }

        $this->mappings[] = ['prefix' => rtrim($prefix, '\\'), 'directory' => rtrim($resolved, '/\\')];
        usort($this->mappings, self::deepestFirst(...));

        return $this;
    }

    /**
     * @param array{prefix: string, directory: string} $a
     * @param array{prefix: string, directory: string} $b
     */
    private static function deepestFirst(array $a, array $b): int
    {
        return strlen($b['directory']) <=> strlen($a['directory']);
    }

    /**
     * Load PSR-4 mappings from composer.json.
     */
    private function loadFromComposerJson(string $path, bool $includeDevAutoload): void
    {
        $content = @file_get_contents($path); // unreadable → no mappings, as in 1.1.0
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        $realPath = realpath($path);
        $baseDir = dirname($realPath === false ? $path : $realPath);

        $this->extractPsr4Mappings(self::psr4Section($data, 'autoload'), $baseDir);

        if ($includeDevAutoload) {
            $this->extractPsr4Mappings(self::psr4Section($data, 'autoload-dev'), $baseDir);
        }
    }

    /**
     * The `psr-4` map of an autoload section, keeping only well-formed entries
     * (prefix => directory or list of directories) of a possibly malformed file.
     *
     * @param array<mixed> $composer
     *
     * @return array<string, string|array<string>>
     */
    private static function psr4Section(array $composer, string $section): array
    {
        return self::psr4Map(is_array($composer[$section] ?? null) ? ($composer[$section]['psr-4'] ?? null) : null);
    }

    /**
     * @return array<string, string|array<string>>
     */
    private static function psr4Map(mixed $psr4): array
    {
        if (!is_array($psr4)) {
            return [];
        }

        $mappings = [];
        foreach (array_keys($psr4) as $prefix) {
            $directories = self::directories($psr4[$prefix]);
            if ($directories !== null) {
                $mappings[(string) $prefix] = $directories;
            }
        }

        return $mappings;
    }

    /**
     * @return string|list<string>|null
     */
    private static function directories(mixed $paths): string|array|null
    {
        return match (true) {
            is_string($paths) => $paths,
            is_array($paths) => array_values(array_filter($paths, 'is_string')),
            default => null,
        };
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
            foreach (is_array($paths) ? $paths : [$paths] as $directory) {
                $this->addMapping($prefix, $baseDir . '/' . $directory);
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

        while ($dir !== dirname($dir)) {
            $candidate = $dir . '/composer.json';
            if (is_file($candidate)) {
                return $candidate;
            }
            $dir = dirname($dir);
        }

        return null;
    }
}
