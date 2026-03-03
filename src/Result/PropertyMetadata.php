<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

/**
 * Immutable metadata snapshot of a discovered property.
 *
 * @package KaririCode\ClassDiscovery\Result
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final readonly class PropertyMetadata
{
    /**
     * @param string                           $name        Property name
     * @param string                           $visibility  'public'|'protected'|'private'
     * @param string|null                      $type        Type declaration
     * @param bool                             $isReadonly  Whether property is readonly
     * @param bool                             $isStatic    Whether property is static
     * @param bool                             $hasDefault  Whether property has a default value
     * @param bool                             $isPromoted  Whether declared via constructor promotion
     * @param array<string, AttributeMetadata> $attributes  Property-level attributes keyed by FQCN
     */
    public function __construct(
        public string $name,
        public string $visibility = 'public',
        public ?string $type = null,
        public bool $isReadonly = false,
        public bool $isStatic = false,
        public bool $hasDefault = false,
        public bool $isPromoted = false,
        public array $attributes = [],
    ) {
    }

    public function hasAttribute(string $attributeClass): bool
    {
        return isset($this->attributes[$attributeClass]);
    }

    public function getAttribute(string $attributeClass): ?AttributeMetadata
    {
        return $this->attributes[$attributeClass] ?? null;
    }
}
