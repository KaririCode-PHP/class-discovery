<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Integration;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;

/**
 * Bridge to KaririCode\Cache PSR-16 simple cache.
 *
 * Adapts PSR-16 SimpleCache to CacheStrategy contract,
 * enabling transparent use of Redis, Memcached, or any
 * PSR-16 compliant cache with the discovery system.
 *
 * Optional dependency: kariricode/cache
 */
final class CacheBridge implements CacheStrategy
{
    /**
     * @param object $simpleCache PSR-16 CacheInterface instance
     *                            (typed as object to avoid hard dependency on psr/simple-cache)
     * @param string $prefix      Key prefix to avoid collisions
     */
    public function __construct(
        /** @psalm-suppress PropertyNotSetInConstructor */
        private readonly object $simpleCache,
        private readonly string $prefix = 'kc_discovery_',
    ) {
    }

    #[\Override]
    public function get(string $key): ?DiscoveryResult
    {
        /** @var DiscoveryResult|null $result */
        $result = $this->simpleCache->get($this->prefix . $key);

        return $result instanceof DiscoveryResult ? $result : null;
    }

    #[\Override]
    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        $this->simpleCache->set(
            $this->prefix . $key,
            $result,
            $ttl > 0 ? $ttl : null,
        );
    }

    #[\Override]
    public function delete(string $key): void
    {
        $this->simpleCache->delete($this->prefix . $key);
    }

    #[\Override]
    public function clear(): void
    {
        // PSR-16 clear() clears everything — not ideal.
        // Prefer iterating known keys if the cache supports it.
        $this->simpleCache->clear();
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->simpleCache->has($this->prefix . $key);
    }
}
