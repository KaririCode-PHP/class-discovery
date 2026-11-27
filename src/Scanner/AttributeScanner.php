<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\AttributeScanner as AttributeScannerContract;
use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Filter\AttributeFilter;

/**
 * PHP 8+ attribute-specialized scanner.
 *
 * Composes FileScanner (ADR-005). Filters registered with addFilter() persist
 * across calls; the attribute filter of scanForAttribute()/scanForAttributes()
 * applies to that call only — each call builds a fresh engine, so a previous
 * call's attribute never narrows the next one.
 */
final class AttributeScanner implements AttributeScannerContract
{
    /** @var list<ClassFilter> */
    private array $filters = [];
    private ?CacheStrategy $cache = null;

    public function __construct(
        private readonly int $maxFileSize = 10 * 1024 * 1024,
        private readonly int $maxFiles = 10_000,
    ) {}

    public function scan(array $paths): DiscoveryResultContract
    {
        return $this->scanWith($paths);
    }

    public function scanForAttribute(string $attributeClass, array $paths): DiscoveryResultContract
    {
        return $this->scanWith($paths, new AttributeFilter($attributeClass));
    }

    public function scanForAttributes(array $attributeClasses, array $paths): DiscoveryResultContract
    {
        return $this->scanWith($paths, new AttributeFilter(...$attributeClasses));
    }

    /**
     * Class finder shaped for typed-closure integration: KC Observability takes
     * `'discovery' => $scanner->discover(...)` and calls it with FQCN attributes
     * such as `Traced::class`. Never loads the scanned code.
     *
     * ```php
     * $finder = $scanner->discover(...);
     * $finder(Route::class, ['src/']); // ['App\Http\HomeController', ...]
     * ```
     */
    public function discover(string $attributeClass, array $paths): array
    {
        return array_keys($this->scanForAttribute($attributeClass, $paths)->getClasses());
    }

    public function addFilter(ClassFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    public function clearCache(): void
    {
        $this->cache?->clear();
    }

    /**
     * @param array<string> $paths
     */
    private function scanWith(array $paths, ClassFilter ...$callFilters): DiscoveryResultContract
    {
        $engine = new FileScanner(
            maxFileSize: $this->maxFileSize,
            maxFiles: $this->maxFiles,
        );

        foreach ([...$this->filters, ...$callFilters] as $filter) {
            $engine->addFilter($filter);
        }

        if ($this->cache !== null) {
            $engine->setCacheStrategy($this->cache);
        }

        return $engine->scan($paths);
    }
}
