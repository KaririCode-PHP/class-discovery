<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Enum;

/**
 * Scanner strategy classification.
 *
 * Pure enum: no external representation required.
 *
 * @package KaririCode\ClassDiscovery\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
enum ScannerType
{
    case Reflection;
    case Attribute;
    case File;
    case Directory;
    case Composer;
    case Git;
    case HotReload;
}
