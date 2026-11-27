<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Conformance;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\Contract\Exception\ExceptionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Mechanical checks of the KaririCode Specification §29.2 conformance rules
 * (plus G4 and the enum policy E-003) over every type declared in src/.
 */
final class SpecConformanceTest extends TestCase
{
    use FixturePaths;

    /** kariricode/contract 2.11.0 exception families (Spec §8.4). */
    private const array CONTRACT_FAMILIES = [
        \KaririCode\Contract\Exception\Validation::class,
        \KaririCode\Contract\Exception\NotFound::class,
        \KaririCode\Contract\Exception\Configuration::class,
        \KaririCode\Contract\Exception\Protocol::class,
        \KaririCode\Contract\Exception\ContainerFailure::class,
        \KaririCode\Contract\Exception\Runtime::class,
    ];

    /** Backed enums need a documented external contract (E-003). */
    private const array JUSTIFIED_BACKED_ENUMS = [
        AttributeTarget::class => 'ADR-004: values mirror the native Attribute::TARGET_* constants',
        Visibility::class => 'E-001: the values are the JSON form of the metadata DPOs',
    ];

    /** @var list<\ReflectionClass<object>>|null */
    private static ?array $types = null;

    public function testEveryFileDeclaresStrictTypesAndExactlyOneType(): void
    {
        $files = self::sourceFiles();

        foreach ($files as $file) {
            self::assertStringContainsString('declare(strict_types=1);', (string) file_get_contents($file), $file);
        }

        self::assertCount(count($files), self::types(), 'one type per file (PSR-4)');
    }

    public function testNoInterfaceSuffixAndNoManagerHelperOrUtilNames(): void
    {
        foreach (self::types() as $type) {
            $name = $type->getShortName();
            self::assertDoesNotMatchRegularExpression('/Interface$/', $name, "{$type->name}: no Interface suffix");
            self::assertDoesNotMatchRegularExpression('/(Manager|Helper|Util|Utils)$/', $name, "{$type->name}: vague role name");
        }
    }

    public function testNoMagicMethodsBesidesTheConstructor(): void
    {
        foreach (self::types() as $type) {
            foreach ($type->getMethods() as $method) {
                if ($method->getDeclaringClass()->name === $type->name && str_starts_with($method->name, '__')) {
                    self::assertSame('__construct', $method->name, "{$type->name}::{$method->name}()");
                }
            }
        }
    }

    public function testNoSerializationShims(): void
    {
        foreach (self::types() as $type) {
            foreach (['toArray', 'fromArray', 'jsonSerialize'] as $method) {
                self::assertFalse($type->hasMethod($method), "{$type->name}::{$method}() (E-001)");
            }
        }
    }

    public function testNoStaticState(): void
    {
        foreach (self::types() as $type) {
            self::assertSame([], $type->getProperties(\ReflectionProperty::IS_STATIC), "{$type->name} holds static state");
        }
    }

    /**
     * §18 targets the fragile base class inside the component, so only the
     * package's own ancestors count; PHP's built-in hierarchy (\RuntimeException
     * → \Exception) is stable. Same shape as the sibling components: abstract
     * base on \RuntimeException plus final family exceptions.
     */
    public function testInheritanceDepthAtMostTwo(): void
    {
        foreach (self::types() as $type) {
            $depth = 0;
            for ($parent = $type->getParentClass(); $parent !== false && !$parent->isInternal(); $parent = $parent->getParentClass()) {
                ++$depth;
            }

            self::assertLessThanOrEqual(2, $depth, "{$type->name} inheritance depth");
        }
    }

    public function testConcreteClassesAreFinal(): void
    {
        foreach (self::types() as $type) {
            if (!$type->isInterface() && !$type->isTrait() && !$type->isEnum() && !$type->isAbstract()) {
                self::assertTrue($type->isFinal(), "{$type->name} must be final (composition over inheritance)");
            }
        }
    }

    public function testBackedEnumsAreJustified(): void
    {
        foreach (self::types() as $type) {
            if ($type->implementsInterface(\BackedEnum::class)) {
                self::assertArrayHasKey($type->name, self::JUSTIFIED_BACKED_ENUMS, "{$type->name}: backed enum without an external contract (E-003)");
            }
        }
    }

    public function testErrorCodesAreUniqueAndInsideTheDeclaredRange(): void
    {
        [$low, $high] = self::declaredErrorRange();
        $codes = [];

        foreach (self::concreteExceptions() as $exception) {
            foreach ($exception->getMethods(\ReflectionMethod::IS_STATIC) as $factory) {
                $instance = $factory->invokeArgs(null, array_map(self::sampleArgument(...), $factory->getParameters()));
                self::assertInstanceOf(DiscoveryException::class, $instance);
                $codes["{$exception->getShortName()}::{$factory->name}"] = $instance->getCode();
            }
        }

        self::assertNotEmpty($codes);
        self::assertCount(count($codes), array_unique($codes), 'error codes must be unique');
        foreach ($codes as $factory => $code) {
            self::assertGreaterThanOrEqual($low, $code, $factory);
            self::assertLessThanOrEqual($high, $code, $factory);
        }
    }

