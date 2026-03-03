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
 * @package KaririCode\ClassDiscovery\Cache
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final readonly class ChainCacheStrategy implements CacheStrategy
{
    /** @var array<CacheStrategy> */
    private array $tiers;

    public function __construct(CacheStrategy ...$tiers)
    {
        $this->tiers = $tiers;
    }

    #[\Override]
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

    #[\Override]
    public function set(string $key, DiscoveryResult $result, int $ttl = 3600): void
    {
        foreach ($this->tiers as $tier) {
            $tier->set($key, $result, $ttl);
        }
    }

    #[\Override]
    public function delete(string $key): void
    {
        foreach ($this->tiers as $tier) {
            $tier->delete($key);
        }
    }

    #[\Override]
    public function clear(): void
    {
        foreach ($this->tiers as $tier) {
            $tier->clear();
        }
    }

    #[\Override]
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
