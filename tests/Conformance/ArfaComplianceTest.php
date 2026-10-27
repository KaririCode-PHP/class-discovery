<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Conformance;

use KaririCode\ClassDiscovery\Result\{
    AttributeMetadata,
    ClassMetadata,
    DiscoveryResult,
    MethodMetadata,
    PropertyMetadata,
};
use KaririCode\ClassDiscovery\Tests\Double\PureEnum;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Executable conformance checks for the ARFA 1.3 guarantees the component claims
 * for its Result layer (SPEC-001 §7): P1 immutable state, E-001 no serialization
 * shims, E-002 no property hooks on readonly DPOs.
 */
final class ArfaComplianceTest extends TestCase
{
    use MetadataFactory;

    /** @return \Generator<string, array{class-string}> */
    public static function resultClassProvider(): \Generator
    {
        yield 'ClassMetadata' => [ClassMetadata::class];
        yield 'MethodMetadata' => [MethodMetadata::class];
        yield 'PropertyMetadata' => [PropertyMetadata::class];
        yield 'AttributeMetadata' => [AttributeMetadata::class];
        yield 'DiscoveryResult' => [DiscoveryResult::class];
    }

    /**
     * P1 — every Result class is final and readonly.
     *
     * @param class-string $class
     */
    #[DataProvider('resultClassProvider')]
    public function testResultClassesAreFinalAndReadonly(string $class): void
    {
        $ref = new \ReflectionClass($class);

        self::assertTrue($ref->isFinal(), "{$class} must be final (ARFA P1)");
        self::assertTrue($ref->isReadonly(), "{$class} must be readonly (ARFA P1)");
    }

    /**
     * E-001 — no toArray()/jsonSerialize() shims; JsonSerializable is not implemented.
     *
     * @param class-string $class
     */
    #[DataProvider('resultClassProvider')]
    public function testResultClassesHaveNoSerializationShims(string $class): void
    {
        self::assertFalse(method_exists($class, 'toArray'), "{$class} must not declare toArray() (ARFA E-001)");
        self::assertFalse(method_exists($class, 'jsonSerialize'), "{$class} must not declare jsonSerialize() (ARFA E-001)");
        self::assertNotInstanceOf(\JsonSerializable::class, $this->instantiate($class));
    }

    /**
     * E-002 — no property hooks on any Result-class property.
     *
     * @param class-string $class
     */
    #[DataProvider('resultClassProvider')]
    public function testResultClassesHaveNoPropertyHooks(string $class): void
    {
        $ref = new \ReflectionClass($class);

        foreach ($ref->getProperties() as $prop) {
            self::assertFalse($prop->hasHooks(), "{$class}::\${$prop->getName()} must not use property hooks (ARFA E-002)");
        }
    }

    /**
     * E-001 (positive) — the four public DPOs are natively json-encodable through
     * their public readonly properties, with no serialization interface.
     */
    public function testDpoJsonEncodesFromPublicProperties(): void
    {
        $class = self::createClassMetadata(name: 'App\\X', isFinal: true, interfaces: ['Countable']);

        $decoded = json_decode(json_encode($class, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertSame('App\\X', $decoded['name']);
        self::assertTrue($decoded['isFinal']);
        self::assertSame(['Countable'], $decoded['interfaces']);
    }

    /** @param class-string $class */
    private function instantiate(string $class): object
    {
        return match ($class) {
            ClassMetadata::class => self::createClassMetadata(),
            MethodMetadata::class => self::createMethodMetadata(),
            PropertyMetadata::class => self::createPropertyMetadata(),
            AttributeMetadata::class => self::createAttributeMetadata(),
            DiscoveryResult::class => new DiscoveryResult(),
            default => throw new \LogicException("Unhandled class {$class}"),
        };
    }

    /**
     * ADR-008: the metadata DPOs deliberately do not declare SerializableData.
     *
     * @param class-string $class
     */
    #[DataProvider('resultClassProvider')]
    public function testMetadataDposDoNotDeclareSerializableData(string $class): void
    {
        self::assertNotContains('KaririCode\\Contract\\SerializableData', class_implements($class) ?: []);
    }

    /**
     * ADR-008 rationale: a pure enum argument — legal in any attribute — has no JSON form,
     * so the marker's precondition cannot hold for every result.
     */
    public function testPureEnumArgumentHasNoJsonForm(): void
    {
        $attribute = self::createAttributeMetadata(arguments: ['visibility' => PureEnum::Shown]);

        $this->expectException(\JsonException::class);
        json_encode($attribute, JSON_THROW_ON_ERROR);
    }
}
