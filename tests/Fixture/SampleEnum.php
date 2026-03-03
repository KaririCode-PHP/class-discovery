<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Fixture;

enum SampleEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
