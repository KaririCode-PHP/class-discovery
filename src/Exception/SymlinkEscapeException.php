<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A followed symbolic link resolves outside the scan root (CWE-22).
 * Raised instead of silently skipping, so a hostile link fails closed.
 *
 * ```php
 * throw SymlinkEscapeException::outside('/app/src/link', '/etc', '/app/src');
 * ```
 */
final class SymlinkEscapeException extends DiscoveryException implements Runtime
{
    public const int CODE = 8702;

    public static function outside(string $link, string $target, string $root): self
    {
        return new self("Symlink '{$link}' targets '{$target}' outside scan root '{$root}'", self::CODE);
    }
}
