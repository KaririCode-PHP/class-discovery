<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use Psr\Clock\ClockInterface;

/**
 * In-memory cache for development and testing.
 *
 * No persistence — cache lives only during the request lifecycle.
 * Zero I/O overhead. Ideal for dev mode with hot-reload.
 *
 * ```php
 * $scanner->setCacheStrategy(new MemoryCacheStrategy());
 * $scanner->scan(['src/']); // parses
 * $scanner->scan(['src/']); // same instance, from memory
 * ```
 */
final class MemoryCacheStrategy implements CacheStrategy
{
    /** @var array<string, array{result: DiscoveryResult, expires: int}> */
    private array $entries = [];

    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
    ) {}

    public function get(string $key): ?DiscoveryResult
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        if ($entry['expires'] > 0 && $entry['expires'] < $this->clock->now()->getTimestamp()) {
            unset($this->entries[$key]);
            return null;
        }

        return $entry['result'];
    }

    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        $this->entries[$key] = [
            'result' => $result,
            'expires' => $ttl > 0 ? $this->clock->now()->getTimestamp() + $ttl : 0,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
