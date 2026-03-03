<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleAttribute;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleClass;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReflectionScanner::class)]
final class ReflectionScannerTest extends TestCase
{
    private string $fixtureDir;
    private NamespaceResolver&MockObject $resolver;

    protected function setUp(): void
    {
        $this->fixtureDir = realpath(__DIR__ . '/../../Fixture');

        // Resolve FQCN from fixture relative path
        $base = $this->fixtureDir;
        $this->resolver = $this->createMock(NamespaceResolver::class);
        $this->resolver
            ->method('resolve')
            ->willReturnCallback(static function (string $path) use ($base): ?string {
                $realPath = realpath($path);
                if ($realPath === false || ! str_starts_with($realPath, $base)) {
                    return null;
                }
                $rel = substr($realPath, \strlen($base) + 1);

                return 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\'
                    . str_replace(['.php', '/'], ['', '\\'], $rel);
            });
    }

    public function testScanReturnsDiscoveryResult(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertInstanceOf(DiscoveryResult::class, $result);
        self::assertGreaterThan(0, $result->count());
    }

    public function testScanBuildsRichMetadataViaReflection(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        $fqcn = SampleClass::class;
        self::assertTrue($result->hasClass($fqcn), "Expected $fqcn to be found");

        $meta = $result->getClass($fqcn);
        self::assertNotNull($meta);
        self::assertSame('SampleClass', $meta->shortName);
        self::assertContains(SampleInterface::class, $meta->interfaces);
        self::assertNotEmpty($meta->methods);
        self::assertNotEmpty($meta->properties);
    }

    public function testScanDetectsAttributedClassViaReflection(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        $attributedFqcn = 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\AttributedClass';
        if (! $result->hasClass($attributedFqcn)) {
            self::markTestSkipped('AttributedClass not loaded by the autoloader in this context.');
        }

        $meta = $result->getClass($attributedFqcn);
        self::assertNotNull($meta);
        // Attributes resolved by reflection will have FQCN keys
        self::assertArrayHasKey(SampleAttribute::class, $meta->attributes);
    }

    public function testScanWithFilterExcludesNonMatchingClasses(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $filter = new StructuralFilter(isFinal: true);
        $scanner->addFilter($filter);

        $result = $scanner->scan([$this->fixtureDir]);

        foreach ($result as $fqcn => $meta) {
            self::assertTrue($meta->isFinal, "$fqcn should be final");
        }
    }

    public function testAddFilterReturnsSelf(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $filter = $this->createMock(ClassFilter::class);

        $return = $scanner->addFilter($filter);

        self::assertSame($scanner, $return);
    }

    public function testSetCacheStrategyReturnsSelf(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);

        $return = $scanner->setCacheStrategy($cache);

        self::assertSame($scanner, $return);
    }

    public function testClearCacheCallsUnderlyingCache(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);
        $cache->expects(self::once())->method('clear');

        $scanner->setCacheStrategy($cache);
        $scanner->clearCache();
    }

    public function testClearCacheWithoutCacheDoesNothing(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        // Should not throw
        $scanner->clearCache();
        self::assertTrue(true);
    }

    public function testScanUsesCache(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);
        $cached = $this->createMock(DiscoveryResult::class);

        $cache->method('has')->willReturn(true);
        $cache->method('get')->willReturn($cached);

        $scanner->setCacheStrategy($cache);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertSame($cached, $result);
    }

    public function testScanWritesToCache(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);

        $cache->method('has')->willReturn(false);
        $cache->expects(self::once())->method('set');

        $scanner->setCacheStrategy($cache);
        $scanner->scan([$this->fixtureDir]);
    }

    public function testScanReturnsDurationInResult(): void
    {
        $scanner = new ReflectionScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertGreaterThanOrEqual(0.0, $result->getScanDuration());
    }
}
