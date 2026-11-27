<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

final readonly class SampleFinalReadonlyClass
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}
}
