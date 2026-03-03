<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

/**
 * Immutable metadata snapshot of a discovered class.
 *
 * Readonly DPO (ARFA 1.3 P1). Public properties are the API —
 * no getters, no toArray(), no jsonSerialize(). PHP's native
 * json_encode() handles readonly public properties directly.
 *
 * @package KaririCode\ClassDiscovery\Result
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final readonly class ClassMetadata
{
    /**
     * @param string                          $name           FQCN
     * @param string                          $filePath       Absolute file path
     * @param string                          $namespace      Namespace (without class name)
     * @param string                          $shortName      Class short name
     * @param bool                            $isFinal        Whether class is final
     * @param bool                            $isAbstract     Whether class is abstract
     * @param bool                            $isReadonly      Whether class is readonly
     * @param bool                            $isInterface    Whether it's an interface
     * @param bool                            $isEnum         Whether it's an enum
     * @param bool                            $isTrait        Whether it's a trait
     * @param array<string>                   $interfaces     Implemented interface FQCNs
     * @param string|null                     $parent         Parent class FQCN
     * @param array<string>                   $traits         Used trait FQCNs
     * @param array<string, AttributeMetadata> $attributes    Class-level attributes keyed by FQCN
     * @param array<string, MethodMetadata>   $methods        Methods keyed by name
     * @param array<string, PropertyMetadata> $properties     Properties keyed by name
     */
    public function __construct(
        public string $name,
        public string $filePath,
        public string $namespace,
        public string $shortName,
        public bool $isFinal = false,
        public bool $isAbstract = false,
        public bool $isReadonly = false,
        public bool $isInterface = false,
        public bool $isEnum = false,
        public bool $isTrait = false,
        public array $interfaces = [],
        public ?string $parent = null,
        public array $traits = [],
        public array $attributes = [],
        public array $methods = [],
        public array $properties = [],
    ) {
    }

    /**
     * Whether this class has a specific attribute.
     */
    public function hasAttribute(string $attributeClass): bool
    {
        return isset($this->attributes[$attributeClass]);
    }

    /**
     * Retrieve a specific attribute's metadata.
     */
    public function getAttribute(string $attributeClass): ?AttributeMetadata
    {
        return $this->attributes[$attributeClass] ?? null;
    }

    /**
     * Whether this class implements a specific interface.
     */
    public function implementsInterface(string $interface): bool
    {
        return \in_array($interface, $this->interfaces, true);
    }

    /**
     * Whether this class extends a specific parent.
     */
    public function extendsClass(string $parentClass): bool
    {
        return $this->parent === $parentClass;
    }
}
