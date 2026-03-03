<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Analyzer;

use KaririCode\ClassDiscovery\Analyzer\DependencyAnalyzer;
use KaririCode\ClassDiscovery\Result\{DiscoveryResult};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class DependencyAnalyzerTest extends TestCase
{
    use MetadataFactory;

    private DependencyAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new DependencyAnalyzer();
    }

    public function testBuildGraphWithParent(): void
    {
        $child = self::createClassMetadata(name: 'App\\Child', parent: 'App\\Parent');
        $parent = self::createClassMetadata(name: 'App\\Parent');

        $result = new DiscoveryResult([
            'App\\Child' => $child,
            'App\\Parent' => $parent,
        ]);

        $graph = $this->analyzer->buildGraph($result);

        self::assertArrayHasKey('App\\Child', $graph);
        self::assertContains('App\\Parent', $graph['App\\Child']);
    }

    public function testBuildGraphWithInterfaces(): void
    {
        $meta = self::createClassMetadata(
            name: 'App\\Service',
            interfaces: ['App\\Contract\\Handler', 'Countable'],
        );

        $result = new DiscoveryResult(['App\\Service' => $meta]);
        $graph = $this->analyzer->buildGraph($result);

        self::assertContains('App\\Contract\\Handler', $graph['App\\Service']);
        self::assertContains('Countable', $graph['App\\Service']);
    }

    public function testBuildGraphWithTraits(): void
    {
        $meta = self::createClassMetadata(
            name: 'App\\Service',
            traits: ['App\\Concern\\Loggable'],
        );

        $result = new DiscoveryResult(['App\\Service' => $meta]);
        $graph = $this->analyzer->buildGraph($result);

        self::assertContains('App\\Concern\\Loggable', $graph['App\\Service']);
    }

    public function testBuildGraphWithConstructorParameters(): void
    {
        $constructor = self::createMethodMetadata(
            name: '__construct',
            parameters: ['App\\Repository\\UserRepo', 'string', 'int'],
        );

        $meta = self::createClassMetadata(
            name: 'App\\Service\\UserService',
            methods: ['__construct' => $constructor],
        );

        $result = new DiscoveryResult(['App\\Service\\UserService' => $meta]);
        $graph = $this->analyzer->buildGraph($result);

        // string and int are builtins — filtered out
        self::assertContains('App\\Repository\\UserRepo', $graph['App\\Service\\UserService']);
        self::assertNotContains('string', $graph['App\\Service\\UserService']);
        self::assertNotContains('int', $graph['App\\Service\\UserService']);
    }

    public function testDetectCircularDependencies(): void
    {
        $graph = [
            'A' => ['B'],
            'B' => ['C'],
            'C' => ['A'], // Cycle: A → B → C → A
        ];

        $cycles = $this->analyzer->detectCircularDependencies($graph);

        self::assertNotEmpty($cycles);
        // The cycle should contain A, B, C
        $cycle = $cycles[0];
        self::assertContains('A', $cycle);
        self::assertContains('B', $cycle);
        self::assertContains('C', $cycle);
    }

    public function testNoCircularDependencies(): void
    {
        $graph = [
            'A' => ['B'],
            'B' => ['C'],
            'C' => [],
        ];

        $cycles = $this->analyzer->detectCircularDependencies($graph);

        self::assertEmpty($cycles);
    }

    public function testBuiltinTypesAreFiltered(): void
    {
        $constructor = self::createMethodMetadata(
            name: '__construct',
            parameters: ['int', 'float', 'string', 'bool', 'array', 'callable', 'mixed', '?string', 'void', 'never'],
        );

        $meta = self::createClassMetadata(
            name: 'App\\Service',
            methods: ['__construct' => $constructor],
        );

        $result = new DiscoveryResult(['App\\Service' => $meta]);
        $graph = $this->analyzer->buildGraph($result);

        self::assertEmpty($graph['App\\Service']);
    }

    public function testEmptyResult(): void
    {
        $result = new DiscoveryResult();
        $graph = $this->analyzer->buildGraph($result);

        self::assertEmpty($graph);
    }

    public function testDeduplicatesDependencies(): void
    {
        $meta = self::createClassMetadata(
            name: 'App\\Service',
            interfaces: ['Countable', 'Countable'], // Duplicated
        );

        $result = new DiscoveryResult(['App\\Service' => $meta]);
        $graph = $this->analyzer->buildGraph($result);

        $countable = array_filter(
            $graph['App\\Service'],
            static fn (string $dep): bool => $dep === 'Countable',
        );

        self::assertCount(1, $countable);
    }
}
