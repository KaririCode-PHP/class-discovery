<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Filter\NamespaceFilter;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class NamespaceFilterTest extends TestCase
{
    use MetadataFactory;

    public function testAcceptsMatchingPrefix(): void
    {
        $meta = self::createClassMetadata(namespace: 'App\\Controller');
        $filter = new NamespaceFilter('App\\Controller');

        self::assertTrue($filter->accept($meta));
    }

    public function testAcceptsSubNamespace(): void
    {
        $meta = self::createClassMetadata(namespace: 'App\\Controller\\Admin');
        $filter = new NamespaceFilter('App\\Controller');

        self::assertTrue($filter->accept($meta));
    }

    public function testRejectsNonMatchingPrefix(): void
    {
        $meta = self::createClassMetadata(namespace: 'App\\Service');
        $filter = new NamespaceFilter('App\\Controller');

        self::assertFalse($filter->accept($meta));
    }

    public function testRootNamespaceMatchesAll(): void
    {
        $meta = self::createClassMetadata(namespace: 'App\\Any\\Deep');
        $filter = new NamespaceFilter('App');

        self::assertTrue($filter->accept($meta));
    }
}
