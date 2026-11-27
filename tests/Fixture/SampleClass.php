<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

class SampleClass extends SampleAbstractClass implements SampleInterface
{
    use SampleTrait;

    public function __construct(
        private readonly string $name,
        private readonly int $value,
    ) {}

    public function handle(): void {}

    public function process(): void {}

    public function getName(): string
    {
        return $this->name;
    }
}
