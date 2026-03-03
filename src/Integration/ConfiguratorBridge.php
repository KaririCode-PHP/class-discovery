<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Integration;

use KaririCode\ClassDiscovery\Cache\{ChainCacheStrategy, FileCacheStrategy, MemoryCacheStrategy};
use KaririCode\ClassDiscovery\Contract\{CacheStrategy, Scanner};
use KaririCode\ClassDiscovery\Scanner\{AttributeScanner, ComposerNamespaceResolver};

/**
 * Bridge to KaririCode\Configurator for discovery configuration.
 *
 * Reads discovery settings from the configurator and builds
 * the appropriate scanner with cache strategy.
 *
 * Optional dependency: kariricode/configurator
 *
 * ## Configuration Keys
 *
 * | Key                    | Type   | Default     |
 * |------------------------|--------|-------------|
 * | discovery.cache.enabled | bool   | true        |
 * | discovery.cache.dir     | string | ./cache     |
 * | discovery.cache.ttl     | int    | 3600        |
 * | discovery.max_files     | int    | 10000       |
 * | discovery.max_file_size | int    | 10485760    |
 * | discovery.paths         | array  | ['src/']    |
 */
final readonly class ConfiguratorBridge
{
    /**
     * Build a configured scanner from array configuration.
     *
     * @param array<string, mixed> $config Configuration array
     */
    public static function createScanner(array $config = []): Scanner
    {
        $maxFileSize = (int) ($config['max_file_size'] ?? 10 * 1024 * 1024);
        $maxFiles = (int) ($config['max_files'] ?? 10_000);

        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $config['composer_json'] ?? null,
            includeDevAutoload: (bool) ($config['include_dev'] ?? false),
        );

        $scanner = new AttributeScanner($resolver, $maxFileSize, $maxFiles);

        $cacheEnabled = (bool) ($config['cache']['enabled'] ?? true);

        if ($cacheEnabled) {
            $scanner->setCacheStrategy(self::createCacheStrategy($config['cache'] ?? []));
        }

        return $scanner;
    }

    /**
     * Build cache strategy from configuration.
     *
     * @param array<string, mixed> $cacheConfig Cache configuration
     */
    public static function createCacheStrategy(array $cacheConfig = []): CacheStrategy
    {
        $dir = (string) ($cacheConfig['dir'] ?? sys_get_temp_dir() . '/kariricode_discovery');

        return new ChainCacheStrategy(
            new MemoryCacheStrategy(),
            new FileCacheStrategy($dir),
        );
    }
}
