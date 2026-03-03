<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class SampleAttribute
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class MethodAttribute
{
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final class PropertyAttribute
{
}
