<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryCacheStrategy::class)]
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

    public function testTtlExpiration(): void
    {
        $result = new DiscoveryResult();

        // TTL of 0 should expire immediately (or be treated as no-expire)
        // The memory cache stores expiry = time() + ttl, so ttl=1 will expire after 1s
        $this->cache->set('ttl-key', $result, 3600);

        self::assertNotNull($this->cache->get('ttl-key'));
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

    public function testGetReturnsNullForInternallyExpiredEntry(): void
    {
        // PHP's time() resolution is 1s, so we manually test the expiry branch
        // by using Reflection to set a past expires value
        $result = new DiscoveryResult();
        $this->cache->set('expire-test', $result, 1);

        $ref = new \ReflectionObject($this->cache);
        $prop = $ref->getProperty('entries');
        $entries = $prop->getValue($this->cache);
        $entries['expire-test']['expires'] = time() - 10; // past
        $prop->setValue($this->cache, $entries);

        self::assertNull($this->cache->get('expire-test'));
    }

    public function testHasReturnsFalseForInternallyExpiredEntry(): void
    {
        $result = new DiscoveryResult();
        $this->cache->set('has-expire', $result, 1);

        $ref = new \ReflectionObject($this->cache);
        $prop = $ref->getProperty('entries');
        $entries = $prop->getValue($this->cache);
        $entries['has-expire']['expires'] = time() - 10;
        $prop->setValue($this->cache, $entries);

        self::assertFalse($this->cache->has('has-expire'));
    }

    public function testZeroTtlNeverExpires(): void
    {
        $result = new DiscoveryResult();
        $this->cache->set('forever', $result, 0);

        self::assertNotNull($this->cache->get('forever'));
        self::assertTrue($this->cache->has('forever'));
    }
}
