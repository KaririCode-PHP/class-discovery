<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

trait SampleTrait
{
    public function traitMethod(): string
    {
        return 'trait';
    }
}
