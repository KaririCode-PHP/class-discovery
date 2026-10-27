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
 * ```php
 * final readonly class OnlyControllers implements ClassFilter
 * {
 *     public function accept(ClassMetadata $metadata): bool
 *     {
 *         return str_ends_with($metadata->shortName, 'Controller');
 *     }
 * }
 * ```
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
