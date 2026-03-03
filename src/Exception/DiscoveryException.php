<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

/**
 * Domain exception hierarchy for class discovery operations.
 *
 * Named constructors encode error semantics; error codes are unique
 * across the component to enable programmatic handling.
 *
 * | Code | Meaning                    |
 * |------|----------------------------|
 * | 1001 | Path not found             |
 * | 1002 | Path traversal detected    |
 * | 1003 | Symlink escape detected    |
 * | 1004 | Max depth exceeded         |
 * | 1005 | Max files exceeded         |
 * | 1006 | Scan timeout               |
 * | 1007 | Token parse failure        |
 * | 1008 | Namespace resolution error |
 * | 1009 | Cache corruption           |
 * | 1010 | Circular dependency        |
 *
 * @package KaririCode\ClassDiscovery\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class DiscoveryException extends \RuntimeException
{
    public static function pathNotFound(string $path): self
    {
        return new self(
            "Discovery path does not exist: {$path}",
            1001,
        );
    }

    public static function pathTraversalDetected(string $path, string $resolved): self
    {
        return new self(
            "Path traversal detected: '{$path}' resolves to '{$resolved}' outside allowed root",
            1002,
        );
    }

    public static function symlinkEscape(string $symlink, string $target, string $root): self
    {
        return new self(
            "Symlink '{$symlink}' targets '{$target}' outside scan root '{$root}'",
            1003,
        );
    }

    public static function maxDepthExceeded(int $depth, int $limit): self
    {
        return new self(
            "Directory depth {$depth} exceeds limit {$limit}",
            1004,
        );
    }

    public static function maxFilesExceeded(int $count, int $limit): self
    {
        return new self(
            "File count {$count} exceeds limit {$limit}",
            1005,
        );
    }

    public static function scanTimeout(float $elapsed, float $limit): self
    {
        return new self(
            \sprintf("Scan timed out after %.2fs (limit: %.2fs)", $elapsed, $limit),
            1006,
        );
    }

    public static function tokenParseFailure(string $file, string $reason): self
    {
        return new self(
            "Failed to parse tokens in '{$file}': {$reason}",
            1007,
        );
    }

    public static function namespaceResolutionError(string $file, string $reason): self
    {
        return new self(
            "Cannot resolve namespace for '{$file}': {$reason}",
            1008,
        );
    }

    public static function cacheCorruption(string $key, string $reason): self
    {
        return new self(
            "Cache entry '{$key}' is corrupted: {$reason}",
            1009,
        );
    }

    public static function circularDependency(string $chain): self
    {
        return new self(
            "Circular dependency detected: {$chain}",
            1010,
        );
    }
}
