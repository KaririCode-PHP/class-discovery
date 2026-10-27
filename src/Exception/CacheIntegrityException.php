<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A cache entry decoded to something other than a discovery result —
 * corruption or tampering. The entry is purged before this is thrown.
 *
 * ```php
 * throw CacheIntegrityException::tampered('scan-key', 'Invalid result type');
 * ```
 */
final class CacheIntegrityException extends DiscoveryException implements Runtime
{
    public const int CODE = 8720;

    public static function tampered(string $key, string $reason): self
    {
        return new self("Cache entry '{$key}' is corrupted: {$reason}", self::CODE);
    }
}
