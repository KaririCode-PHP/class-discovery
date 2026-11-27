<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Immutable container for class discovery scan results.
 *
 * All operations return new instances (ARFA 1.3 P1: Immutable State Transformation).
 * Supports iteration, counting, and filtering without mutation.
 *
 *
 * ```php
 * foreach ($result as $fqcn => $metadata) {
 *     // ...
 * }
 * $result->hasErrors(); // files that could not be read or reflected, keyed by path or FQCN
 * ```
 *
 * @extends \IteratorAggregate<string, ClassMetadata>
 */
interface DiscoveryResult extends \Countable, \IteratorAggregate
{
    /**
     * All discovered class metadata entries.
     *
     * @return array<string, ClassMetadata> Keyed by FQCN
     */
    public function getClasses(): array;

    /**
     * Retrieve metadata for a specific class.
     *
     * @param string $className FQCN
     *
     * @return ClassMetadata|null Metadata or null if not found
     */
    public function getClass(string $className): ?ClassMetadata;

    /**
     * Whether a specific class was discovered.
     *
     * @param string $className FQCN
     *
     * @return bool True if class exists in results
     */
    public function hasClass(string $className): bool;

    /**
     * Filter results, returning a new DiscoveryResult.
     *
     * @param ClassFilter $filter Filter predicate
     *
     * @return self New result containing only matching classes
     */
    public function filter(ClassFilter $filter): self;

    /**
     * Merge with another result, returning a new instance.
     *
     * On FQCN collision, the other result's metadata takes precedence.
     *
     * @param self $other Result to merge
     *
     * @return self New merged result
     */
    public function merge(self $other): self;

    /**
     * Whether the scan encountered non-fatal errors.
     *
     * @return bool True if errors were collected
     */
    public function hasErrors(): bool;

    /**
     * Non-fatal errors encountered during scanning.
     *
     * @return array<string, string> Keyed by file path, value is error message
     */
    public function getErrors(): array;

    /**
     * Wall-clock scan duration in seconds (float precision).
     *
     * Returns 0.0 for cached or synthetic results.
     */
    public function getScanDuration(): float;
}
