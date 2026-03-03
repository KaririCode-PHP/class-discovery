<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Enum;

/**
 * Scanner strategy classification.
 *
 * Pure enum: no external representation required.
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
