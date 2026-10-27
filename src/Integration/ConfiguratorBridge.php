<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Integration;

use KaririCode\ClassDiscovery\Cache\ChainCacheStrategy;
use KaririCode\ClassDiscovery\Cache\FileCacheStrategy;
use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Contract\AttributeScanner as AttributeScannerContract;
use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Exception\InsecureCacheDirectoryException;
use KaririCode\ClassDiscovery\Exception\InvalidConfigurationException;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;

/**
 * Bridge to KaririCode\Configurator for discovery configuration.
 *
 * Builds the scanner and cache strategy from the `discovery` configuration
 * section. Values are validated, not coerced: a wrong type fails fast with
 * InvalidConfigurationException (8740) instead of silently becoming 0/false.
 *
 * Optional dependency: kariricode/configurator
 *
 * ## Configuration Keys
 *
 * | Key             | Type                  | Default                                  |
 * |-----------------|-----------------------|------------------------------------------|
 * | max_file_size   | int                   | 10485760                                 |
 * | max_files       | int                   | 10000                                    |
 * | cache.enabled   | bool                  | true                                     |
 * | cache.dir       | string                | <sys temp>/kariricode_discovery_<uid>    |
 *
 * Integers accept numeric strings and booleans accept "true"/"false"/"1"/"0",
 * so values read from the environment work unchanged.
 *
 * ```php
 * $scanner = ConfiguratorBridge::createScanner([
 *     'max_files' => 5000,
 *     'cache' => ['dir' => '/var/cache/discovery'],
 * ]); // InvalidConfigurationException (8740) on a wrong type
 * ```
 */
final readonly class ConfiguratorBridge
{
    /**
     * @param array<string, mixed> $config Configuration array
     *
     * @throws InvalidConfigurationException On an option of the wrong type
     */
    public static function createScanner(array $config = []): AttributeScannerContract
    {
        $cacheConfig = self::section($config, 'cache');

        $scanner = new AttributeScanner(
            maxFileSize: self::int($config, 'max_file_size', 10 * 1024 * 1024),
            maxFiles: self::int($config, 'max_files', 10_000),
        );

        if (self::bool($cacheConfig, 'enabled', true)) {
            $scanner->setCacheStrategy(self::createCacheStrategy($cacheConfig));
        }

        return $scanner;
    }


    /**
     * Memory (L1) → File (L2). The default directory is per user, so two
     * accounts on one host never share — or fight over — a cache directory.
     *
     * @param array<string, mixed> $cacheConfig Cache configuration
     *
     * @throws InvalidConfigurationException On an option of the wrong type
     * @throws InsecureCacheDirectoryException When the cache directory is not private
     */
    public static function createCacheStrategy(array $cacheConfig = []): CacheStrategy
    {
        $dir = self::optionalString($cacheConfig, 'dir') ?? self::defaultCacheDirectory();

        return new ChainCacheStrategy(
            new MemoryCacheStrategy(),
            new FileCacheStrategy($dir),
        );
    }

    private static function defaultCacheDirectory(): string
    {
        $user = function_exists('posix_geteuid') ? (string) posix_geteuid() : hash('xxh32', get_current_user());

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kariricode_discovery_' . $user;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function section(array $config, string $key): array
    {
        return self::arrayValue($key, $config[$key] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(string $key, mixed $value): array
    {
        if (!is_array($value)) {
            throw InvalidConfigurationException::option($key, 'array', get_debug_type($value));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function int(array $config, string $key, int $default): int
    {
        return self::intValue($key, $config[$key] ?? $default);
    }

    private static function intValue(string $key, mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || is_bool($value)) {
            throw InvalidConfigurationException::option($key, 'int', get_debug_type($value));
        }

        return $int;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function bool(array $config, string $key, bool $default): bool
    {
        return self::boolValue($key, $config[$key] ?? $default);
    }

    private static function boolValue(string $key, mixed $value): bool
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            throw InvalidConfigurationException::option($key, 'bool', get_debug_type($value));
        }

        return $bool;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function optionalString(array $config, string $key): ?string
    {
        return self::optionalStringValue($key, $config[$key] ?? null);
    }

    private static function optionalStringValue(string $key, mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw InvalidConfigurationException::option($key, 'string', get_debug_type($value));
        }

        return $value;
    }
}
