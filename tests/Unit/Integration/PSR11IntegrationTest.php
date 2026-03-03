<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Integration;

use KaririCode\ClassDiscovery\Analyzer\CircularDetector;
use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Contract\{AttributeScanner as AttributeScannerContract, Scanner};
use KaririCode\ClassDiscovery\Integration\PSR11Integration;
use KaririCode\ClassDiscovery\Scanner\{AttributeScanner, ReflectionScanner};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PSR11Integration::class)]
final class PSR11IntegrationTest extends TestCase
{
    public function testCreateDefaultScanner(): void
    {
        $scanner = PSR11Integration::createDefaultScanner();

        self::assertInstanceOf(Scanner::class, $scanner);
        self::assertInstanceOf(AttributeScanner::class, $scanner);
    }

    public function testCreateDefaultScannerWithCache(): void
    {
        $cache = new MemoryCacheStrategy();
        $scanner = PSR11Integration::createDefaultScanner($cache);

        self::assertInstanceOf(Scanner::class, $scanner);
    }

    public function testCreateAttributeScanner(): void
    {
        $scanner = PSR11Integration::createAttributeScanner();

        self::assertInstanceOf(AttributeScannerContract::class, $scanner);
    }

    public function testCreateReflectionScanner(): void
    {
        $scanner = PSR11Integration::createReflectionScanner();

        self::assertInstanceOf(ReflectionScanner::class, $scanner);
    }

    public function testCreateCircularDetector(): void
    {
        $detector = PSR11Integration::createCircularDetector(throwOnDetection: true);

        self::assertInstanceOf(CircularDetector::class, $detector);
    }

    public function testCreateAttributeScannerWithCache(): void
    {
        $cache = new MemoryCacheStrategy();
        $scanner = PSR11Integration::createAttributeScanner($cache);

        self::assertInstanceOf(AttributeScannerContract::class, $scanner);
    }
}
