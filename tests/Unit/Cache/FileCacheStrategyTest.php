<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Cache;

use KaririCode\ClassDiscovery\Cache\FileCacheStrategy;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Exception\InsecureCacheDirectoryException;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\Double\SettableClock;
use KaririCode\ClassDiscovery\Tests\Double\WakeupProbe;
use KaririCode\ClassDiscovery\Tests\Fixture\SampleEnum;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

final class FileCacheStrategyTest extends TestCase
{
    use MetadataFactory;
    use TemporaryDirectories;

    private string $cacheDir;
    private FileCacheStrategy $cache;

    protected function setUp(): void
    {
        $this->cacheDir = $this->temporaryDirectory() . '/cache';
        $this->cache = new FileCacheStrategy($this->cacheDir);
    }

    public function testMissingKeyIsNull(): void
    {
        self::assertNull($this->cache->get('nonexistent'));
        self::assertFalse($this->cache->has('nonexistent'));
    }

    public function testRoundTripPreservesTheResult(): void
    {
        $this->cache->set('scan', new DiscoveryResult(['App\\A' => self::createClassMetadata(name: 'App\\A')], [], 0.05));

        $cached = $this->cache->get('scan');
        self::assertNotNull($cached);
        self::assertTrue($cached->hasClass('App\\A'));
        self::assertSame(0.05, $cached->getScanDuration());
        self::assertTrue($this->cache->has('scan'));
    }

    public function testDeleteAndClear(): void
    {
        $this->cache->set('a', new DiscoveryResult());
        $this->cache->set('b', new DiscoveryResult());

        $this->cache->delete('a');
        self::assertFalse($this->cache->has('a'));
        self::assertTrue($this->cache->has('b'));

        $this->cache->clear();
        self::assertFalse($this->cache->has('b'));
    }

    public function testPersistsAcrossInstances(): void
    {
        $this->cache->set('persist', new DiscoveryResult(['App\\X' => self::createClassMetadata(name: 'App\\X')]));

        self::assertTrue(new FileCacheStrategy($this->cacheDir)->get('persist')?->hasClass('App\\X'));
    }

    public function testCreatesAPrivateDirectory(): void
    {
        self::assertSame(0o700, fileperms($this->cacheDir) & 0o777);
    }

    public function testRefusesAWorldWritableDirectory(): void
    {
        $dir = $this->temporaryDirectory();
        chmod($dir, 0o777);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8721);
        $this->expectExceptionMessage('world-writable');

