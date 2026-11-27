<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Analyzer;

use KaririCode\ClassDiscovery\Analyzer\DependencyAnalyzer;
use KaririCode\ClassDiscovery\Analyzer\DependencyCycle;
use KaririCode\ClassDiscovery\Analyzer\DependencyGraph;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
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

    public function testParentInterfacesAndTraitsAreDependencies(): void
    {
        $graph = $this->graphOf(self::createClassMetadata(
            name: 'App\\Service',
            interfaces: ['App\\Contract\\Handler', 'Countable'],
            parent: 'App\\Base',
            traits: ['App\\Concern\\Loggable'],
        ));

        self::assertSame(['App\\Base', 'App\\Contract\\Handler', 'Countable', 'App\\Concern\\Loggable'], $graph->dependenciesOf('App\\Service'));
    }

    public function testConstructorParameterTypesAreDependenciesExceptBuiltins(): void
    {
        $graph = $this->graphOf($this->withConstructor('App\\UserService', [
            'repo' => 'App\\Repository\\UserRepo',
            'name' => 'string',
            'retries' => 'int',
        ]));

        self::assertSame(['App\\Repository\\UserRepo'], $graph->dependenciesOf('App\\UserService'));
    }

    public function testAllBuiltinTypesAreIgnoredCaseInsensitively(): void
    {
        $builtins = ['int', 'float', 'string', 'bool', 'array', 'callable', 'mixed', '?string', 'iterable',
            'object', 'null', 'true', 'false', 'self', 'static', 'parent', 'void', 'never', 'Int', 'STRING'];
        $parameters = [];
        foreach ($builtins as $index => $type) {
            $parameters["p{$index}"] = $type;
        }

        self::assertSame([], $this->graphOf($this->withConstructor('App\\Service', $parameters))->dependenciesOf('App\\Service'));
    }

    public function testUnionIntersectionAndDnfTypesContributeEveryClass(): void
    {
        $graph = $this->graphOf($this->withConstructor('App\\Service', [
            'logger' => 'App\\Log\\Logger|App\\Log\\NullLogger',
            'store' => '(App\\Store\\Readable&App\\Store\\Writable)|null',
        ]));

        self::assertSame(
            ['App\\Log\\Logger', 'App\\Log\\NullLogger', 'App\\Store\\Readable', 'App\\Store\\Writable'],
            $graph->dependenciesOf('App\\Service'),
        );
    }

    public function testNullableAndFullyQualifiedNamesAreNormalised(): void
    {
        $graph = $this->graphOf(self::createClassMetadata(
            name: 'App\\Service',
            interfaces: ['\\Countable'],
            methods: ['__construct' => self::createMethodMetadata(name: '__construct', parameters: ['clock' => '?\\App\\Clock'])],
        ));

        self::assertSame(['Countable', 'App\\Clock'], $graph->dependenciesOf('App\\Service'));
    }

    public function testDependenciesAreDeduplicatedAsAList(): void
    {
        $graph = $this->graphOf(self::createClassMetadata(name: 'App\\Service', interfaces: ['Countable', 'Countable']));

        self::assertSame(['Countable'], $graph->dependenciesOf('App\\Service'));
    }

    public function testEmptyResultBuildsEmptyGraph(): void
    {
        self::assertSame([], $this->analyzer->buildGraph(new DiscoveryResult())->edges);
    }

    public function testDetectsThreeNodeCycle(): void
    {
        $cycles = $this->cyclePaths(['A' => ['B'], 'B' => ['C'], 'C' => ['A']]);

        self::assertSame([['A', 'B', 'C', 'A']], $cycles);
    }

    public function testDetectsSelfDependency(): void
    {
        self::assertSame([['A', 'A']], $this->cyclePaths(['A' => ['A']]));
    }

    public function testAcyclicGraphHasNoCycles(): void
    {
        self::assertSame([], $this->cyclePaths(['A' => ['B'], 'B' => ['C'], 'C' => []]));
    }

    public function testCycleThroughNullableDependencyIsDetected(): void
    {
        // Before type decomposition, '?App\B' was a node of its own and this cycle was invisible.
        $graph = $this->analyzer->buildGraph(new DiscoveryResult([
            'App\\A' => $this->withConstructor('App\\A', ['b' => '?App\\B']),
            'App\\B' => $this->withConstructor('App\\B', ['a' => 'App\\A']),
        ]));

        self::assertSame([['App\\A', 'App\\B', 'App\\A']], $this->cyclePaths($graph));
    }

    /**
     * @return DependencyGraph
     */
    private function graphOf(ClassMetadata $metadata): DependencyGraph
    {
        return $this->analyzer->buildGraph(new DiscoveryResult([$metadata->name => $metadata]));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function withConstructor(string $fqcn, array $parameters): ClassMetadata
    {
        return self::createClassMetadata(
            name: $fqcn,
            methods: ['__construct' => self::createMethodMetadata(name: '__construct', parameters: $parameters)],
        );
    }

    public function testGraphAnswersForClassesInsideAndOutsideIt(): void
    {
        $graph = new DependencyGraph(['App\\A' => ['App\\B']]);

        self::assertSame(['App\\B'], $graph->dependenciesOf('App\\A'));
        self::assertSame([], $graph->dependenciesOf('App\\Unknown'));
        self::assertTrue($graph->contains('App\\A'));
        self::assertFalse($graph->contains('App\\B'), 'a dependency is not a node until it is discovered');
    }

    public function testCycleRendersAsAChain(): void
    {
        self::assertSame('App\\A → App\\B → App\\A', new DependencyCycle(['App\\A', 'App\\B', 'App\\A'])->chain());
    }

    /**
     * @param DependencyGraph|array<string, list<string>> $graph
     *
     * @return list<list<string>>
     */
    private function cyclePaths(DependencyGraph|array $graph): array
    {
        return array_map(
            static fn(DependencyCycle $cycle): array => $cycle->path,
            $this->analyzer->detectCircularDependencies(is_array($graph) ? new DependencyGraph($graph) : $graph),
        );
    }
}
