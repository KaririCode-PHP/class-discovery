<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Analyzer;

use KaririCode\ClassDiscovery\Analyzer\{CircularDetector, DependencyAnalyzer};
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Result\{DiscoveryResult};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircularDetector::class)]
final class CircularDetectorTest extends TestCase
{
    use MetadataFactory;

    public function testCheckReturnsCyclesWithoutThrowing(): void
    {
        $constructor = self::createMethodMetadata(name: '__construct', parameters: ['App\\B']);
        $a = self::createClassMetadata(name: 'App\\A', methods: ['__construct' => $constructor]);

        $constructorB = self::createMethodMetadata(name: '__construct', parameters: ['App\\A']);
        $b = self::createClassMetadata(name: 'App\\B', methods: ['__construct' => $constructorB]);

        $result = new DiscoveryResult(['App\\A' => $a, 'App\\B' => $b]);

        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: false);
        $cycles = $detector->check($result);

        self::assertNotEmpty($cycles);
    }

    public function testCheckThrowsWhenConfigured(): void
    {
        $constructor = self::createMethodMetadata(name: '__construct', parameters: ['App\\B']);
        $a = self::createClassMetadata(name: 'App\\A', methods: ['__construct' => $constructor]);

        $constructorB = self::createMethodMetadata(name: '__construct', parameters: ['App\\A']);
        $b = self::createClassMetadata(name: 'App\\B', methods: ['__construct' => $constructorB]);

        $result = new DiscoveryResult(['App\\A' => $a, 'App\\B' => $b]);

        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(1010);

        $detector->check($result);
    }

    public function testCheckReturnsEmptyForAcyclicGraph(): void
    {
        $a = self::createClassMetadata(name: 'App\\A');
        $b = self::createClassMetadata(name: 'App\\B');

        $result = new DiscoveryResult(['App\\A' => $a, 'App\\B' => $b]);

        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);
        $cycles = $detector->check($result);

        self::assertEmpty($cycles);
    }

    public function testCheckDoesNotThrowForAcyclicGraph(): void
    {
        $result = new DiscoveryResult([
            'App\\A' => self::createClassMetadata(name: 'App\\A'),
        ]);

        $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);

        // Should not throw
        $cycles = $detector->check($result);
        self::assertEmpty($cycles);
    }
}
