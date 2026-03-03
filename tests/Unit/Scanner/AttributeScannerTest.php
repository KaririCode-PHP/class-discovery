<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult as ConcreteDiscoveryResult;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeScanner::class)]
#[UsesClass(FileScanner::class)]
#[UsesClass(ConcreteDiscoveryResult::class)]
#[UsesClass(ClassMetadata::class)]
#[UsesClass(AttributeMetadata::class)]
final class AttributeScannerTest extends TestCase
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

    public function testScanReturnsAllClasses(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertInstanceOf(DiscoveryResult::class, $result);
        self::assertGreaterThan(0, $result->count());
    }

    public function testScanForAttributeFindsAttributedClass(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttribute('SampleAttribute', [$this->fixtureDir]);

        $found = false;
        foreach ($result as $fqcn => $meta) {
            if (str_contains($fqcn, 'AttributedClass')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, 'AttributedClass should be found');
    }

    public function testScanForNonExistentAttributeReturnsEmpty(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttribute('TotallyNonExistentXyz123', [$this->fixtureDir]);

        self::assertCount(0, $result);
    }

    public function testScanForAttributesOrLogic(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttributes(
            ['SampleAttribute', 'TotallyNonExistentXyz123'],
            [$this->fixtureDir],
        );

        // At least AttributedClass should be found (has SampleAttribute)
        self::assertGreaterThan(0, $result->count());
    }

    public function testEachScanForAttributeIsIndependent(): void
    {
        $scanner = new AttributeScanner($this->resolver);

        $result1 = $scanner->scanForAttribute('SampleAttribute', [$this->fixtureDir]);
        $result2 = $scanner->scanForAttribute('TotallyNonExistentXyz123', [$this->fixtureDir]);

        self::assertGreaterThan(0, $result1->count());
        self::assertCount(0, $result2);
    }

    public function testAddFilterReturnsSelf(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $filter = $this->createMock(ClassFilter::class);

        $return = $scanner->addFilter($filter);

        self::assertSame($scanner, $return);
    }

    public function testSetCacheStrategyReturnsSelf(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);

        $return = $scanner->setCacheStrategy($cache);

        self::assertSame($scanner, $return);
    }

    public function testClearCacheCallsUnderlyingCache(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $cache = $this->createMock(CacheStrategy::class);
        $cache->expects(self::once())->method('clear');

        $scanner->setCacheStrategy($cache);
        $scanner->clearCache();
    }
}
