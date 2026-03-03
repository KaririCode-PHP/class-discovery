<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\{ChainCacheStrategy, MemoryCacheStrategy};
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainCacheStrategy::class)]
final class ChainCacheStrategyTest extends TestCase
{
    use MetadataFactory;

    public function testGetPromotesToEarlierTier(): void
    {
        $l1 = new MemoryCacheStrategy();
        $l2 = new MemoryCacheStrategy();
        $chain = new ChainCacheStrategy($l1, $l2);

        $result = new DiscoveryResult(
            ['App\\A' => self::createClassMetadata(name: 'App\\A')],
        );

        // Seed L2 only
        $l2->set('key', $result);

        // L1 miss → L2 hit → promotes to L1
        $cached = $chain->get('key');
        self::assertNotNull($cached);

        // Now L1 should have it
        self::assertNotNull($l1->get('key'));
    }

    public function testGetReturnsNullWhenAllMiss(): void
    {
        $chain = new ChainCacheStrategy(
            new MemoryCacheStrategy(),
            new MemoryCacheStrategy(),
        );

        self::assertNull($chain->get('missing'));
    }

    public function testSetWritesToAllTiers(): void
    {
        $l1 = new MemoryCacheStrategy();
        $l2 = new MemoryCacheStrategy();
        $chain = new ChainCacheStrategy($l1, $l2);

        $result = new DiscoveryResult();
        $chain->set('key', $result);

        self::assertTrue($l1->has('key'));
        self::assertTrue($l2->has('key'));
    }

    public function testDeleteRemovesFromAllTiers(): void
    {
        $l1 = new MemoryCacheStrategy();
        $l2 = new MemoryCacheStrategy();
        $chain = new ChainCacheStrategy($l1, $l2);

        $chain->set('key', new DiscoveryResult());
        $chain->delete('key');

        self::assertFalse($l1->has('key'));
        self::assertFalse($l2->has('key'));
    }

    public function testClearClearsAllTiers(): void
    {
        $l1 = new MemoryCacheStrategy();
        $l2 = new MemoryCacheStrategy();
        $chain = new ChainCacheStrategy($l1, $l2);

        $chain->set('a', new DiscoveryResult());
        $chain->clear();

        self::assertFalse($l1->has('a'));
        self::assertFalse($l2->has('a'));
    }

    public function testHasChecksAllTiers(): void
    {
        $l1 = new MemoryCacheStrategy();
        $l2 = new MemoryCacheStrategy();
        $chain = new ChainCacheStrategy($l1, $l2);

        $l2->set('key', new DiscoveryResult());

        self::assertTrue($chain->has('key'));
        self::assertFalse($chain->has('other'));
    }
}
