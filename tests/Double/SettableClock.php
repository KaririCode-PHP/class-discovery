<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Double;

use Psr\Clock\ClockInterface;

/**
 * PSR-20 clock that only moves when a test moves it.
 */
final class SettableClock implements ClockInterface
{
    public function __construct(
        private int $timestamp = 1_700_000_000,
    ) {}

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->timestamp);
    }
}
