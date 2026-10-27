<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class MethodAttribute {}
