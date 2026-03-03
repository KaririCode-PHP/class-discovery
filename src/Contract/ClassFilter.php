<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Filter predicate for class discovery results.
 *
 * Applied during scanning to exclude non-matching classes before
 * result construction. Multiple filters compose with AND logic.
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
interface ClassFilter
{
    /**
     * Determine whether a class should be included in results.
     *
     * @param ClassMetadata $metadata Class metadata to evaluate
     *
     * @return bool True if class passes the filter
     */
    public function accept(ClassMetadata $metadata): bool;
}
