<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Combines multiple filters with configurable logic (AND/OR).
 *
 * Use static factories: CompositeFilter::any(...) for OR-logic,
 * CompositeFilter::all(...) for AND-logic.
 */
final class CompositeFilter implements ClassFilter
{
    /** @var list<ClassFilter> */
    private readonly array $filters;

    private readonly bool $requireAll;

    /**
     * @param list<ClassFilter> $filters
     */
    private function __construct(array $filters, bool $requireAll)
    {
        $this->filters = $filters;
        $this->requireAll = $requireAll;
    }

    /** Returns a filter that passes when ANY of the given filters pass (OR logic). */
    public static function any(ClassFilter ...$filters): self
    {
        return new self(array_values($filters), false);
    }

    /** Returns a filter that passes when ALL of the given filters pass (AND logic). */
    public static function all(ClassFilter ...$filters): self
    {
        return new self(array_values($filters), true);
    }

    #[\Override]
    public function accept(ClassMetadata $metadata): bool
    {
        if ($this->filters === []) {
            return true;
        }

        foreach ($this->filters as $filter) {
            $passes = $filter->accept($metadata);

            if ($this->requireAll && ! $passes) {
                return false;
            }

            if (! $this->requireAll && $passes) {
                return true;
            }
        }

        return $this->requireAll;
    }
}
