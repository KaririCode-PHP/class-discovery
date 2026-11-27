<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A scan crossed one of its resource bounds — directory depth or file count.
 * The bounds cap the work a hostile or runaway tree can cause.
 *
 * ```php
 * throw ScanLimitExceededException::files(10_001, 10_000);
 * ```
 */
final class ScanLimitExceededException extends DiscoveryException implements Runtime
{
    public const int DEPTH = 8710;
    public const int FILES = 8711;

    public static function depth(int $depth, int $limit): self
    {
        return new self("Directory depth {$depth} exceeds limit {$limit}", self::DEPTH);
    }

    public static function files(int $count, int $limit): self
    {
        return new self("File count {$count} exceeds limit {$limit}", self::FILES);
    }
}
