<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\FileCacheStrategy;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileCacheStrategy::class)]
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

    public function testGetReturnsNullForExpiredEntry(): void
    {
        $result = new DiscoveryResult();

        // Set with TTL=1s and immediately expire it by manipulating the file
        $this->cache->set('expire', $result, 1);

        // Read the cache file, change expires to past time
        $files = glob($this->cacheDir . '/discovery_*.cache');
        self::assertNotEmpty($files);

        $data = unserialize(file_get_contents($files[0]) ?: '');
        $data['expires'] = time() - 10; // force expired
        file_put_contents($files[0], serialize($data));

        self::assertNull($this->cache->get('expire'));
    }

    public function testGetReturnsNullForCorruptFile(): void
    {
        $this->cache->set('corrupt', new DiscoveryResult());

        // Get cache file and write garbage
        $files = glob($this->cacheDir . '/discovery_*.cache');
        self::assertNotEmpty($files);
        file_put_contents($files[0], 'not-serialized-garbage!!!');

        self::assertNull($this->cache->get('corrupt'));
    }

    public function testGetThrowsOnInvalidResultType(): void
    {
        $this->cache->set('bad-type', new DiscoveryResult());

        // Find the cache file and replace with incorrect type
        $files = glob($this->cacheDir . '/discovery_*.cache');
        self::assertNotEmpty($files);

        $badData = serialize(['result' => new \stdClass(), 'expires' => 0]);
        file_put_contents($files[0], $badData);

        $this->expectException(DiscoveryException::class);
        $this->cache->get('bad-type');
    }

    public function testDeleteNonExistentKeyDoesNothing(): void
    {
        // Should not throw
        $this->cache->delete('nonexistent-key-xyz');
        self::assertTrue(true);
    }

    public function testSetWithZeroTtlStoresForever(): void
    {
        $result = new DiscoveryResult();
        $this->cache->set('forever', $result, 0);

        self::assertNotNull($this->cache->get('forever'));
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
