<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\FileCacheStrategy;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class FileCacheStrategyTest extends TestCase
{
    use MetadataFactory;

    private string $cacheDir;
    private FileCacheStrategy $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/kc_test_cache_' . uniqid();
        $this->cache = new FileCacheStrategy($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->cache->get('nonexistent'));
    }

    public function testSetAndGet(): void
    {
        $result = new DiscoveryResult(
            ['App\\A' => self::createClassMetadata(name: 'App\\A')],
            [],
            0.05,
        );

        $this->cache->set('scan', $result);

        $cached = $this->cache->get('scan');
        self::assertNotNull($cached);
        self::assertCount(1, $cached);
        self::assertTrue($cached->hasClass('App\\A'));
        self::assertSame(0.05, $cached->getScanDuration());
    }

    public function testHas(): void
    {
        self::assertFalse($this->cache->has('key'));

        $this->cache->set('key', new DiscoveryResult());

        self::assertTrue($this->cache->has('key'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key', new DiscoveryResult());
        $this->cache->delete('key');

        self::assertFalse($this->cache->has('key'));
    }

    public function testClear(): void
    {
        $this->cache->set('a', new DiscoveryResult());
        $this->cache->set('b', new DiscoveryResult());
        $this->cache->clear();

        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function testPersistenceAcrossInstances(): void
    {
        $result = new DiscoveryResult(
            ['App\\X' => self::createClassMetadata(name: 'App\\X')],
        );

        $this->cache->set('persist', $result);

        // New instance, same directory
        $cache2 = new FileCacheStrategy($this->cacheDir);
        $cached = $cache2->get('persist');

        self::assertNotNull($cached);
        self::assertTrue($cached->hasClass('App\\X'));
    }

    public function testCreatesDirectoryOnConstruction(): void
    {
        $dir = sys_get_temp_dir() . '/kc_new_dir_' . uniqid();
        self::assertDirectoryDoesNotExist($dir);

        new FileCacheStrategy($dir);

        self::assertDirectoryExists($dir);
        $this->removeDir($dir);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
