<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

use Attribute;

/**
 * Sample method-level PHP attribute for testing the attribute scanner.
 *
 * @package KaririCode\ClassDiscovery\Tests\Fixture
 * @since   3.0.0
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class MethodAttribute
{
}
