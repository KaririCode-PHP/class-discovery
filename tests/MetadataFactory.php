<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\MethodMetadata;
use KaririCode\ClassDiscovery\Result\PropertyMetadata;

/**
 * Factory methods for test metadata with safe defaults: each test states only
 * the fields it is about.
 */
trait MetadataFactory
{
    /**
     * @param list<string>                     $interfaces
     * @param list<string>                     $traits
     * @param array<string, AttributeMetadata> $attributes
     * @param array<string, MethodMetadata>    $methods
     * @param array<string, PropertyMetadata>  $properties
     */
    private static function createClassMetadata(
        string $name = 'App\\Controller\\HomeController',
        string $filePath = '/src/Controller/HomeController.php',
        string $namespace = 'App\\Controller',
        string $shortName = 'HomeController',
        bool $isFinal = false,
        bool $isAbstract = false,
        bool $isReadonly = false,
        bool $isInterface = false,
        bool $isEnum = false,
        bool $isTrait = false,
        array $interfaces = [],
        ?string $parent = null,
        array $traits = [],
        array $attributes = [],
        array $methods = [],
        array $properties = [],
    ): ClassMetadata {
        return new ClassMetadata(
            name: $name,
            filePath: $filePath,
            namespace: $namespace,
            shortName: $shortName,
            isFinal: $isFinal,
            isAbstract: $isAbstract,
            isReadonly: $isReadonly,
            isInterface: $isInterface,
            isEnum: $isEnum,
            isTrait: $isTrait,
            interfaces: $interfaces,
            parent: $parent,
            traits: $traits,
            attributes: $attributes,
            methods: $methods,
            properties: $properties,
        );
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private static function createAttributeMetadata(
        string $name = 'App\\Attribute\\Route',
        AttributeTarget $target = AttributeTarget::Class_,
        array $arguments = [],
        bool $isRepeated = false,
        ?string $targetName = null,
        ?object $instance = null,
    ): AttributeMetadata {
        return new AttributeMetadata(
            name: $name,
            target: $target,
            arguments: $arguments,
            isRepeated: $isRepeated,
            targetName: $targetName,
            instance: $instance,
        );
    }

    /**
     * @param array<string, AttributeMetadata> $attributes
     * @param array<string, string>            $parameters Parameter name => declared type
     */
    private static function createMethodMetadata(
        string $name = 'execute',
        Visibility $visibility = Visibility::Public,
        bool $isStatic = false,
        bool $isAbstract = false,
        ?string $returnType = null,
        array $attributes = [],
        array $parameters = [],
    ): MethodMetadata {
        return new MethodMetadata(
            name: $name,
            visibility: $visibility,
            isStatic: $isStatic,
            isAbstract: $isAbstract,
            returnType: $returnType,
            attributes: $attributes,
            parameters: $parameters,
        );
    }

    /**
     * @param array<string, AttributeMetadata> $attributes
     */
    private static function createPropertyMetadata(
        string $name = 'id',
        Visibility $visibility = Visibility::Public,
        ?string $type = null,
        bool $isReadonly = false,
        bool $isStatic = false,
        bool $hasDefault = false,
        bool $isPromoted = false,
        array $attributes = [],
    ): PropertyMetadata {
        return new PropertyMetadata(
            name: $name,
            visibility: $visibility,
            type: $type,
            isReadonly: $isReadonly,
            isStatic: $isStatic,
            hasDefault: $hasDefault,
            isPromoted: $isPromoted,
            attributes: $attributes,
        );
    }
}
