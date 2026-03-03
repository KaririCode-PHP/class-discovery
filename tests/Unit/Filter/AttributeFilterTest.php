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

    /**
     * Covers L41: when the attribute name has NO backslash, shortName is null
     * and only exact matching is done (no short-name fallback).
     */
    public function testAcceptsClassWithShortNameAttributeNoNamespace(): void
    {
        // Attribute stored/searched as plain name (no backslash)
        $attr = self::createAttributeMetadata(name: 'Route');
        $meta = self::createClassMetadata(attributes: ['Route' => $attr]);

        // Filter with plain name — shortName branch is null (L41 covered)
        $filter = new AttributeFilter('Route');

        self::assertTrue($filter->accept($meta));
    }

    public function testRejectsWhenShortNameNoNamespaceDoesNotMatch(): void
    {
        $attr = self::createAttributeMetadata(name: 'Route');
        $meta = self::createClassMetadata(attributes: ['Route' => $attr]);

        // Searching for 'Command' plain name — no match
        $filter = new AttributeFilter('Command');

        self::assertFalse($filter->accept($meta));
    }

    public function testAcceptsViaShortNameFallbackOnMethod(): void
    {
        // Filter with FQCN, attribute stored with short name on method
        $attr = new AttributeMetadata(name: 'Route', target: AttributeTarget::Method);
        $method = new MethodMetadata(name: 'index', attributes: ['Route' => $attr]);
        $meta = self::createClassMetadata(methods: ['index' => $method]);

        // FQCN filter — shortName = 'Route' — matches method attribute stored as 'Route'
        $filter = new AttributeFilter('App\\Route');

        self::assertTrue($filter->accept($meta));
    }

    public function testAcceptsViaShortNameFallbackOnProperty(): void
    {
        // Filter with FQCN, attribute stored with short name on property
        $attr = new AttributeMetadata(name: 'Column', target: AttributeTarget::Property);
        $prop = new PropertyMetadata(name: 'id', attributes: ['Column' => $attr]);
        $meta = self::createClassMetadata(properties: ['id' => $prop]);

        // FQCN filter — shortName = 'Column' — matches property attribute stored as 'Column'
        $filter = new AttributeFilter('Doctrine\\Column');

        self::assertTrue($filter->accept($meta));
    }

    public function testRejectsWhenNoAttributeClassesGiven(): void
    {
        $attr = self::createAttributeMetadata(name: 'Route');
        $meta = self::createClassMetadata(attributes: ['Route' => $attr]);

        // Empty filter — foreach never executes → returns false
        $filter = new AttributeFilter();

        self::assertFalse($filter->accept($meta));
    }
}
