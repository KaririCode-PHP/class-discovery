<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

/**
 * Immutable metadata snapshot of a discovered method.
 *
 * @package KaririCode\ClassDiscovery\Result
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final readonly class MethodMetadata
{
    /**
     * @param string                           $name        Method name
     * @param string                           $visibility  'public'|'protected'|'private'
     * @param bool                             $isStatic    Whether method is static
     * @param bool                             $isAbstract  Whether method is abstract
     * @param string|null                      $returnType  Return type declaration
     * @param array<string, AttributeMetadata> $attributes  Method-level attributes keyed by FQCN
     * @param array<string, string>            $parameters  Parameter names to type hints
     */
    public function __construct(
        public string $name,
        public string $visibility = 'public',
        public bool $isStatic = false,
        public bool $isAbstract = false,
        public ?string $returnType = null,
        public array $attributes = [],
        public array $parameters = [],
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
