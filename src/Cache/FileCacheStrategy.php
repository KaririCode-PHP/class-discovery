<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;

/**
 * File-based cache using PHP serialization.
 *
 * OPcache-friendly when using var_export format. Production-grade
 * with atomic writes (write to temp, rename) to prevent corruption.
 *
 * @package KaririCode\ClassDiscovery\Cache
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final readonly class FileCacheStrategy implements CacheStrategy
{
    public function __construct(
        private readonly string $cacheDirectory,
    ) {
        if (! is_dir($this->cacheDirectory) && ! @mkdir($this->cacheDirectory, 0o755, true)) {
            throw DiscoveryException::cacheCorruption('init', "Cannot create cache directory: {$this->cacheDirectory}");
        }
    }

    #[\Override]
    public function get(string $key): ?DiscoveryResult
    {
        $path = $this->keyToPath($key);

        if (! is_file($path)) {
            return null;
        }

        $data = @unserialize(file_get_contents($path) ?: '');

        if (! \is_array($data) || ! isset($data['result'], $data['expires'])) {
            @unlink($path);

            return null;
        }

        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($path);

            return null;
        }

        if (! $data['result'] instanceof DiscoveryResult) {
            @unlink($path);

            throw DiscoveryException::cacheCorruption($key, 'Invalid result type');
        }

        return $data['result'];
    }

    #[\Override]
    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        $path = $this->keyToPath($key);
        $tempPath = $path . '.tmp.' . (int) getmypid();

        $data = serialize([
            'result' => $result,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ]);

        // Atomic write: temp file → rename
        file_put_contents($tempPath, $data, LOCK_EX);
        rename($tempPath, $path);
    }

    #[\Override]
    public function delete(string $key): void
    {
        $path = $this->keyToPath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    #[\Override]
    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/discovery_*.cache');
        if ($files === false) {
            return;
        }

        array_map('unlink', $files);
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function keyToPath(string $key): string
    {
        return $this->cacheDirectory . '/discovery_' . $key . '.cache';
    }
}
