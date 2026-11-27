<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Filter\NamespaceFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Scanner\ScanCacheKey;
use PHPUnit\Framework\TestCase;

final class ScanCacheKeyTest extends TestCase
{
    public function testSameRequestSameKey(): void
    {
        self::assertSame(
            ScanCacheKey::of('S', [__DIR__], [new AttributeFilter('A')])->value,
            ScanCacheKey::of('S', [__DIR__], [new AttributeFilter('A')])->value,
        );
    }

    public function testFilterConfigurationParticipates(): void
    {
        self::assertNotSame(
            ScanCacheKey::of('S', [__DIR__], [new AttributeFilter('A')])->value,
            ScanCacheKey::of('S', [__DIR__], [new AttributeFilter('B')])->value,
        );
        self::assertNotSame(
            ScanCacheKey::of('S', [__DIR__], [new NamespaceFilter('App\\A')])->value,
            ScanCacheKey::of('S', [__DIR__], [new NamespaceFilter('App\\B')])->value,
        );
    }

    public function testScannerAndPathsParticipate(): void
    {
        $base = ScanCacheKey::of('S', [__DIR__], [])->value;

        self::assertNotSame($base, ScanCacheKey::of('T', [__DIR__], [])->value);
        self::assertNotSame($base, ScanCacheKey::of('S', [dirname(__DIR__)], [])->value);
        self::assertSame($base, ScanCacheKey::of('S', [__DIR__ . '/../Scanner'], [])->value, 'paths are compared after realpath()');
    }

    public function testNonSerializableFilterNeverProducesAFalseHit(): void
    {
        $closureFilter = static fn(): ClassFilter => new class (static fn(): bool => true) implements ClassFilter {
            /** @param \Closure(): bool $predicate */
            public function __construct(private readonly \Closure $predicate) {}

            public function accept(ClassMetadata $metadata): bool
            {
                return ($this->predicate)();
            }
        };
        $first = $closureFilter();
        $second = $closureFilter();

        self::assertSame(ScanCacheKey::of('S', [], [$first])->value, ScanCacheKey::of('S', [], [$first])->value);
        self::assertNotSame(ScanCacheKey::of('S', [], [$first])->value, ScanCacheKey::of('S', [], [$second])->value);
    }

    public function testUnresolvablePathsStillDistinguishRequests(): void
    {
        self::assertNotSame(
            ScanCacheKey::of('S', ['/nonexistent/a'], [])->value,
            ScanCacheKey::of('S', ['/nonexistent/b'], [])->value,
        );
    }

    public function testPartBoundariesAreUnambiguous(): void
    {
        self::assertNotSame(
            ScanCacheKey::of('S', ['/nonexistent/ab'], [])->value,
            ScanCacheKey::of('S', ['/nonexistent/a', 'b'], [])->value,
        );
    }
}
