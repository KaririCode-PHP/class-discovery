<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Integration;

use KaririCode\ClassDiscovery\Integration\CacheBridge;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\Double\InMemorySimpleCache;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class CacheBridgeTest extends TestCase
{
    use MetadataFactory;

    private InMemorySimpleCache $psr16;

    protected function setUp(): void
    {
        $this->psr16 = new InMemorySimpleCache();
    }

    public function testRoundTripThroughPrefixedKey(): void
    {
        $bridge = new CacheBridge($this->psr16, 'test_');
        $bridge->set('scan', new DiscoveryResult(['App\\A' => self::createClassMetadata(name: 'App\\A')]));

        self::assertTrue($this->psr16->has('test_scan'));
        self::assertTrue($bridge->get('scan')?->hasClass('App\\A'));
    }

    public function testForeignValueUnderTheKeyIsAMiss(): void
    {
        $this->psr16->set('test_key', 'not-a-result');

        self::assertNull(new CacheBridge($this->psr16, 'test_')->get('key'));
    }

    public function testHasDeleteAndClear(): void
    {
        $bridge = new CacheBridge($this->psr16, 'pfx_');
        $bridge->set('a', new DiscoveryResult());
        $bridge->set('b', new DiscoveryResult());

        self::assertTrue($bridge->has('a'));
        $bridge->delete('a');
        self::assertFalse($bridge->has('a'));

        $bridge->clear();
        self::assertFalse($bridge->has('b'));
    }

    public function testDefaultPrefix(): void
    {
        new CacheBridge($this->psr16)->set('key', new DiscoveryResult());

        self::assertTrue($this->psr16->has('kc_discovery_key'));
    }

    public function testPositiveTtlIsForwardedAndNonPositiveMeansNoExpiry(): void
    {
        $bridge = new CacheBridge($this->psr16);

        $bridge->set('a', new DiscoveryResult(), 120);
        self::assertSame(120, $this->psr16->lastTtl);

        $bridge->set('b', new DiscoveryResult(), 0);
        self::assertNull($this->psr16->lastTtl);
    }
}
