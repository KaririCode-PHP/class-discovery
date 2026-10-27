<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Keeps classes under a namespace prefix, sub-namespaces included — scoping a
 * scan of a shared tree to one bounded context.
 *
 * ```php
 * $scanner->addFilter(new NamespaceFilter('App\\Domain')); // App\Domain and below
 * ```
 */
final readonly class NamespaceFilter implements ClassFilter
{
    public function __construct(
        private string $namespacePrefix,
    ) {}

    public function accept(ClassMetadata $metadata): bool
    {
        return str_starts_with($metadata->namespace, $this->namespacePrefix);
    }
}
