<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Result;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Result\{PropertyMetadata};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class PropertyMetadataTest extends TestCase
{
    use MetadataFactory;

    public function testConstructionWithDefaults(): void
    {
        $prop = new PropertyMetadata(name: 'id');

        self::assertSame('id', $prop->name);
        self::assertSame(Visibility::Public, $prop->visibility);
        self::assertNull($prop->type);
        self::assertFalse($prop->isReadonly);
        self::assertFalse($prop->isStatic);
        self::assertFalse($prop->hasDefault);
        self::assertFalse($prop->isPromoted);
        self::assertSame([], $prop->attributes);
    }

    public function testConstructionWithAllParameters(): void
    {
        $attr = self::createAttributeMetadata(target: AttributeTarget::Property);
        $prop = new PropertyMetadata(
            name: 'status',
            visibility: Visibility::Protected,
            type: 'string',
            isReadonly: true,
            isStatic: false,
            hasDefault: true,
            isPromoted: true,
            attributes: ['App\\Column' => $attr],
        );

        self::assertSame('status', $prop->name);
        self::assertSame(Visibility::Protected, $prop->visibility);
        self::assertSame('string', $prop->type);
        self::assertTrue($prop->isReadonly);
        self::assertTrue($prop->hasDefault);
        self::assertTrue($prop->isPromoted);
    }

    public function testHasAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Column');
        $prop = new PropertyMetadata(
            name: 'field',
            attributes: ['App\\Column' => $attr],
        );

        self::assertTrue($prop->hasAttribute('App\\Column'));
        self::assertFalse($prop->hasAttribute('App\\Id'));
    }

    public function testGetAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Column');
        $prop = new PropertyMetadata(
            name: 'field',
            attributes: ['App\\Column' => $attr],
        );

        self::assertSame($attr, $prop->getAttribute('App\\Column'));
        self::assertNull($prop->getAttribute('App\\Id'));
    }

    public function testImmutability(): void
    {
        $prop = self::createPropertyMetadata();
        $ref = new \ReflectionClass($prop);

        self::assertTrue($ref->isReadonly());
        self::assertTrue($ref->isFinal());
    }
}