    public function testEveryExceptionImplementsExactlyOneContractFamily(): void
    {
        $base = new \ReflectionClass(DiscoveryException::class);
        self::assertTrue($base->isAbstract(), 'the base is abstract; families are concrete');
        self::assertTrue($base->implementsInterface(ExceptionInterface::class));

        foreach (self::concreteExceptions() as $exception) {
            $families = array_filter(
                self::CONTRACT_FAMILIES,
                static fn(string $family): bool => $exception->implementsInterface($family),
            );
            self::assertCount(1, $families, "{$exception->name} must implement exactly one contract family (§8.4)");
            self::assertTrue($exception->isFinal(), "{$exception->name} must be final");
        }
    }

    public function testCanonicalMetadataBlockIsDeclared(): void
    {
        $kariricode = self::kariricodeExtra();

        self::assertSame('class-discovery', $kariricode['component'] ?? null);
        self::assertSame('4.3.3', $kariricode['spec'] ?? null);
        self::assertIsArray($kariricode['arfa'] ?? null);
        self::assertSame('1.3', $kariricode['arfa']['version'] ?? null);
        self::assertContains($kariricode['arfa']['level'] ?? null, [1, 2], 'E-009 defines levels 1 and 2 only');
        self::assertIsArray($kariricode['asvs'] ?? null);
        self::assertIsInt($kariricode['asvs']['level'] ?? null, 'G4: asvs.level');
        $extra = self::composer()['extra'] ?? [];
        self::assertIsArray($extra);
        self::assertArrayNotHasKey('arfa', $extra, 'legacy extra.arfa must be migrated (§33)');
    }

    public function testEveryAdrReferenceResolvesToADocument(): void
    {
        $referenced = [];
        foreach ([...self::sourceFiles(), ...(glob(self::projectRoot() . '/docs/*/*.md') ?: []), self::projectRoot() . '/README.md'] as $file) {
            preg_match_all('/\bADR-(\d{3})\b/', (string) file_get_contents($file), $matches);
            $referenced = [...$referenced, ...$matches[1]];
        }

        foreach (array_unique($referenced) as $number) {
            self::assertNotEmpty(glob(self::projectRoot() . "/docs/adr/ADR-{$number}-*.md"), "ADR-{$number} is referenced but missing");
        }
    }

    /**
     * @return list<\ReflectionClass<object>>
     */
    private static function types(): array
    {
        if (self::$types === null) {
            $scanner = new FileScanner();
            self::$types = array_map(
                self::reflect(...),
                array_keys($scanner->scan([self::projectRoot() . '/src'])->getClasses()),
            );
        }

        return self::$types;
    }

    /**
     * @return \ReflectionClass<object>
     */
    private static function reflect(string $fqcn): \ReflectionClass
    {
        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn)) {
            self::fail("{$fqcn} was discovered but cannot be autoloaded");
        }

        return new \ReflectionClass($fqcn);
    }

    /**
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::projectRoot() . '/src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function declaredErrorRange(): array
    {
        $declared = self::kariricodeExtra()['error-code-range'] ?? '';
        self::assertIsString($declared);
        self::assertMatchesRegularExpression('/^\d+[–-]\d+$/u', $declared, 'extra.kariricode.error-code-range must be LOW–HIGH');
        $bounds = preg_split('/[–-]/u', $declared);
        self::assertIsArray($bounds);

        return [(int) $bounds[0], (int) $bounds[1]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function kariricodeExtra(): array
    {
        $extra = self::composer()['extra'] ?? null;
        self::assertIsArray($extra);
        $kariricode = $extra['kariricode'] ?? null;
        self::assertIsArray($kariricode, 'extra.kariricode is the canonical metadata block (Spec §4)');

        /** @var array<string, mixed> $kariricode */
        return $kariricode;
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $composer = json_decode((string) file_get_contents(self::projectRoot() . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        /** @var array<string, mixed> $composer */
        return $composer;
    }

    /**
     * @return list<\ReflectionClass<\Throwable>>
     */
    private static function concreteExceptions(): array
    {
        $exceptions = [];
        foreach (self::types() as $type) {
            if ($type->isSubclassOf(\Throwable::class) && !$type->isAbstract()) {
                $exceptions[] = $type;
            }
        }

        return $exceptions;
    }

    private static function sampleArgument(\ReflectionParameter $parameter): int|float|string
    {
        $type = $parameter->getType();

        return match ($type instanceof \ReflectionNamedType ? $type->getName() : 'string') {
            'int' => 1,
            'float' => 1.5,
            default => 'sample',
        };
    }
}
