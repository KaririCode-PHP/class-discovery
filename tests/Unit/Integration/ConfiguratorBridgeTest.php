<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Integration;

use KaririCode\ClassDiscovery\Cache\ChainCacheStrategy;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Integration\ConfiguratorBridge;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfiguratorBridgeTest extends TestCase
{
    use FixturePaths;
    use TemporaryDirectories;

    public function testBuildsAnAttributeScannerFromConfiguration(): void
    {
        $scanner = ConfiguratorBridge::createScanner([
            'cache' => ['enabled' => false],
        ]);

        self::assertInstanceOf(AttributeScanner::class, $scanner);
        self::assertCount(12, $scanner->scan([self::fixturePath()]));
    }

    public function testNumericAndBooleanStringsFromTheEnvironmentAreAccepted(): void
    {
        $scanner = ConfiguratorBridge::createScanner([
            'max_files' => '1',
            'max_file_size' => '1048576',
            'cache' => ['enabled' => '0'],
        ]);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8711); // max_files '1' was honoured

        $scanner->scan([self::fixturePath()]);
    }

    public function testCacheStrategyIsMemoryOverFile(): void
    {
        $cache = ConfiguratorBridge::createCacheStrategy(['dir' => $this->temporaryDirectory() . '/cache']);

        self::assertInstanceOf(ChainCacheStrategy::class, $cache);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidOptionsFailFast(array $config, string $option): void
    {
        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8740);
        $this->expectExceptionMessage("'{$option}'");

        ConfiguratorBridge::createScanner($config + ['cache' => ['enabled' => false]]);
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): \Generator
    {
        yield 'non-numeric max_files' => [['max_files' => 'many'], 'max_files'];
        yield 'boolean max_file_size' => [['max_file_size' => true], 'max_file_size'];
        yield 'float max_files' => [['max_files' => 1.5], 'max_files'];
        yield 'scalar cache section' => [['cache' => 'yes'], 'cache'];
        yield 'array cache.enabled' => [['cache' => ['enabled' => []]], 'enabled'];
        yield 'non-string cache.dir' => [['cache' => ['enabled' => true, 'dir' => 7]], 'dir'];
    }
}
