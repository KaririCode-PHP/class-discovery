<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\Double\SettableClock;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class MemoryCacheStrategyTest extends TestCase
{
    use MetadataFactory;

    private MemoryCacheStrategy $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryCacheStrategy();
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->cache->get('nonexistent'));
    }

    public function testSetAndGet(): void
    {
        $result = new DiscoveryResult(
            ['App\\A' => self::createClassMetadata(name: 'App\\A')],
        );

        $this->cache->set('scan-1', $result);

        self::assertSame($result, $this->cache->get('scan-1'));
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
        self::assertNull($this->cache->get('key'));
    }

    public function testClear(): void
    {
        $this->cache->set('a', new DiscoveryResult());
        $this->cache->set('b', new DiscoveryResult());
        $this->cache->clear();

        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function testEntryWithinItsTtlIsServed(): void
    {
        $result = new DiscoveryResult();
        $this->cache->set('ttl-key', $result, 3600);

        self::assertSame($result, $this->cache->get('ttl-key'));
    }

    public function testNonPositiveTtlMeansNoExpiry(): void
    {
        $result = new DiscoveryResult();
        $this->cache->set('forever', $result, 0);

        self::assertSame($result, $this->cache->get('forever'));
        self::assertTrue($this->cache->has('forever'));
    }

    public function testOverwriteExistingKey(): void
    {
        $resultA = new DiscoveryResult([], [], 1.0);
        $resultB = new DiscoveryResult([], [], 2.0);

        $this->cache->set('key', $resultA);
        $this->cache->set('key', $resultB);

        $cached = $this->cache->get('key');
        self::assertSame(2.0, $cached?->getScanDuration());
    }

    public function testEntryIsServedUntilItsTtlElapsesThenPurged(): void
    {
        $clock = new SettableClock();
        $cache = new MemoryCacheStrategy($clock);
        $cache->set('k', new DiscoveryResult(), 60);

        $clock->advance(60);
        self::assertNotNull($cache->get('k'), 'still valid at exactly the TTL');

        $clock->advance(1);
        self::assertNull($cache->get('k'));
        self::assertFalse($cache->has('k'));
    }

    public function testZeroTtlNeverExpires(): void
    {
        $clock = new SettableClock();
        $cache = new MemoryCacheStrategy($clock);
        $cache->set('k', new DiscoveryResult(), 0);

        $clock->advance(10 * 365 * 24 * 3600);
        self::assertNotNull($cache->get('k'));
    }
}
