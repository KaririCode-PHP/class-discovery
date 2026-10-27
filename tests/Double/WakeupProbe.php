<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Double;

/**
 * Stand-in for a deserialization gadget: records whether PHP ever woke it up.
 */
final class WakeupProbe
{
    public static bool $woken = false;

    public function __wakeup(): void
    {
        self::$woken = true;
    }
}
