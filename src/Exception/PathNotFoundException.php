<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\NotFound;

/**
 * A scan path that does not exist on disk.
 *
 * ```php
 * throw PathNotFoundException::at('/app/missing');
 * ```
 */
final class PathNotFoundException extends DiscoveryException implements NotFound
{
    public const int CODE = 8701;

    public static function at(string $path): self
    {
        return new self("Path not found: {$path}", self::CODE);
    }
}
