<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Integration;

use KaririCode\ClassDiscovery\Cache\ChainCacheStrategy;
use KaririCode\ClassDiscovery\Contract\{CacheStrategy, Scanner};
use KaririCode\ClassDiscovery\Integration\ConfiguratorBridge;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguratorBridge::class)]
final class ConfiguratorBridgeTest extends TestCase
{
    public function testCreateScannerReturnsAttributeScanner(): void
    {
        $scanner = ConfiguratorBridge::createScanner();

        self::assertInstanceOf(Scanner::class, $scanner);
        self::assertInstanceOf(AttributeScanner::class, $scanner);
    }

    public function testCreateScannerWithDisabledCache(): void
    {
        $scanner = ConfiguratorBridge::createScanner([
            'cache' => ['enabled' => false],
        ]);

        self::assertInstanceOf(Scanner::class, $scanner);
    }

    public function testCreateCacheStrategyReturnsChain(): void
    {
        $cache = ConfiguratorBridge::createCacheStrategy([
            'dir' => sys_get_temp_dir() . '/kc_test_cfg_' . uniqid(),
        ]);

        self::assertInstanceOf(CacheStrategy::class, $cache);
        self::assertInstanceOf(ChainCacheStrategy::class, $cache);
    }
}
