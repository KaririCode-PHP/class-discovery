<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Provider;

use KaririCode\ClassDiscovery\Analyzer\CircularDetector;
use KaririCode\ClassDiscovery\Analyzer\DependencyAnalyzer;
use KaririCode\ClassDiscovery\Contract\AttributeScanner;
use KaririCode\ClassDiscovery\Contract\Scanner;
use KaririCode\ClassDiscovery\Exception\InvalidConfigurationException;
use KaririCode\ClassDiscovery\Provider\ClassDiscoveryServiceProvider;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;
use KaririCode\ClassDiscovery\Tests\Double\InMemoryContainer;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use PHPUnit\Framework\TestCase;

final class ClassDiscoveryServiceProviderTest extends TestCase
{
    use FixturePaths;

    private InMemoryContainer $container;

    protected function setUp(): void
    {
        $this->container = new InMemoryContainer();
        $provider = new ClassDiscoveryServiceProvider([
            'cache' => ['enabled' => false],
        ]);
        $provider->register($this->container);
        $provider->boot();
    }

    public function testRegisterBindsWithoutBuilding(): void
    {
        $container = new InMemoryContainer();
        // A wrong option type proves laziness: register() succeeds, the first get() fails.
        new ClassDiscoveryServiceProvider(['max_files' => 'many'])->register($container);

        self::assertSame(0, $container->builds);
        self::assertTrue($container->has(AttributeScanner::class));

        $this->expectException(InvalidConfigurationException::class);
        $container->get(AttributeScanner::class);
    }

    public function testScannerIdsShareOneSingleton(): void
    {
        $scanner = $this->container->get(AttributeScanner::class);

        self::assertInstanceOf(AttributeScanner::class, $scanner);
        self::assertSame($scanner, $this->container->get(AttributeScanner::class));
        self::assertSame($scanner, $this->container->get(Scanner::class));
    }

    public function testResolvedScannerFindsClassesByFqcn(): void
    {
        $scanner = $this->container->get(AttributeScanner::class);
        self::assertInstanceOf(AttributeScanner::class, $scanner);

        self::assertSame(
            [self::fixtureClass('AttributedClass')],
            $scanner->discover(self::fixtureClass('SampleAttribute'), [self::fixturePath()]),
        );
    }

    public function testReflectionScannerUsesTheConfiguredResolver(): void
    {
        $scanner = $this->container->get(ReflectionScanner::class);
        self::assertInstanceOf(ReflectionScanner::class, $scanner);

        $meta = $scanner->scan([self::fixturePath('SampleClass.php')])->getClass(self::fixtureClass('SampleClass'));
        self::assertSame(self::fixtureClass('SampleAbstractClass'), $meta?->parent);
    }

    public function testDetectorIsBuiltOverTheContainersAnalyzer(): void
    {
        $detector = $this->container->get(CircularDetector::class);

        self::assertInstanceOf(CircularDetector::class, $detector);
        self::assertInstanceOf(DependencyAnalyzer::class, $this->container->get(DependencyAnalyzer::class));
        self::assertSame($detector, $this->container->get(CircularDetector::class));
    }
}
