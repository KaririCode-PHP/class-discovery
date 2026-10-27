<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;
use KaririCode\ClassDiscovery\Tests\Fixture\MethodAttribute;
use KaririCode\ClassDiscovery\Tests\Fixture\PropertyAttribute;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleAttribute;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

/**
 * Reflection mode loads the fixtures through Composer's autoloader
 * (autoload-dev maps KaririCode\ClassDiscovery\Tests\ to tests/).
 */
final class ReflectionScannerIntegrationTest extends TestCase
{
    use FixturePaths;
    use TemporaryDirectories;

    private ReflectionScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new ReflectionScanner();
    }

    public function testEveryFixtureIsReflectedIncludingTraits(): void
    {
        $result = $this->scanner->scan([self::fixturePath()]);

        self::assertCount(12, $result);
        self::assertFalse($result->hasErrors());
        self::assertTrue($this->reflected($result->getClasses(), 'SampleTrait')->isTrait);
    }

    public function testHierarchyIsResolvedToFullyQualifiedNames(): void
    {
        $meta = $this->reflected($this->scanner->scan([self::fixturePath('SampleClass.php')])->getClasses(), 'SampleClass');

        self::assertSame(self::fixtureClass('SampleAbstractClass'), $meta->parent);
        self::assertSame([self::fixtureClass('SampleInterface')], $meta->interfaces);
        self::assertSame([self::fixtureClass('SampleTrait')], $meta->traits);
    }

    public function testMembersAreReflectedWithTheirTypes(): void
    {
        $meta = $this->reflected($this->scanner->scan([self::fixturePath('SampleClass.php')])->getClasses(), 'SampleClass');

        self::assertSame(['name' => 'string', 'value' => 'int'], $meta->methods['__construct']->parameters);
        self::assertSame('string', $meta->methods['getName']->returnType);
        self::assertTrue($meta->properties['name']->isPromoted);
        self::assertTrue($meta->properties['name']->isReadonly);
        self::assertSame(Visibility::Private, $meta->properties['name']->visibility);
    }

    public function testAttributesCarryResolvedNamesArgumentsAndTargets(): void
    {
        $meta = $this->reflected($this->scanner->scan([self::fixturePath('AttributedClass.php')])->getClasses(), 'AttributedClass');

        $classAttribute = $meta->getAttribute(self::fixtureClass('SampleAttribute'));
        self::assertNotNull($classAttribute);
        self::assertSame(['name' => 'test'], $classAttribute->arguments);
        self::assertTrue($meta->methods['execute']->hasAttribute(self::fixtureClass('MethodAttribute')));
        self::assertSame('field', $meta->properties['field']->getAttribute(self::fixtureClass('PropertyAttribute'))?->targetName);
    }

    public function testAutoloadFailureIsReportedNotThrown(): void
    {
        // Loading a class whose parent does not exist raises \Error inside the autoloader.
        $dir = $this->temporaryDirectory();
        $file = $dir . '/Broken.php';
        file_put_contents($file, "<?php\nnamespace KcBroken;\nclass Broken extends \\KcNowhere\\MissingParent {}\n");
        $autoload = static function (string $class) use ($file): void {
            if ($class === 'KcBroken\\Broken') {
                require $file;
            }
        };
        spl_autoload_register($autoload);

        try {
            $result = $this->scanner->scan([$dir]);
        } finally {
            spl_autoload_unregister($autoload);
        }

        self::assertCount(0, $result);
        self::assertArrayHasKey('KcBroken\\Broken', $result->getErrors());
        self::assertStringContainsString('MissingParent', $result->getErrors()['KcBroken\\Broken']);
    }

    public function testClassesTheAutoloaderCannotFindAreSkipped(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir . '/Ghost.php', "<?php\nnamespace KcGhost;\nclass Ghost {}\n");

        $result = $this->scanner->scan([$dir]);

        self::assertCount(0, $result);
        self::assertFalse($result->hasErrors());
    }

    public function testFiltersApplyToReflectedMetadata(): void
    {
        $result = $this->scanner->addFilter(new StructuralFilter(isTrait: true))->scan([self::fixturePath()]);

        self::assertSame([self::fixtureClass('SampleTrait')], array_keys($result->getClasses()));
    }

    public function testCacheHitReturnsTheSameInstanceUntilCleared(): void
    {
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());
        $first = $this->scanner->scan([self::fixturePath()]);

        self::assertSame($first, $this->scanner->scan([self::fixturePath()]));

        $this->scanner->clearCache();
        self::assertNotSame($first, $this->scanner->scan([self::fixturePath()]));
    }

    public function testEveryMemberShapeIsMapped(): void
    {
        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Specimen\\MemberVariety';
        $meta = $this->scanner->scan([self::projectRoot() . '/tests/Specimen/MemberVariety.php'])->getClass($fqcn);
        self::assertNotNull($meta);

        self::assertTrue($meta->isAbstract);
        self::assertSame(Visibility::Protected, $meta->methods['template']->visibility);
        self::assertTrue($meta->methods['template']->isAbstract);
        self::assertTrue($meta->methods['describe']->isStatic);
        self::assertSame(Visibility::Private, $meta->methods['combine']->visibility);
        self::assertNull($meta->methods['combine']->returnType, 'union return types have no single name');
        self::assertSame(['id' => 'string|int', 'raw' => 'mixed', 'items' => 'Countable'], $meta->methods['combine']->parameters);

        self::assertTrue($meta->properties['instances']->isStatic);
        self::assertSame('int', $meta->properties['instances']->type);
        self::assertTrue($meta->properties['instances']->hasDefault);
        self::assertSame(Visibility::Protected, $meta->properties['label']->visibility);
        self::assertSame('string', $meta->properties['label']->type);
        self::assertNull($meta->properties['key']->type);
        self::assertNull($meta->properties['untyped']->type);
        self::assertFalse($meta->properties['untyped']->isPromoted);
    }

    public function testAttributesAreInstantiatedOnEveryTarget(): void
    {
        $meta = $this->reflected($this->scanner->scan([self::fixturePath('AttributedClass.php')])->getClasses(), 'AttributedClass');

        $class = $meta->getAttribute(self::fixtureClass('SampleAttribute'))?->instance;
        self::assertInstanceOf(SampleAttribute::class, $class);
        self::assertSame('test', $class->name, 'the instance carries its constructor arguments');
        self::assertInstanceOf(MethodAttribute::class, $meta->methods['execute']->getAttribute(self::fixtureClass('MethodAttribute'))?->instance);
        self::assertInstanceOf(PropertyAttribute::class, $meta->properties['field']->getAttribute(self::fixtureClass('PropertyAttribute'))?->instance);
    }

    public function testAttributeThatCannotBeInstantiatedKeepsItsArguments(): void
    {
        $result = $this->scanner->scan([self::projectRoot() . '/tests/Specimen/MisplacedAttribute.php']);
        $method = $result->getClass('KaririCode\\ClassDiscovery\\Tests\\Specimen\\MisplacedAttribute')?->methods['run'];
        self::assertNotNull($method);

        $attribute = $method->getAttribute(self::fixtureClass('SampleAttribute'));
        self::assertNotNull($attribute);
        self::assertNull($attribute->instance, 'a TARGET_CLASS attribute cannot be instantiated on a method');
        self::assertSame(['name' => 'misplaced'], $attribute->arguments);
        self::assertFalse($result->hasErrors(), 'a failed instantiation is not a scan error');
    }

    public function testScanDurationIsMeasured(): void
    {
        self::assertGreaterThan(0.0, $this->scanner->scan([self::fixturePath()])->getScanDuration());
    }

    /**
     * @param array<string, ClassMetadata> $classes
     */
    private function reflected(array $classes, string $shortName): ClassMetadata
    {
        $meta = $classes[self::fixtureClass($shortName)] ?? null;
        self::assertNotNull($meta, "{$shortName} was not reflected");

        return $meta;
    }
}
