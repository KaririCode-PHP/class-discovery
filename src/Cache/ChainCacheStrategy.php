<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;

/**
 * Multi-tier cache: checks each tier in order, promotes on miss.
 *
 * Typical configuration: Memory (L1) → File (L2).
 * On L1 miss + L2 hit, result is promoted to L1.
 *
 * ```php
 * $scanner->setCacheStrategy(new ChainCacheStrategy(
 *     new MemoryCacheStrategy(),
 *     new FileCacheStrategy('/var/cache/discovery'), // a file hit is promoted to memory
 * ));
 * ```
 */
final readonly class ChainCacheStrategy implements CacheStrategy
{
    /** @var array<CacheStrategy> */
    private array $tiers;

    public function __construct(CacheStrategy ...$tiers)
    {
        $this->tiers = $tiers;
    }

    public function get(string $key): ?DiscoveryResult
    {
        foreach ($this->tiers as $index => $tier) {
            $result = $tier->get($key);
            if ($result !== null) {
                // Promote to earlier tiers
                for ($i = 0; $i < $index; $i++) {
                    $this->tiers[$i]->set($key, $result);
                }
                return $result;
            }
        }

        return null;
    }

    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        foreach ($this->tiers as $tier) {
            $tier->set($key, $result, $ttl);
        }
    }

    public function delete(string $key): void
    {
        foreach ($this->tiers as $tier) {
            $tier->delete($key);
        }
    }

    public function clear(): void
    {
        foreach ($this->tiers as $tier) {
            $tier->clear();
        }
    }

    public function has(string $key): bool
    {
        foreach ($this->tiers as $tier) {
            if ($tier->has($key)) {
                return true;
            }
        }

        return false;
    }
}
