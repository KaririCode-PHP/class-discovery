<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * The discovered classes depend on each other in a cycle.
 *
 * ```php
 * throw CircularDependencyException::chain('App\A → App\B → App\A');
 * ```
 */
final class CircularDependencyException extends DiscoveryException implements Runtime
{
    public const int CODE = 8730;

    public static function chain(string $chain): self
    {
        return new self("Circular dependency detected: {$chain}", self::CODE);
    }
}
