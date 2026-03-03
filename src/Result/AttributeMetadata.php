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
 * @package KaririCode\ClassDiscovery\Result
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final readonly class AttributeMetadata
{
    /**
     * @param string          $name         Attribute FQCN
     * @param AttributeTarget $target       Where the attribute was applied
     * @param array<mixed>    $arguments    Constructor arguments (positional + named)
     * @param bool            $isRepeated   Whether attribute appears multiple times
     * @param string|null     $targetName   Name of the annotated element (method/property name)
     */
    public function __construct(
        public string $name,
        public AttributeTarget $target,
        public array $arguments = [],
        public bool $isRepeated = false,
        public ?string $targetName = null,
    ) {
    }
}
