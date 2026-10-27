<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Result;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class AttributeMetadataTest extends TestCase
{
    use MetadataFactory;

    public function testConstructionWithDefaults(): void
    {
        $attr = new AttributeMetadata(
            name: 'App\\Route',
            target: AttributeTarget::Class_,
        );

        self::assertSame('App\\Route', $attr->name);
        self::assertSame(AttributeTarget::Class_, $attr->target);
        self::assertSame([], $attr->arguments);
        self::assertFalse($attr->isRepeated);
        self::assertNull($attr->targetName);
    }

    public function testConstructionWithAllParameters(): void
    {
        $attr = new AttributeMetadata(
            name: 'App\\Route',
            target: AttributeTarget::Method,
            arguments: ['path' => '/home', 'method' => 'GET'],
            isRepeated: true,
            targetName: 'index',
        );

        self::assertSame('App\\Route', $attr->name);
        self::assertSame(AttributeTarget::Method, $attr->target);
        self::assertSame(['path' => '/home', 'method' => 'GET'], $attr->arguments);
        self::assertTrue($attr->isRepeated);
        self::assertSame('index', $attr->targetName);
    }

    public function testImmutability(): void
    {
        $attr = self::createAttributeMetadata();
        $ref = new \ReflectionClass($attr);

        self::assertTrue($ref->isReadonly());
        self::assertTrue($ref->isFinal());
    }

    public function testJsonEncodable(): void
    {
        $attr = new AttributeMetadata(
            name: 'App\\Route',
            target: AttributeTarget::Class_,
            arguments: ['path' => '/api'],
        );

        $json = json_encode($attr, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertSame('App\\Route', $decoded['name']);
        self::assertSame(['path' => '/api'], $decoded['arguments']);
    }
}
