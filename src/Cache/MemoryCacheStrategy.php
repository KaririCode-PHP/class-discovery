<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;

/**
 * In-memory cache for development and testing.
 *
 * No persistence — cache lives only during the request lifecycle.
 * Zero I/O overhead. Ideal for dev mode with hot-reload.
 *
 * @package KaririCode\ClassDiscovery\Cache
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class MemoryCacheStrategy implements CacheStrategy
{
    /** @var array<string, array{result: DiscoveryResult, expires: int}> */
    private array $entries = [];

    #[\Override]
    public function get(string $key): ?DiscoveryResult
    {
        if (! isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        if ($entry['expires'] > 0 && $entry['expires'] < time()) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry['result'];
    }

    #[\Override]
    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        $this->entries[$key] = [
            'result' => $result,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
    }

    #[\Override]
    public function delete(string $key): void
    {
        unset($this->entries[$key]);
    }

    #[\Override]
    public function clear(): void
    {
        $this->entries = [];
    }

    #[\Override]
    public function has(string $key): bool
    {
        if (! isset($this->entries[$key])) {
            return false;
        }

        $entry = $this->entries[$key];
        if ($entry['expires'] > 0 && $entry['expires'] < time()) {
            unset($this->entries[$key]);

            return false;
        }

        return true;
    }
}
