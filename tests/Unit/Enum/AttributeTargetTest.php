<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Enum;

use Attribute;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use PHPUnit\Framework\TestCase;

final class AttributeTargetTest extends TestCase
{
    public function testMapsToNativeConstants(): void
    {
        self::assertSame(Attribute::TARGET_CLASS, AttributeTarget::Class_->value);
        self::assertSame(Attribute::TARGET_METHOD, AttributeTarget::Method->value);
        self::assertSame(Attribute::TARGET_PROPERTY, AttributeTarget::Property->value);
        self::assertSame(Attribute::TARGET_PARAMETER, AttributeTarget::Parameter->value);
        self::assertSame(Attribute::TARGET_CLASS_CONSTANT, AttributeTarget::ClassConstant->value);
        self::assertSame(Attribute::TARGET_ALL, AttributeTarget::All->value);
    }

    public function testFromNativeValue(): void
    {
        $target = AttributeTarget::from(Attribute::TARGET_METHOD);

        self::assertSame(AttributeTarget::Method, $target);
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        self::assertNull(AttributeTarget::tryFrom(99999));
    }

    public function testIsBackedEnum(): void
    {
        $ref = new \ReflectionEnum(AttributeTarget::class);

        self::assertTrue($ref->isBacked());
        self::assertSame('int', $ref->getBackingType()->getName());
    }

    public function testCaseCount(): void
    {
        self::assertCount(6, AttributeTarget::cases());
    }
}
