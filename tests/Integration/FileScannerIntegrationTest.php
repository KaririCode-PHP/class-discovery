<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Filter\{StructuralFilter};
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileScanner::class)]
final class FileScannerIntegrationTest extends TestCase
{
    private string $fixtureDir;
    private NamespaceResolver $resolver;

    protected function setUp(): void
    {
        $this->fixtureDir = realpath(__DIR__ . '/../Fixture');

        // Manual resolver that maps fixture directory to test namespace
        $this->resolver = new class ($this->fixtureDir) implements NamespaceResolver {
            public function __construct(private readonly string $baseDir)
            {
            }

            public function resolve(string $filePath): ?string
            {
                $realPath = realpath($filePath);
                if ($realPath === false || ! str_starts_with($realPath, $this->baseDir)) {
                    return null;
                }

                $relative = substr($realPath, \strlen($this->baseDir) + 1);
                $relative = str_replace('.php', '', $relative);
                $className = str_replace('/', '\\', $relative);

                return 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\' . $className;
            }

            public function addMapping(string $prefix, string $directory): self
            {
                return $this;
            }
        };
    }

    public function testScanFindsAllFixtureClasses(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        // Should find: SampleInterface, SampleTrait, SampleEnum, SampleAbstractClass,
        // SampleClass, SampleFinalReadonlyClass, ChildClass, AttributedClass,
        // SampleAttribute, MethodAttribute, PropertyAttribute, DeepClass
        // Should NOT find: helper_functions.php (no class)
        self::assertGreaterThanOrEqual(8, $result->count());
        self::assertGreaterThan(0.0, $result->getScanDuration());
    }

    public function testScanDetectsInterface(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleInterface.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleInterface';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertTrue($meta->isInterface);
    }

    public function testScanDetectsEnum(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleEnum.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleEnum';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertTrue($meta->isEnum);
    }

    public function testScanDetectsTrait(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleTrait.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleTrait';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertTrue($meta->isTrait);
    }

    public function testScanDetectsAbstractClass(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleAbstractClass.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleAbstractClass';

        self::assertTrue($result->hasClass($fqcn));
        self::assertTrue($result->getClass($fqcn)->isAbstract);
    }

    public function testScanDetectsFinalReadonly(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleFinalReadonlyClass.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleFinalReadonlyClass';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertTrue($meta->isFinal);
        self::assertTrue($meta->isReadonly);
    }

    public function testScanDetectsInheritanceAndInterfaces(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleClass.php']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\SampleClass';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertSame('SampleAbstractClass', $meta->parent);
        self::assertContains('SampleInterface', $meta->interfaces);
    }

    public function testScanSkipsFilesWithoutClasses(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/helper_functions.php']);

        self::assertCount(0, $result);
    }

    public function testScanWithFilter(): void
    {
        $scanner = new FileScanner($this->resolver);
        $scanner->addFilter(new StructuralFilter(isFinal: true));

        $result = $scanner->scan([$this->fixtureDir]);

        // Only final classes should pass
        foreach ($result as $meta) {
            self::assertTrue($meta->isFinal, "{$meta->name} should be final");
        }
    }

    public function testScanWithCache(): void
    {
        $cache = new MemoryCacheStrategy();
        $scanner = new FileScanner($this->resolver);
        $scanner->setCacheStrategy($cache);

        $result1 = $scanner->scan([$this->fixtureDir]);
        $result2 = $scanner->scan([$this->fixtureDir]);

        // Second scan should be cached (same count, ~0 duration)
        self::assertSame($result1->count(), $result2->count());
    }

    public function testScanThrowsForNonexistentPath(): void
    {
        $scanner = new FileScanner($this->resolver);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(1001);

        $scanner->scan(['/nonexistent/path']);
    }

    public function testScanNestedDirectory(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/Nested']);

        $fqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\Nested\\DeepClass';

        self::assertTrue($result->hasClass($fqcn));
        $meta = $result->getClass($fqcn);
        self::assertTrue($meta->isFinal);
        self::assertSame('DeepClass', $meta->shortName);
    }

    public function testScanSingleFile(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleEnum.php']);

        self::assertGreaterThanOrEqual(1, $result->count());
    }

    public function testScanDurationIsPositive(): void
    {
        $scanner = new FileScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertGreaterThan(0.0, $result->getScanDuration());
    }

    public function testClearCache(): void
    {
        $cache = new MemoryCacheStrategy();
        $scanner = new FileScanner($this->resolver);
        $scanner->setCacheStrategy($cache);

        $scanner->scan([$this->fixtureDir]);
        $scanner->clearCache();

        // After clear, scanning again should produce a fresh result
        $result = $scanner->scan([$this->fixtureDir]);
        self::assertGreaterThan(0, $result->count());
    }
}
