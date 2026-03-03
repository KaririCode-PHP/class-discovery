<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Filter;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Result\{AttributeMetadata, MethodMetadata, PropertyMetadata};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeFilter::class)]
final class AttributeFilterTest extends TestCase
{
    use MetadataFactory;

    public function testAcceptsClassWithMatchingClassAttribute(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Route');
        $meta = self::createClassMetadata(attributes: ['App\\Route' => $attr]);

        $filter = new AttributeFilter('App\\Route');

        self::assertTrue($filter->accept($meta));
    }

    public function testRejectsClassWithoutMatchingAttribute(): void
    {
        $meta = self::createClassMetadata(attributes: []);

        $filter = new AttributeFilter('App\\Route');

        self::assertFalse($filter->accept($meta));
    }

    public function testAcceptsClassWithMatchingMethodAttribute(): void
    {
        $attr = new AttributeMetadata(name: 'App\\Route', target: AttributeTarget::Method);
        $method = new MethodMetadata(name: 'index', attributes: ['App\\Route' => $attr]);
        $meta = self::createClassMetadata(methods: ['index' => $method]);

        $filter = new AttributeFilter('App\\Route');

        self::assertTrue($filter->accept($meta));
    }

    public function testAcceptsClassWithMatchingPropertyAttribute(): void
    {
        $attr = new AttributeMetadata(name: 'App\\Column', target: AttributeTarget::Property);
        $prop = new PropertyMetadata(name: 'field', attributes: ['App\\Column' => $attr]);
        $meta = self::createClassMetadata(properties: ['field' => $prop]);

        $filter = new AttributeFilter('App\\Column');

        self::assertTrue($filter->accept($meta));
    }

    public function testMultipleAttributesOrLogic(): void
    {
        $attr = self::createAttributeMetadata(name: 'App\\Route');
        $meta = self::createClassMetadata(attributes: ['App\\Route' => $attr]);

        // Has Route but not Command — OR logic should pass
        $filter = new AttributeFilter('App\\Route', 'App\\Command');

        self::assertTrue($filter->accept($meta));
    }

    public function testMultipleAttributesNoneMatch(): void
    {
        $meta = self::createClassMetadata(attributes: []);

        $filter = new AttributeFilter('App\\Route', 'App\\Command');

        self::assertFalse($filter->accept($meta));
    }
}
