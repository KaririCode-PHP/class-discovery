<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Filter\InterfaceFilter;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterfaceFilter::class)]
final class InterfaceFilterTest extends TestCase
{
    use MetadataFactory;

    public function testAcceptsClassImplementingInterface(): void
    {
        $meta = self::createClassMetadata(interfaces: ['Countable', 'Stringable']);
        $filter = new InterfaceFilter('Countable');

        self::assertTrue($filter->accept($meta));
    }

    public function testRejectsClassNotImplementingInterface(): void
    {
        $meta = self::createClassMetadata(interfaces: ['Stringable']);
        $filter = new InterfaceFilter('Countable');

        self::assertFalse($filter->accept($meta));
    }

    public function testRejectsClassWithNoInterfaces(): void
    {
        $meta = self::createClassMetadata(interfaces: []);
        $filter = new InterfaceFilter('Countable');

        self::assertFalse($filter->accept($meta));
    }
}
