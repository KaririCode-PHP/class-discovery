<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;

/**
 * Immutable metadata snapshot of a discovered PHP attribute.
 *
 * Readonly DPO. Captures attribute class, target, arguments,
 * and repeatability without instantiating the attribute.
 *
 * ```php
 * $route = $metadata->getAttribute(Route::class);
 * $route?->arguments; // resolved in reflection mode; empty in token mode
 * ```
 */
final readonly class AttributeMetadata
{
    /**
     * @param string          $name         Attribute FQCN
     * @param AttributeTarget $target       Where the attribute was applied
     * @param array<mixed>    $arguments    Constructor arguments (positional + named)
     * @param bool            $isRepeated   Whether attribute appears multiple times
     * @param string|null     $targetName   Name of the annotated element (method/property name)
     * @param object|null     $instance     Attribute instantiated by ReflectionScanner; null in token mode
     *                                      or when it cannot be instantiated (wrong target, failing constructor)
     */
    public function __construct(
        public string $name,
        public AttributeTarget $target,
        public array $arguments = [],
        public bool $isRepeated = false,
        public ?string $targetName = null,
        public ?object $instance = null,
    ) {}
}
