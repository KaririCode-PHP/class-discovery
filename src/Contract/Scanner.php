<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

/**
 * Core abstraction for class discovery scanners.
 *
 * Defines the scanning contract used by all discovery strategies.
 * Implementations select between token-based, reflection-based,
 * or composer-based scanning depending on use case.
 *
 * ## Strategy Selection
 *
 * | Strategy       | Use Case                  | Performance (1k classes) |
 * |----------------|---------------------------|--------------------------|
 * | AttributeScanner | PHP 8+ attribute discovery | 50-100ms cold, <5ms warm |
 * | FileScanner      | Token-based class listing  | 30-80ms cold, <3ms warm  |
 * | DirectoryScanner | Recursive filesystem scan  | 200-500ms (I/O bound)    |
 * | ComposerScanner  | Autoload map-based         | <10ms (pre-indexed)      |
 *
 * Ref: Gamma et al., "Design Patterns" (1994) — Strategy Pattern §5.9
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
interface Scanner
{
    /**
     * Scan paths for discoverable classes.
     *
     * Paths can be absolute, relative (resolved from CWD), or glob patterns.
     * All active filters are applied (AND logic). Results are immutable.
     *
     * @param array<string> $paths Directories to scan
     *
     * @return DiscoveryResult Immutable scan results
     *
     * @throws \KaririCode\ClassDiscovery\Exception\DiscoveryException On critical scanning error
     */
    public function scan(array $paths): DiscoveryResult;

    /**
     * Add filter to scanner pipeline.
     *
     * Filters are applied in insertion order (AND logic).
     * Use CompositeFilter for OR logic.
     *
     * @param ClassFilter $filter Filter to apply
     *
     * @return self For method chaining
     */
    public function addFilter(ClassFilter $filter): self;

    /**
     * Set cache strategy for scan results.
     *
     * Cache key is derived from hash of (paths + filters + scanner config).
     * Default TTL: 3600s. Invalidation: file change detection in dev mode.
     *
     * @param CacheStrategy $cache Cache implementation
     *
     * @return self For method chaining
     */
    public function setCacheStrategy(CacheStrategy $cache): self;

    /**
     * Clear all caches for this scanner instance.
     */
    public function clearCache(): void;
}
