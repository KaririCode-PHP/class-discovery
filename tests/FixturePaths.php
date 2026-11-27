<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests;

use PHPUnit\Framework\Assert;

/**
 * Resolved paths into the project and its fixtures.
 */
trait FixturePaths
{
    private static function fixtureClass(string $shortName): string
    {
        return 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\' . $shortName;
    }

    private static function projectRoot(): string
    {
        return self::resolved(__DIR__ . '/..');
    }

    private static function fixturePath(string $relative = ''): string
    {
        return self::resolved(__DIR__ . '/Fixture' . ($relative === '' ? '' : '/' . $relative));
    }

    private static function resolved(string $path): string
    {
        $real = realpath($path);
        Assert::assertIsString($real, "Missing test path: {$path}");

        return $real;
    }
}
