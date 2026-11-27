<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Configuration;

/**
 * The file cache refuses a directory it cannot own privately (CWE-377):
 * a symlink, world-writable, owned by another user, or impossible to create.
 * A deployment problem, so it surfaces at construction — fail closed.
 *
 * ```php
 * throw InsecureCacheDirectoryException::because('/tmp/cache', 'it is world-writable');
 * ```
 */
final class InsecureCacheDirectoryException extends DiscoveryException implements Configuration
{
    public const int REFUSED = 8721;
    public const int UNCREATABLE = 8722;

    public static function because(string $directory, string $reason): self
    {
        return new self("Refusing insecure cache directory {$directory}: {$reason}", self::REFUSED);
    }

    public static function uncreatable(string $directory): self
    {
        return new self("Cannot create cache directory: {$directory}", self::UNCREATABLE);
    }
}
