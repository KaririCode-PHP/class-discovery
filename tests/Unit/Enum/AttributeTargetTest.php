<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Enum;

use Attribute;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttributeTargetTest extends TestCase
{
    #[DataProvider('nativeTargets')]
    public function testEachCaseMirrorsItsNativeConstant(int $native, AttributeTarget $expected): void
    {
        self::assertSame($expected, AttributeTarget::from($native));
    }

    /**
     * @return \Generator<string, array{int, AttributeTarget}>
     */
    public static function nativeTargets(): \Generator
    {
        yield 'class' => [Attribute::TARGET_CLASS, AttributeTarget::Class_];
        yield 'method' => [Attribute::TARGET_METHOD, AttributeTarget::Method];
        yield 'property' => [Attribute::TARGET_PROPERTY, AttributeTarget::Property];
        yield 'parameter' => [Attribute::TARGET_PARAMETER, AttributeTarget::Parameter];
        yield 'class constant' => [Attribute::TARGET_CLASS_CONSTANT, AttributeTarget::ClassConstant];
        yield 'all' => [Attribute::TARGET_ALL, AttributeTarget::All];
    }

    public function testEveryCaseIsCoveredByTheMapping(): void
    {
        self::assertCount(count(AttributeTarget::cases()), iterator_to_array(self::nativeTargets()));
    }

    #[DataProvider('unknownTargets')]
    public function testTryFromRejectsValuesWithoutACase(int $value): void
    {
        self::assertNull(AttributeTarget::tryFrom($value));
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function unknownTargets(): \Generator
    {
        yield 'zero' => [0];
        yield 'function target (not class-scoped)' => [Attribute::TARGET_FUNCTION];
        yield 'out of range' => [99999];
    }
}
