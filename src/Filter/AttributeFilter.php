<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Filters classes by presence of one or more attributes (OR logic).
 *
 * Checks class-level, method-level, and property-level attributes.
 *
 * ```php
 * // accepted when the class, a method or a property carries either attribute
 * $scanner->addFilter(new AttributeFilter(Route::class, Command::class));
 * ```
 */
final readonly class AttributeFilter implements ClassFilter
{
    /** @var array<string> */
    private array $attributeClasses;

    public function __construct(string ...$attributeClasses)
    {
        $this->attributeClasses = $attributeClasses;
    }

    public function accept(ClassMetadata $metadata): bool
    {
        foreach ($this->attributeClasses as $attrClass) {
            if ($metadata->hasAttribute($attrClass)) {
                return true;
            }

            foreach ($metadata->methods as $method) {
                if ($method->hasAttribute($attrClass)) {
                    return true;
                }
            }

            foreach ($metadata->properties as $property) {
                if ($property->hasAttribute($attrClass)) {
                    return true;
                }
            }
        }

        return false;
    }
}
