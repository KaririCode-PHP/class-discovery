<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Result;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Result\{MethodMetadata};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class MethodMetadataTest extends TestCase
{
    use MetadataFactory;

    public function testConstructionWithDefaults(): void
    {
        $method = new MethodMetadata(name: 'execute');

        self::assertSame('execute', $method->name);
        self::assertSame('public', $method->visibility);
        self::assertFalse($method->isStatic);
        self::assertFalse($method->isAbstract);
        self::assertNull($method->returnType);
        self::assertSame([], $method->attributes);
        self::assertSame([], $method->parameters);
    }

    public function testConstructionWithAllParameters(): void
    {
        $attr = self::createAttributeMetadata(target: AttributeTarget::Method);
        $method = new MethodMetadata(
            name: '__construct',
            visibility: 'private',
            isStatic: false,
            isAbstract: false,
            returnType: 'void',
            attributes: ['App\\Route' => $attr],
            parameters: ['string', 'int'],
        );

        self::assertSame('__construct', $method->name);
        self::assertSame('private', $method->visibility);
        self::assertSame('void', $method->returnType);
        self::assertCount(1, $method->attributes);
        self::assertSame(['string', 'int'], $method->parameters);
    }

    public function testHasAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Route');
        $method = new MethodMetadata(
            name: 'index',
            attributes: ['App\\Route' => $attr],
        );

        self::assertTrue($method->hasAttribute('App\\Route'));
        self::assertFalse($method->hasAttribute('App\\Middleware'));
    }

    public function testGetAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Route');
        $method = new MethodMetadata(
            name: 'index',
            attributes: ['App\\Route' => $attr],
        );

        self::assertSame($attr, $method->getAttribute('App\\Route'));
        self::assertNull($method->getAttribute('App\\Middleware'));
    }

    public function testImmutability(): void
    {
        $method = self::createMethodMetadata();
        $ref = new \ReflectionClass($method);

        self::assertTrue($ref->isReadonly());
        self::assertTrue($ref->isFinal());
    }
}
