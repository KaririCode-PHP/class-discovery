<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

use KaririCode\ClassDiscovery\Contract\DiscoveryResult;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;

/**
 * Specialized circular dependency detector with reporting.
 *
 * Wraps DependencyAnalyzer with formatted output and optional
 * exception throwing on detection.
 */
final readonly class CircularDetector
{
    public function __construct(
        private DependencyAnalyzer $analyzer,
        private bool $throwOnDetection = false,
    ) {
    }

    /**
     * Check for circular dependencies and optionally throw.
     *
     * @return array<array<string>> Detected cycles
     *
     * @throws DiscoveryException When throwOnDetection is true and cycles found
     */
    public function check(DiscoveryResult $result): array
    {
        $graph = $this->analyzer->buildGraph($result);
        $cycles = $this->analyzer->detectCircularDependencies($graph);

        if ($cycles !== [] && $this->throwOnDetection) {
            $formatted = array_map(
                static fn (array $cycle): string => implode(' → ', $cycle),
                $cycles,
            );

            throw DiscoveryException::circularDependency(implode('; ', $formatted));
        }

        return $cycles;
    }
}
