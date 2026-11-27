<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

#[SampleAttribute(name: 'test')]
class AttributedClass
{
    #[PropertyAttribute]
    public string $field = '';

    #[MethodAttribute]
    public function execute(): void {}
}
