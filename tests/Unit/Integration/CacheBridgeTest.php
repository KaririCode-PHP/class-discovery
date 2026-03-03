<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Integration;

use KaririCode\ClassDiscovery\Integration\CacheBridge;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheBridge::class)]
final class CacheBridgeTest extends TestCase
{
    use MetadataFactory;

    /** Simple in-memory PSR-16-like object for testing */
    private object $simpleCache;

    protected function setUp(): void
    {
        $this->simpleCache = new class () {
            private array $store = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->store[$key] ?? $default;
            }

            public function set(string $key, mixed $value, int|null $ttl = null): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function clear(): bool
            {
                $this->store = [];

                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->store[$key]);
            }
        };
    }

    public function testSetAndGetWithPrefix(): void
    {
        $bridge = new CacheBridge($this->simpleCache, 'test_');
        $result = new DiscoveryResult(
            ['App\\A' => self::createClassMetadata(name: 'App\\A')],
        );

        $bridge->set('scan', $result);

        // Bridge should prepend prefix
        self::assertTrue($this->simpleCache->has('test_scan'));

        $cached = $bridge->get('scan');
        self::assertNotNull($cached);
        self::assertInstanceOf(DiscoveryResult::class, $cached);
        self::assertTrue($cached->hasClass('App\\A'));
    }

    public function testGetReturnsNullForNonDiscoveryResult(): void
    {
        $this->simpleCache->set('test_key', 'not-a-result');
        $bridge = new CacheBridge($this->simpleCache, 'test_');

        self::assertNull($bridge->get('key'));
    }

    public function testHas(): void
    {
        $bridge = new CacheBridge($this->simpleCache, 'pfx_');

        self::assertFalse($bridge->has('key'));

        $bridge->set('key', new DiscoveryResult());

        self::assertTrue($bridge->has('key'));
    }

    public function testDelete(): void
    {
        $bridge = new CacheBridge($this->simpleCache, 'pfx_');
        $bridge->set('key', new DiscoveryResult());
        $bridge->delete('key');

        self::assertFalse($bridge->has('key'));
    }

    public function testClear(): void
    {
        $bridge = new CacheBridge($this->simpleCache, 'pfx_');
        $bridge->set('a', new DiscoveryResult());
        $bridge->clear();

        self::assertFalse($bridge->has('a'));
    }

    public function testDefaultPrefix(): void
    {
        $bridge = new CacheBridge($this->simpleCache);
        $bridge->set('key', new DiscoveryResult());

        self::assertTrue($this->simpleCache->has('kc_discovery_key'));
    }
}
