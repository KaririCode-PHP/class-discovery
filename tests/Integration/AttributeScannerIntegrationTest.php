<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleAttribute;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use PHPUnit\Framework\TestCase;

/**
 * Token mode resolves `use` imports: the fixtures write `#[SampleAttribute]`,
 * which is recorded — and matched — as its FQCN, like in reflection mode.
 */
final class AttributeScannerIntegrationTest extends TestCase
{
    use FixturePaths;

    private AttributeScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new AttributeScanner();
    }

    public function testPlainScanDiscoversEveryFixture(): void
    {
        self::assertCount(12, $this->scanner->scan([self::fixturePath()]));
    }

    public function testScanForAttributeFindsOnlyAnnotatedClasses(): void
    {
        $result = $this->scanner->scanForAttribute(self::fixtureClass('SampleAttribute'), [self::fixturePath()]);

        self::assertSame([self::fixtureClass('AttributedClass')], array_keys($result->getClasses()));
    }

    public function testScanForAttributesUsesOrSemantics(): void
    {
        $result = $this->scanner->scanForAttributes([self::fixtureClass('SampleAttribute'), 'Attribute'], [self::fixturePath()]);

        // AttributedClass carries SampleAttribute; the three attribute classes carry #[Attribute].
        self::assertCount(4, $result);
    }

    public function testUnknownAttributeFindsNothing(): void
    {
        self::assertCount(0, $this->scanner->scanForAttribute('TotallyAbsentAttribute', [self::fixturePath()]));
    }

    public function testAttributeOfOneCallNeverNarrowsTheNext(): void
    {
        // Regression (SRC-002): filters used to accumulate, so the second call required BOTH attributes.
        $this->scanner->scanForAttribute('TotallyAbsentAttribute', [self::fixturePath()]);

        self::assertCount(1, $this->scanner->scanForAttribute(self::fixtureClass('SampleAttribute'), [self::fixturePath()]));
    }

    public function testPersistentFiltersCombineWithTheCallFilter(): void
    {
        $this->scanner->addFilter(new StructuralFilter(isFinal: true));

        // Every #[Attribute] class is final; AttributedClass (SampleAttribute) is not.
        self::assertCount(3, $this->scanner->scanForAttributes([self::fixtureClass('SampleAttribute'), 'Attribute'], [self::fixturePath()]));
    }

    public function testDifferentAttributesNeverShareACacheEntry(): void
    {
        // Regression: the cache key used only filter class names, so AttributeFilter('A')
        // and AttributeFilter('B') collided in a shared cache.
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());

        self::assertCount(1, $this->scanner->scanForAttribute(self::fixtureClass('SampleAttribute'), [self::fixturePath()]));
        self::assertCount(3, $this->scanner->scanForAttribute('Attribute', [self::fixturePath()]));
    }

    public function testClearCacheWithoutACacheIsANoOp(): void
    {
        $this->scanner->clearCache();

        self::assertCount(12, $this->scanner->scan([self::fixturePath()]));
    }

    public function testDiscoverReturnsTheFqcnsOfAnnotatedClasses(): void
    {
        self::assertSame(
            [self::fixtureClass('AttributedClass')],
            $this->scanner->discover(self::fixtureClass('SampleAttribute'), [self::fixturePath()]),
        );
    }

    public function testDiscoverServesAsTheTypedClosureObservabilityConsumes(): void
    {
        // KC Observability: 'discovery' => $scanner->discover(...), called with Traced::class-style FQCNs
        $finder = $this->scanner->discover(...);

        self::assertSame([self::fixtureClass('AttributedClass')], $finder(SampleAttribute::class, [self::fixturePath()]));
    }

    public function testClearCacheEmptiesTheConfiguredCache(): void
    {
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());
        $first = $this->scanner->scan([self::fixturePath()]);
        self::assertSame($first, $this->scanner->scan([self::fixturePath()]), 'served from the cache');

        $this->scanner->clearCache();

        self::assertNotSame($first, $this->scanner->scan([self::fixturePath()]));
    }
}
