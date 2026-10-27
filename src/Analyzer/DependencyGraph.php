<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

/**
 * Who depends on whom among discovered classes: each class mapped to the names
 * it extends, implements, uses as traits or receives in its constructor.
 * Edges may point at classes outside the scan (vendor types); only edges
 * between nodes of the graph can close a cycle.
 *
 * ```php
 * $graph = new DependencyAnalyzer()->buildGraph($result);
 * $graph->dependenciesOf(OrderService::class); // ['App\Repository\OrderRepository', ...]
 * ```
 */
final readonly class DependencyGraph
{
    /**
     * @param array<string, list<string>> $edges FQCN => names it depends on
     */
    public function __construct(
        public array $edges,
    ) {}

    /**
     * @return list<string> Empty for a class outside the graph
     */
    public function dependenciesOf(string $class): array
    {
        return $this->edges[$class] ?? [];
    }

    public function contains(string $class): bool
    {
        return isset($this->edges[$class]);
    }
}
