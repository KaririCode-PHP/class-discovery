<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Analyzer;

/**
 * One circular dependency: the path from a class back to itself, the first
 * class repeated at the end (`[A, B, A]`; a self-dependency is `[A, A]`).
 *
 * ```php
 * foreach ($detector->check($result) as $cycle) {
 *     echo $cycle->chain(); // App\A → App\B → App\A
 * }
 * ```
 */
final readonly class DependencyCycle
{
    /**
     * @param list<string> $path
     */
    public function __construct(
        public array $path,
    ) {}

    public function chain(): string
    {
        return implode(' → ', $this->path);
    }
}
