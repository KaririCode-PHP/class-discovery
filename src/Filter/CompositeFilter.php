<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Combines multiple filters with configurable logic (AND/OR).
 *
 * Default: OR logic (any filter passes). When $requireAll is true: AND logic.
 *
 * ```php
 * $scanner->addFilter(new CompositeFilter(
 *     true, // all must accept
 *     new NamespaceFilter('App\Http'),
 *     new StructuralFilter(isFinal: true),
 * ));
 * ```
 */
final readonly class CompositeFilter implements ClassFilter
{
    /** @var array<ClassFilter> */
    private array $filters;

    public function __construct(
        private bool $requireAll = false,
        ClassFilter ...$filters,
    ) {
        $this->filters = $filters;
    }

    public function accept(ClassMetadata $metadata): bool
    {
        if ($this->filters === []) {
            return true;
        }

        foreach ($this->filters as $filter) {
            $passes = $filter->accept($metadata);

            if ($this->requireAll && !$passes) {
                return false;
            }

            if (!$this->requireAll && $passes) {
                return true;
            }
        }

        return $this->requireAll;
    }
}
