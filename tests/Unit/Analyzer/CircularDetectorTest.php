<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Analyzer;

use KaririCode\ClassDiscovery\Analyzer\CircularDetector;
use KaririCode\ClassDiscovery\Analyzer\DependencyAnalyzer;
use KaririCode\ClassDiscovery\Analyzer\DependencyCycle;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class CircularDetectorTest extends TestCase
{
    use MetadataFactory;

    public function testReportsCyclesWithoutThrowingByDefault(): void
    {
        $detector = new CircularDetector(new DependencyAnalyzer());

        self::assertSame(
            ['App\\A → App\\B → App\\A'],
            array_map(static fn(DependencyCycle $cycle): string => $cycle->chain(), $detector->check(self::mutualDependency())),
        );
    }

    public function testThrowsWithTheFormattedChainWhenConfigured(): void
    {
        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8730);
        $this->expectExceptionMessage('App\\A → App\\B → App\\A');

        $detector->check(self::mutualDependency());
    }

    public function testAcyclicResultNeitherThrowsNorReports(): void
    {
        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);
        $result = new DiscoveryResult([
            'App\\A' => self::createClassMetadata(name: 'App\\A'),
            'App\\B' => self::createClassMetadata(name: 'App\\B', parent: 'App\\A'),
        ]);

        self::assertSame([], $detector->check($result));
    }

    private static function mutualDependency(): DiscoveryResult
    {
        return new DiscoveryResult([
            'App\\A' => self::createClassMetadata(
                name: 'App\\A',
                methods: ['__construct' => self::createMethodMetadata(name: '__construct', parameters: ['b' => 'App\\B'])],
            ),
            'App\\B' => self::createClassMetadata(
                name: 'App\\B',
                methods: ['__construct' => self::createMethodMetadata(name: '__construct', parameters: ['a' => 'App\\A'])],
            ),
        ]);
    }
}
