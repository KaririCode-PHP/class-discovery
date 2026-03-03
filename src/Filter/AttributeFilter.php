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
 * @package KaririCode\ClassDiscovery\Filter
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final readonly class AttributeFilter implements ClassFilter
{
    /** @var array<string> */
    private array $attributeClasses;

    public function __construct(string ...$attributeClasses)
    {
        $this->attributeClasses = $attributeClasses;
    }

    #[\Override]
    public function accept(ClassMetadata $metadata): bool
    {
        foreach ($this->attributeClasses as $attrClass) {
            // Token parser stores short names (e.g. 'SampleAttribute').
            // Callers may pass FQCNs. Accept both short name and full match,
            // but only compute shortName when $attrClass is actually a FQCN
            // (contains '\') to avoid false-positives with built-in names.
            $lastBackslash = strrpos($attrClass, '\\');
            $shortName = $lastBackslash !== false
                ? substr($attrClass, $lastBackslash + 1)
                : null;

            $matchClass = $metadata->hasAttribute($attrClass)
                || ($shortName !== null && $metadata->hasAttribute($shortName));

            if ($matchClass) {
                return true;
            }

            foreach ($metadata->methods as $method) {
                if ($method->hasAttribute($attrClass)
                    || ($shortName !== null && $method->hasAttribute($shortName))) {
                    return true;
                }
            }

            foreach ($metadata->properties as $property) {
                if ($property->hasAttribute($attrClass)
                    || ($shortName !== null && $property->hasAttribute($shortName))) {
                    return true;
                }
            }
        }

        return false;
    }
}
