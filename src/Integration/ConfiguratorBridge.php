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
 *
 * @package KaririCode\ClassDiscovery\Integration
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
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
        // @phpstan-ignore cast.int (mixed from array<string,mixed>)
        $maxFileSize = (int) ($config['max_file_size'] ?? 10 * 1024 * 1024);
        // @phpstan-ignore cast.int (mixed from array<string,mixed>)
        $maxFiles = (int) ($config['max_files'] ?? 10_000);

        $resolver = new ComposerNamespaceResolver(
            // @phpstan-ignore argument.type (mixed from array<string,mixed>, null-safe)
            composerJsonPath: $config['composer_json'] ?? null,
            includeDevAutoload: (bool) ($config['include_dev'] ?? false),
        );

        $scanner = new AttributeScanner($resolver, $maxFileSize, $maxFiles);

        // @phpstan-ignore offsetAccess.nonOffsetAccessible (mixed from array<string,mixed>)
        $cacheEnabled = (bool) ($config['cache']['enabled'] ?? true);

        if ($cacheEnabled) {
            // @phpstan-ignore argument.type (mixed from array<string,mixed>)
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
        // @phpstan-ignore cast.string (mixed from array<string,mixed>)
        $dir = (string) ($cacheConfig['dir'] ?? sys_get_temp_dir() . '/kariricode_discovery');

        return new ChainCacheStrategy(
            new MemoryCacheStrategy(),
            new FileCacheStrategy($dir),
        );
    }
}
