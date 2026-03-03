<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Enum;

use KaririCode\ClassDiscovery\Enum\ScannerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScannerType::class)]
final class ScannerTypeTest extends TestCase
{
    public function testIsPureEnum(): void
    {
        $ref = new \ReflectionEnum(ScannerType::class);

        self::assertFalse($ref->isBacked());
    }

    public function testExpectedCases(): void
    {
        $names = array_map(
            static fn (ScannerType $case): string => $case->name,
            ScannerType::cases(),
        );

        self::assertContains('Reflection', $names);
        self::assertContains('Attribute', $names);
        self::assertContains('File', $names);
        self::assertContains('Directory', $names);
        self::assertContains('Composer', $names);
        self::assertContains('Git', $names);
        self::assertContains('HotReload', $names);
    }

    public function testCaseCount(): void
    {
        self::assertCount(7, ScannerType::cases());
    }
}