        new FileCacheStrategy($dir);
    }

    public function testRefusesASymlinkedDirectory(): void
    {
        $link = $this->temporaryDirectory() . '/link';
        symlink($this->temporaryDirectory(), $link);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8721);
        $this->expectExceptionMessage('symbolic link');

        new FileCacheStrategy($link);
    }

    public function testObjectInjectionPayloadIsNeverInstantiated(): void
    {
        WakeupProbe::$woken = false;
        $file = $this->storeThenTamper(serialize(['result' => new WakeupProbe(), 'expires' => 0]));

        try {
            $this->cache->get('k');
            self::fail('A tampered entry must be refused');
        } catch (DiscoveryException $e) {
            self::assertSame(8720, $e->getCode());
        }

        self::assertFalse(WakeupProbe::$woken, 'unserialize() must not wake non-allow-listed classes');
        self::assertFileDoesNotExist($file);
    }

    public function testEntryWithAnUnrestorableArgumentObjectIsAMiss(): void
    {
        $this->cache->set('k', $this->resultWithArgument(new \ArrayObject([1, 2])));

        self::assertNull($this->cache->get('k'), 'A partially restored result must never be served');
        self::assertSame([], glob($this->cacheDir . '/discovery_*.cache'));
    }

    public function testEnumArgumentsSurviveTheRoundTrip(): void
    {
        $this->cache->set('k', $this->resultWithArgument(SampleEnum::Active));

        $cached = $this->cache->get('k');
        self::assertNotNull($cached);
        self::assertSame(SampleEnum::Active, $cached->getClass('App\\A')?->getAttribute('App\\Route')?->arguments['value']);
    }

    public function testKeysCannotAddressPathsOutsideTheDirectory(): void
    {
        $this->cache->set('../../escape', new DiscoveryResult());

        self::assertCount(1, (array) glob($this->cacheDir . '/discovery_*.cache'));
        self::assertFileDoesNotExist(dirname($this->cacheDir, 2) . '/escape');
        self::assertTrue($this->cache->has('../../escape'));
    }

    public function testExpiredEntryIsPurged(): void
    {
        $file = $this->storeThenTamper(serialize(['result' => new DiscoveryResult(), 'expires' => time() - 60]));

        self::assertNull($this->cache->get('k'));
        self::assertFileDoesNotExist($file);
    }

    public function testMalformedEntryIsPurged(): void
    {
        $file = $this->storeThenTamper('not a serialized entry');

        self::assertNull($this->cache->get('k'));
        self::assertFileDoesNotExist($file);
    }

    public function testNonPositiveTtlNeverExpiresAndPositiveTtlIsHonoured(): void
    {
        $this->cache->set('forever', new DiscoveryResult(), 0);
        $this->cache->set('negative', new DiscoveryResult(), -5);
        $this->cache->set('hour', new DiscoveryResult(), 3600);

        self::assertNotNull($this->cache->get('forever'));
        self::assertNotNull($this->cache->get('negative'));
        self::assertNotNull($this->cache->get('hour'));
    }

    public function testFutureAndZeroExpiryAreServed(): void
    {
        $this->storeThenTamper(serialize(['result' => new DiscoveryResult(), 'expires' => time() + 60]));
        self::assertNotNull($this->cache->get('k'));

        $this->storeThenTamper(serialize(['result' => new DiscoveryResult(), 'expires' => 0]));
        self::assertNotNull($this->cache->get('k'));
    }

    public function testUnrestorableObjectsNestedInMemberAttributesAreDetected(): void
    {
        $nested = self::createAttributeMetadata(name: 'App\\Route', arguments: ['deep' => ['deeper' => [new \ArrayObject()]]]);

        $this->cache->set('method', new DiscoveryResult(['App\\A' => self::createClassMetadata(
            name: 'App\\A',
            methods: ['run' => self::createMethodMetadata(name: 'run', attributes: ['App\\Route' => $nested])],
        )]));
        $this->cache->set('property', new DiscoveryResult(['App\\A' => self::createClassMetadata(
            name: 'App\\A',
            properties: ['id' => self::createPropertyMetadata(name: 'id', attributes: ['App\\Route' => $nested])],
        )]));

        self::assertNull($this->cache->get('method'));
        self::assertNull($this->cache->get('property'));
    }

    public function testScalarArgumentsAreServedFromCache(): void
    {
        $this->cache->set('k', $this->resultWithArgument(['methods' => ['GET', 'POST'], 'priority' => 5]));

        self::assertSame(
            ['value' => ['methods' => ['GET', 'POST'], 'priority' => 5]],
            $this->cache->get('k')?->getClass('App\\A')?->getAttribute('App\\Route')?->arguments,
        );
    }

    public function testClearOnlyRemovesCacheEntries(): void
    {
        file_put_contents($this->cacheDir . '/keep.txt', 'not a cache entry');
        $this->cache->set('a', new DiscoveryResult());

        $this->cache->clear();

        self::assertFileExists($this->cacheDir . '/keep.txt');
        self::assertFalse($this->cache->has('a'));
    }

    public function testDeletingAMissingKeyIsANoOp(): void
    {
        $this->cache->delete('never-stored');

        self::assertFalse($this->cache->has('never-stored'));
    }

    public function testEntryWithAnUnrestorableAttributeInstanceIsAMiss(): void
    {
        // ReflectionScanner stores attribute instances; their classes are not on the allow-list.
        $attribute = self::createAttributeMetadata(name: 'App\\Route', instance: new \ArrayObject(['path' => '/']));
        $this->cache->set('k', new DiscoveryResult(['App\\A' => self::createClassMetadata(name: 'App\\A', attributes: ['App\\Route' => $attribute])]));

        self::assertNull($this->cache->get('k'), 'an incomplete instance must never be served');
        self::assertSame([], glob($this->cacheDir . '/discovery_*.cache'));
    }

    public function testUncreatableDirectoryFailsClosedWithoutAWarning(): void
    {
        $blocker = $this->temporaryDirectory() . '/blocker';
        file_put_contents($blocker, 'a file where the directory should go');

        $this->expectException(InsecureCacheDirectoryException::class);
        $this->expectExceptionCode(8722);

        new FileCacheStrategy($blocker . '/cache');
    }

    /**
     * Writes a legitimate entry for key 'k', then replaces its bytes — simulating
     * corruption or an attacker with write access to the cache directory.
     */
    private function storeThenTamper(string $payload): string
    {
        $this->cache->set('k', new DiscoveryResult());
        $files = glob($this->cacheDir . '/discovery_*.cache');
        self::assertIsArray($files);
        self::assertCount(1, $files);
        file_put_contents($files[0], $payload);

        return $files[0];
    }

    private function resultWithArgument(mixed $argument): DiscoveryResult
    {
        $attribute = self::createAttributeMetadata(name: 'App\\Route', target: AttributeTarget::Class_, arguments: ['value' => $argument]);

        return new DiscoveryResult(['App\\A' => self::createClassMetadata(name: 'App\\A', attributes: ['App\\Route' => $attribute])]);
    }

    public function testEntryIsServedUntilItsTtlElapsesThenPurged(): void
    {
        $clock = new SettableClock();
        $cache = new FileCacheStrategy($this->temporaryDirectory() . '/clocked', $clock);
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
        $cache = new FileCacheStrategy($this->temporaryDirectory() . '/clocked', $clock);
        $cache->set('k', new DiscoveryResult(), 0);

        $clock->advance(10 * 365 * 24 * 3600);
        self::assertNotNull($cache->get('k'));
    }
}
