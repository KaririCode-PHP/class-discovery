<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

use KaririCode\ClassDiscovery\Enum\Visibility;

/**
 * Immutable metadata snapshot of a discovered property.
 *
 * ```php
 * $metadata->properties['id']->visibility === Visibility::Private;
 * ```
 */
final readonly class PropertyMetadata
{
    /**
     * @param string                           $name        Property name
     * @param Visibility                           $visibility
     * @param string|null                      $type        Type declaration
     * @param bool                             $isReadonly  Whether property is readonly
     * @param bool                             $isStatic    Whether property is static
     * @param bool                             $hasDefault  Whether property has a default value
     * @param bool                             $isPromoted  Whether declared via constructor promotion
     * @param array<string, AttributeMetadata> $attributes  Property-level attributes keyed by FQCN
     */
    public function __construct(
        public string $name,
        public Visibility $visibility = Visibility::Public,
        public ?string $type = null,
        public bool $isReadonly = false,
        public bool $isStatic = false,
        public bool $hasDefault = false,
        public bool $isPromoted = false,
        public array $attributes = [],
    ) {}

    public function hasAttribute(string $attributeClass): bool
    {
        return isset($this->attributes[$attributeClass]);
    }

    public function getAttribute(string $attributeClass): ?AttributeMetadata
    {
        return $this->attributes[$attributeClass] ?? null;
    }
}
