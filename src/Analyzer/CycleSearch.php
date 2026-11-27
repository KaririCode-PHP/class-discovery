<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

/**
 * One depth-first search for cycles over a dependency graph.
 *
 * Holds the traversal state of a single run, so DependencyAnalyzer stays a
 * stateless readonly service: an edge back to a node on the current path
 * closes a cycle, reported as that path plus the repeated closing node.
 *
 * @internal Used by DependencyAnalyzer; not part of the public API.
 */
final class CycleSearch
{
    /** @var array<string, true> */
    private array $visited = [];

    /** @var list<DependencyCycle> */
    private array $cycles = [];

    /**
     * @param array<string, list<string>> $graph
     */
    public function __construct(
        private readonly array $graph,
    ) {}

    /**
     * @return list<DependencyCycle>
     */
    public function cycles(): array
    {
        foreach (array_keys($this->graph) as $node) {
            if (!isset($this->visited[$node])) {
                $this->visit($node, []);
            }
        }

        return $this->cycles;
    }

    /**
     * @param list<string> $path
     */
    private function visit(string $node, array $path): void
    {
        $this->visited[$node] = true;
        $path[] = $node;

        foreach ($this->graph[$node] ?? [] as $neighbor) {
            $onPath = array_search($neighbor, $path, true);

            if ($onPath !== false) {
                $this->cycles[] = new DependencyCycle([...array_slice($path, $onPath), $neighbor]);
            } elseif (!isset($this->visited[$neighbor]) && isset($this->graph[$neighbor])) {
                $this->visit($neighbor, $path);
            }
        }
    }
}
