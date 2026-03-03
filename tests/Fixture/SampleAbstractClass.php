<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

abstract class SampleAbstractClass
{
    abstract public function process(): void;

    public function shared(): string
    {
        return 'shared';
    }
}
