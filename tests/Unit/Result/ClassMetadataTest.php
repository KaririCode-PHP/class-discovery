<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Result;

use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassMetadata::class)]
final class ClassMetadataTest extends TestCase
{
    use MetadataFactory;

    public function testConstructionWithDefaults(): void
    {
        $meta = new ClassMetadata(
            name: 'App\\Entity\\User',
            filePath: '/src/Entity/User.php',
            namespace: 'App\\Entity',
            shortName: 'User',
        );

        self::assertSame('App\\Entity\\User', $meta->name);
        self::assertSame('/src/Entity/User.php', $meta->filePath);
        self::assertSame('App\\Entity', $meta->namespace);
        self::assertSame('User', $meta->shortName);
        self::assertFalse($meta->isFinal);
        self::assertFalse($meta->isAbstract);
        self::assertFalse($meta->isReadonly);
        self::assertFalse($meta->isInterface);
        self::assertFalse($meta->isEnum);
        self::assertFalse($meta->isTrait);
        self::assertSame([], $meta->interfaces);
        self::assertNull($meta->parent);
        self::assertSame([], $meta->traits);
        self::assertSame([], $meta->attributes);
        self::assertSame([], $meta->methods);
        self::assertSame([], $meta->properties);
    }

    public function testFinalReadonlyClass(): void
    {
        $meta = self::createClassMetadata(
            isFinal: true,
            isReadonly: true,
            interfaces: ['Serializable'],
            parent: 'App\\Base\\BaseEntity',
        );

        self::assertTrue($meta->isFinal);
        self::assertTrue($meta->isReadonly);
        self::assertSame(['Serializable'], $meta->interfaces);
        self::assertSame('App\\Base\\BaseEntity', $meta->parent);
    }

    public function testHasAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Entity');
        $meta = self::createClassMetadata(attributes: ['App\\Entity' => $attr]);

        self::assertTrue($meta->hasAttribute('App\\Entity'));
        self::assertFalse($meta->hasAttribute('App\\Route'));
    }

    public function testGetAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Entity');
        $meta = self::createClassMetadata(attributes: ['App\\Entity' => $attr]);

        self::assertSame($attr, $meta->getAttribute('App\\Entity'));
        self::assertNull($meta->getAttribute('App\\Route'));
    }

    public function testImplementsInterface(): void
    {
        $meta = self::createClassMetadata(
            interfaces: ['App\\Contract\\Renderable', 'Stringable'],
        );

        self::assertTrue($meta->implementsInterface('Stringable'));
        self::assertTrue($meta->implementsInterface('App\\Contract\\Renderable'));
        self::assertFalse($meta->implementsInterface('Countable'));
    }

    public function testExtendsClass(): void
    {
        $meta = self::createClassMetadata(parent: 'App\\Base\\AbstractController');

        self::assertTrue($meta->extendsClass('App\\Base\\AbstractController'));
        self::assertFalse($meta->extendsClass('App\\Base\\AbstractService'));
    }

    public function testExtendsClassWithNullParent(): void
    {
        $meta = self::createClassMetadata(parent: null);

        self::assertFalse($meta->extendsClass('App\\Base\\Any'));
    }

    public function testImmutability(): void
    {
        $meta = self::createClassMetadata();
        $ref = new \ReflectionClass($meta);

        self::assertTrue($ref->isReadonly());
        self::assertTrue($ref->isFinal());
    }

    public function testJsonEncodable(): void
    {
        $meta = self::createClassMetadata(
            isFinal: true,
            interfaces: ['Countable'],
        );

        $json = json_encode($meta, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('App\\Controller\\HomeController', $decoded['name']);
        self::assertTrue($decoded['isFinal']);
        self::assertSame(['Countable'], $decoded['interfaces']);
    }
}
