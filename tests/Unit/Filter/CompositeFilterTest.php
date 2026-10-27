<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Filter\{CompositeFilter, InterfaceFilter, NamespaceFilter, StructuralFilter};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class CompositeFilterTest extends TestCase
{
    use MetadataFactory;

    // NOTE: $requireAll is the first constructor parameter followed by a
    // variadic ...$filters. PHP forbids positional arguments after a named
    // one, so $requireAll must be passed positionally when filters follow.

    public function testOrLogicAnyFilterPasses(): void
    {
        $meta = self::createClassMetadata(isFinal: true, namespace: 'App\\Service');

        $filter = new CompositeFilter(
            false,
            new NamespaceFilter('App\\Controller'),  // Fails
            new StructuralFilter(isFinal: true),      // Passes
        );

        self::assertTrue($filter->accept($meta));
    }

    public function testOrLogicNonePass(): void
    {
        $meta = self::createClassMetadata(isFinal: false, namespace: 'App\\Service');

        $filter = new CompositeFilter(
            false,
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

        $filter = new CompositeFilter(
            true,
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

        $filter = new CompositeFilter(
            true,
            new NamespaceFilter('App\\Controller'),
            new InterfaceFilter('Countable'),  // Fails
        );

        self::assertFalse($filter->accept($meta));
    }

    public function testEmptyFiltersAcceptAll(): void
    {
        $orFilter = new CompositeFilter(false);
        $andFilter = new CompositeFilter(true);

        $meta = self::createClassMetadata();

        self::assertTrue($orFilter->accept($meta));
        self::assertTrue($andFilter->accept($meta));
    }
}
