<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Configuration;

/**
 * A configuration option has the wrong type. Raised instead of coercing,
 * so `'abc'` never silently becomes `0`.
 *
 * ```php
 * throw InvalidConfigurationException::option('max_files', 'int', 'string');
 * ```
 */
final class InvalidConfigurationException extends DiscoveryException implements Configuration
{
    public const int CODE = 8740;

    public static function option(string $option, string $expected, string $actual): self
    {
        return new self("Invalid configuration option '{$option}': expected {$expected}, got {$actual}", self::CODE);
    }
}
