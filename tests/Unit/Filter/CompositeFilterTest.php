<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Filter\{CompositeFilter, InterfaceFilter, NamespaceFilter, StructuralFilter};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(CompositeFilter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(NamespaceFilter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(StructuralFilter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InterfaceFilter::class)]
final class CompositeFilterTest extends TestCase
{
    use MetadataFactory;

    public function testOrLogicAnyFilterPasses(): void
    {
        $meta = self::createClassMetadata(isFinal: true, namespace: 'App\\Service');

        $filter = CompositeFilter::any(
            new NamespaceFilter('App\\Controller'),  // Fails
            new StructuralFilter(isFinal: true),     // Passes
        );

        self::assertTrue($filter->accept($meta));
    }

    public function testOrLogicNonePass(): void
    {
        $meta = self::createClassMetadata(isFinal: false, namespace: 'App\\Service');

        $filter = CompositeFilter::any(
            new NamespaceFilter('App\\Controller'),
            new StructuralFilter(isFinal: true),
        );

        self::assertFalse($filter->accept($meta));
    }

    public function testAndLogicAllMustPass(): void
    {
        $meta = self::createClassMetadata(
            isFinal: true,
            namespace: 'App\\Controller',
            interfaces: ['Countable'],
        );

        $filter = CompositeFilter::all(
            new NamespaceFilter('App\\Controller'),
            new StructuralFilter(isFinal: true),
            new InterfaceFilter('Countable'),
        );

        self::assertTrue($filter->accept($meta));
    }

    public function testAndLogicOneFailsRejects(): void
    {
        $meta = self::createClassMetadata(
            isFinal: true,
            namespace: 'App\\Controller',
            interfaces: [],
        );

        $filter = CompositeFilter::all(
            new NamespaceFilter('App\\Controller'),
            new InterfaceFilter('Countable'),  // Fails
        );

        self::assertFalse($filter->accept($meta));
    }

    public function testEmptyFiltersAcceptAll(): void
    {
        $meta = self::createClassMetadata();

        $orFilter = CompositeFilter::any();
        $andFilter = CompositeFilter::all();

        self::assertTrue($orFilter->accept($meta));
        self::assertTrue($andFilter->accept($meta));
    }
}
