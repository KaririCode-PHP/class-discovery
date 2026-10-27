<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Analyzes class dependencies from discovery results.
 *
 * Builds a dependency graph from parents, interfaces, traits and constructor
 * parameter types, then detects circular dependencies by depth-first search.
 * Declared types are decomposed: unions, intersections and DNF groups
 * contribute every class they name; built-ins and the nullable marker do not.
 *
 * ```php
 * $analyzer = new DependencyAnalyzer();
 * foreach ($analyzer->detectCircularDependencies($analyzer->buildGraph($result)) as $cycle) {
 *     echo $cycle->chain(); // App\A → App\B → App\A
 * }
 * ```
 */
final readonly class DependencyAnalyzer
{
    private const array BUILTIN_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable',
        'void', 'never', 'null', 'mixed', 'true', 'false', 'self', 'static', 'parent',
    ];

    /**
     */
    public function buildGraph(DiscoveryResult $result): DependencyGraph
    {
        $graph = [];
        foreach ($result->getClasses() as $fqcn => $metadata) {
            $graph[$fqcn] = $this->dependenciesOf($metadata);
        }

        return new DependencyGraph($graph);
    }

    /**
     * @return list<DependencyCycle>
     */
    public function detectCircularDependencies(DependencyGraph $graph): array
    {
        return new CycleSearch($graph->edges)->cycles();
    }

    /**
     * @return list<string>
     */
    private function dependenciesOf(ClassMetadata $metadata): array
    {
        $names = [...($metadata->parent === null ? [] : [$metadata->parent]), ...$metadata->interfaces, ...$metadata->traits];

        $constructor = $metadata->methods['__construct'] ?? null;
        foreach ($constructor->parameters ?? [] as $type) {
            $names = [...$names, ...$this->classNamesIn($type)];
        }

        return array_values(array_unique(array_map(
            static fn(string $name): string => ltrim($name, '\\'),
            $names,
        )));
    }

    /**
     * Class names referenced by a declared type such as `?Foo`, `A|B`, `A&B` or `(A&B)|null`.
     *
     * @return list<string>
     */
    private function classNamesIn(string $type): array
    {
        $parts = preg_split('/[|&()?\s]+/', $type, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $parts === false ? [] : $parts,
            static fn(string $part): bool => !in_array(strtolower($part), self::BUILTIN_TYPES, true),
        ));
    }
}
