<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

use KaririCode\ClassDiscovery\Contract\DiscoveryResult;

/**
 * Analyzes class dependencies from discovery results.
 *
 * Builds a dependency graph from constructor parameters, type hints,
 * and use statements. Detects circular dependencies.
 */
final readonly class DependencyAnalyzer
{
    /**
     * Build dependency graph from discovery result.
     *
     * @return array<string, array<string>> FQCN => list of dependency FQCNs
     */
    public function buildGraph(DiscoveryResult $result): array
    {
        $graph = [];

        foreach ($result->getClasses() as $fqcn => $metadata) {
            $dependencies = [];

            if ($metadata->parent !== null) {
                $dependencies[] = $metadata->parent;
            }

            foreach ($metadata->interfaces as $interface) {
                $dependencies[] = $interface;
            }

            foreach ($metadata->traits as $trait) {
                $dependencies[] = $trait;
            }

            // Constructor parameter types
            $constructor = $metadata->methods['__construct'] ?? null;
            if ($constructor !== null) {
                foreach ($constructor->parameters as $type) {
                    if ($type !== 'mixed' && ! $this->isBuiltinType($type)) {
                        $dependencies[] = $type;
                    }
                }
            }

            $graph[$fqcn] = array_unique($dependencies);
        }

        return $graph;
    }

    /**
     * Detect circular dependencies in the graph.
     *
     * @param array<string, array<string>> $graph
     *
     * @return array<array<string>> List of cycles (each cycle is a list of FQCNs)
     */
    public function detectCircularDependencies(array $graph): array
    {
        $visited = [];
        $inStack = [];
        $cycles = [];

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                $this->dfs($node, $graph, $visited, $inStack, [], $cycles);
            }
        }

        return $cycles;
    }

    /**
     * @param array<string, array<string>> $graph
     * @param array<string, bool>          $visited
     * @param array<string, bool>          $inStack
     * @param array<string>                $path
     * @param array<array<string>>         $cycles
     */
    private function dfs(
        string $node,
        array $graph,
        array &$visited,
        array &$inStack,
        array $path,
        array &$cycles,
    ): void {
        $visited[$node] = true;
        $inStack[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (! isset($visited[$neighbor]) && isset($graph[$neighbor])) {
                $this->dfs($neighbor, $graph, $visited, $inStack, $path, $cycles);
            } elseif (isset($inStack[$neighbor]) && $inStack[$neighbor]) {
                // Found a cycle
                $cycleStart = array_search($neighbor, $path, true);
                if ($cycleStart !== false) {
                    $cycle = \array_slice($path, (int) $cycleStart);
                    $cycle[] = $neighbor;
                    $cycles[] = $cycle;
                }
            }
        }

        $inStack[$node] = false;
    }

    private function isBuiltinType(string $type): bool
    {
        return \in_array(
            ltrim($type, '?'),
            ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'void', 'never', 'null', 'mixed', 'true', 'false', 'self', 'static', 'parent'],
            true,
        );
    }
}
