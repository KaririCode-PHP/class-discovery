<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

/**
 * Cache abstraction for discovery results.
 *
 * Decouples scanning from caching strategy. Implementations range
 * from in-memory (dev) to file-based OPcache-friendly (production).
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
interface CacheStrategy
{
    /**
     * Retrieve cached discovery result.
     *
     * @param string $key Cache key (hash of scan configuration)
     *
     * @return DiscoveryResult|null Cached result or null on miss
     */
    public function get(string $key): ?DiscoveryResult;

    /**
     * Store discovery result in cache.
     *
     * @param string          $key    Cache key
     * @param DiscoveryResult $result Result to cache
     * @param int             $ttl    Time-to-live in seconds (0 = forever)
     */
    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void;

    /**
     * Remove a specific cached result.
     *
     * @param string $key Cache key to invalidate
     */
    public function delete(string $key): void;

    /**
     * Clear all cached discovery results.
     */
    public function clear(): void;

    /**
     * Check if a cached result exists and is valid.
     *
     * @param string $key Cache key
     *
     * @return bool True if valid cache entry exists
     */
    public function has(string $key): bool;
}
