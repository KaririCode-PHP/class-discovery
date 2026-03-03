<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructuralFilter::class)]
final class StructuralFilterTest extends TestCase
{
    use MetadataFactory;

    public function testFilterByFinal(): void
    {
        $final = self::createClassMetadata(isFinal: true);
        $nonFinal = self::createClassMetadata(isFinal: false);

        $filter = new StructuralFilter(isFinal: true);

        self::assertTrue($filter->accept($final));
        self::assertFalse($filter->accept($nonFinal));
    }

    public function testFilterByAbstract(): void
    {
        $abstract = self::createClassMetadata(isAbstract: true);
        $concrete = self::createClassMetadata(isAbstract: false);

        $filter = new StructuralFilter(isAbstract: true);

        self::assertTrue($filter->accept($abstract));
        self::assertFalse($filter->accept($concrete));
    }

    public function testFilterByReadonly(): void
    {
        $readonly = self::createClassMetadata(isReadonly: true);
        $mutable = self::createClassMetadata(isReadonly: false);

        $filter = new StructuralFilter(isReadonly: true);

        self::assertTrue($filter->accept($readonly));
        self::assertFalse($filter->accept($mutable));
    }

    public function testFilterByInterface(): void
    {
        $iface = self::createClassMetadata(isInterface: true);
        $cls = self::createClassMetadata(isInterface: false);

        $filter = new StructuralFilter(isInterface: true);

        self::assertTrue($filter->accept($iface));
        self::assertFalse($filter->accept($cls));
    }

    public function testFilterByEnum(): void
    {
        $enum = self::createClassMetadata(isEnum: true);
        $cls = self::createClassMetadata(isEnum: false);

        $filter = new StructuralFilter(isEnum: true);

        self::assertTrue($filter->accept($enum));
        self::assertFalse($filter->accept($cls));
    }

    public function testFilterByTrait(): void
    {
        $trait = self::createClassMetadata(isTrait: true);
        $cls = self::createClassMetadata(isTrait: false);

        $filter = new StructuralFilter(isTrait: true);

        self::assertTrue($filter->accept($trait));
        self::assertFalse($filter->accept($cls));
    }

    public function testCombinedFilters(): void
    {
        $finalReadonly = self::createClassMetadata(isFinal: true, isReadonly: true);
        $finalMutable = self::createClassMetadata(isFinal: true, isReadonly: false);

        $filter = new StructuralFilter(isFinal: true, isReadonly: true);

        self::assertTrue($filter->accept($finalReadonly));
        self::assertFalse($filter->accept($finalMutable));
    }

    public function testNullFiltersAcceptAll(): void
    {
        $meta = self::createClassMetadata();
        $filter = new StructuralFilter();

        self::assertTrue($filter->accept($meta));
    }

    public function testNegativeFilter(): void
    {
        $nonFinal = self::createClassMetadata(isFinal: false);
        $final = self::createClassMetadata(isFinal: true);

        $filter = new StructuralFilter(isFinal: false);

        self::assertTrue($filter->accept($nonFinal));
        self::assertFalse($filter->accept($final));
    }
}
