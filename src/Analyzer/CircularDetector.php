<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Exception\CircularDependencyException;

/**
 * Specialized circular dependency detector with reporting.
 *
 * Wraps DependencyAnalyzer with formatted output and optional
 * exception throwing on detection.
 *
 * ```php
 * $detector = new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true);
 * $detector->check($result); // CircularDependencyException (8730) when a cycle exists
 * ```
 */
final readonly class CircularDetector
{
    public function __construct(
        private DependencyAnalyzer $analyzer,
        private bool $throwOnDetection = false,
    ) {}

    /**
     * Check for circular dependencies and optionally throw.
     *
     *
     * @throws CircularDependencyException When configured to throw and a cycle exists
     */
    /**
     * @return list<DependencyCycle>
     */
    public function check(DiscoveryResult $result): array
    {
        $graph = $this->analyzer->buildGraph($result);
        $cycles = $this->analyzer->detectCircularDependencies($graph);

        if ($cycles !== [] && $this->throwOnDetection) {
            throw CircularDependencyException::chain(implode('; ', array_map(
                static fn(DependencyCycle $cycle): string => $cycle->chain(),
                $cycles,
            )));
        }

        return $cycles;
    }
}
