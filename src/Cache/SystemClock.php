<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use Psr\Clock\ClockInterface;

/**
 * Wall-clock time for cache expiry. The caches take any PSR-20 clock, so
 * expiry is testable with a settable clock and shared with the application's.
 *
 * ```php
 * $cache = new MemoryCacheStrategy(new SystemClock());
 * ```
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
