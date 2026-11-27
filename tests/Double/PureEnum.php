<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Double;

/**
 * A pure enum: legal as an attribute argument, without a JSON form (ADR-008).
 */
enum PureEnum
{
    case Shown;
}
