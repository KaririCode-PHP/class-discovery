<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

final class ChildClass extends SampleClass
{
    public function __construct(
        string $name,
        int $value,
        private readonly float $extra,
    ) {
        parent::__construct($name, $value);
    }
}
