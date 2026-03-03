<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Result\{AttributeMetadata, ClassMetadata, MethodMetadata, PropertyMetadata};

/**
 * Factory methods for creating test metadata instances.
 */
trait MetadataFactory
{
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

    private static function createAttributeMetadata(
        string $name = 'App\\Attribute\\Route',
        AttributeTarget $target = AttributeTarget::Class_,
        array $arguments = [],
        bool $isRepeated = false,
        ?string $targetName = null,
    ): AttributeMetadata {
        return new AttributeMetadata(
            name: $name,
            target: $target,
            arguments: $arguments,
            isRepeated: $isRepeated,
            targetName: $targetName,
        );
    }

    private static function createMethodMetadata(
        string $name = 'execute',
        string $visibility = 'public',
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

    private static function createPropertyMetadata(
        string $name = 'id',
        string $visibility = 'public',
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
