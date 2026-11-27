<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Specimen;

use KaririCode\ClassDiscovery\Tests\Fixture\SampleAttribute;

/**
 * Reflection specimen: a TARGET_CLASS attribute placed on a method. PHP reads
 * its arguments but refuses to instantiate it, which ReflectionScanner must
 * report as "no instance" without failing the scan.
 */
final class MisplacedAttribute
{
    #[SampleAttribute(name: 'misplaced')]
    public function run(): void {}
}
