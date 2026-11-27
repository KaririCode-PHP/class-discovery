<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

use KaririCode\ClassDiscovery\Enum\Visibility;

/**
 * Immutable metadata snapshot of a discovered method.
 *
 * ```php
 * foreach ($metadata->methods as $method) {
 *     if ($method->hasAttribute(Traced::class)) {
 *         // instrument $method->name
 *     }
 * }
 * ```
 */
final readonly class MethodMetadata
{
    /**
     * @param string                           $name        Method name
     * @param Visibility                           $visibility
     * @param bool                             $isStatic    Whether method is static
     * @param bool                             $isAbstract  Whether method is abstract
     * @param string|null                      $returnType  Return type declaration
     * @param array<string, AttributeMetadata> $attributes  Method-level attributes keyed by FQCN
     * @param array<string, string>            $parameters  Parameter names to type hints
     */
    public function __construct(
        public string $name,
        public Visibility $visibility = Visibility::Public,
        public bool $isStatic = false,
        public bool $isAbstract = false,
        public ?string $returnType = null,
        public array $attributes = [],
        public array $parameters = [],
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
