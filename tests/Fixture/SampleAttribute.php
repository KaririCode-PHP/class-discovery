<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

use Attribute;

/**
 * Sample class-level PHP attribute for testing the attribute scanner.
 *
 * @package KaririCode\ClassDiscovery\Tests\Fixture
 * @since   3.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SampleAttribute
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
