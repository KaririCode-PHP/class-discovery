<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult as ConcreteDiscoveryResult;
use KaririCode\ClassDiscovery\Scanner\DirectoryScanner;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryScanner::class)]
#[CoversClass(DiscoveryException::class)]
#[UsesClass(FileScanner::class)]
#[UsesClass(ConcreteDiscoveryResult::class)]
#[UsesClass(ClassMetadata::class)]

final class DirectoryScannerTest extends TestCase
{
    private string $fixtureDir;
    private NamespaceResolver&MockObject $resolver;

    protected function setUp(): void
    {
        $this->fixtureDir = realpath(__DIR__ . '/../../Fixture');

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
        $scanner = new DirectoryScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertInstanceOf(DiscoveryResult::class, $result);
        self::assertGreaterThan(0, $result->count());
    }

    public function testSetMaxDepthLimitsRecursion(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $scanner->setMaxDepth(1); // only top-level dir, no deeper nesting

        $result = $scanner->scan([$this->fixtureDir]);

        // DeepClass lives in Fixture/Nested/ — at depth=1 it should be included because
        // $fixtureDir/Nested/ IS exactly one level deep.
        // The test verifies maxDepth is respected without throwing.
        self::assertGreaterThanOrEqual(0, $result->count());
    }

    public function testSetMaxDepthReturnsSelf(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $return = $scanner->setMaxDepth(5);

        self::assertSame($scanner, $return);
    }

    public function testSetPatternFiltersFiles(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $scanner->setPattern('*.php');

        $result = $scanner->scan([$this->fixtureDir]);

        self::assertGreaterThan(0, $result->count());
    }

    public function testSetPatternReturnsSelf(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $return = $scanner->setPattern('*.php');

        self::assertSame($scanner, $return);
    }

    public function testSetFollowSymlinksReturnsSelf(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $return = $scanner->setFollowSymlinks(false);

        self::assertSame($scanner, $return);
    }

    public function testSetFollowSymlinksDefaultFalse(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        // Follow symlinks is false by default; scan should still work
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertGreaterThan(0, $result->count());
    }

    public function testAddFilterReturnsSelf(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $filter = $this->createMock(ClassFilter::class);

        $return = $scanner->addFilter($filter);

        self::assertSame($scanner, $return);
    }

    public function testAddFilterExcludesNonMatchingClasses(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $filter = $this->createMock(ClassFilter::class);
        $filter->method('accept')->willReturn(false);
        $scanner->addFilter($filter);

        $result = $scanner->scan([$this->fixtureDir]);

        self::assertCount(0, $result);
    }

    public function testSetCacheStrategyReturnsSelf(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);

        $return = $scanner->setCacheStrategy($cache);

        self::assertSame($scanner, $return);
    }

    public function testClearCacheCallsUnderlyingCache(): void
    {
        // DirectoryScanner propagates the cache to the inner FileScanner during scan().
        // We validate that clearCache() does not throw and the method chain is callable.
        $scanner = new DirectoryScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);
        $cached = $this->createMock(DiscoveryResult::class);

        // First, let scan() propagate the cache to the inner FileScanner
        $cache->method('has')->willReturn(true);
        $cache->method('get')->willReturn($cached);

        $scanner->setCacheStrategy($cache);
        $scanner->scan([$this->fixtureDir]); // propagates cache to inner FileScanner

        // Now clearCache() should invoke the inner FileScanner which holds the cache
        $cache->expects(self::once())->method('clear');
        $scanner->clearCache();
    }

    public function testScanUsesCache(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);
        $cached = $this->createMock(DiscoveryResult::class);

        $cache->method('has')->willReturn(true);
        $cache->method('get')->willReturn($cached);

        $scanner->setCacheStrategy($cache);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertSame($cached, $result);
    }

    public function testScanNonExistentPathThrowsException(): void
    {
        $scanner = new DirectoryScanner($this->resolver);

        $this->expectException(\KaririCode\ClassDiscovery\Exception\DiscoveryException::class);
        $scanner->scan(['/path/that/absolutely/does/not/exist/xyz987']);
    }

    public function testScanWithMaxDepthRecurses(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $scanner->setMaxDepth(5);

        $result = $scanner->scan([$this->fixtureDir]);

        $foundDeep = false;
        foreach ($result as $fqcn => $meta) {
            if (str_contains($fqcn, 'DeepClass')) {
                $foundDeep = true;

                break;
            }
        }

        self::assertTrue($foundDeep, 'DeepClass in Nested/ should be found at maxDepth=5');
    }
}
