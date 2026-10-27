<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Keeps classes that declare the given interface — the discovery side of
 * "every implementation of X" (subscribers, handlers, plugins). Matches the
 * names the scanner records: FQCNs in both modes; an interface inherited
 * from a parent is visible only in reflection mode.
 *
 * ```php
 * $scanner->addFilter(new InterfaceFilter(EventSubscriber::class));
 * ```
 */
final readonly class InterfaceFilter implements ClassFilter
{
    public function __construct(
        private string $interfaceName,
    ) {}

    public function accept(ClassMetadata $metadata): bool
    {
        return $metadata->implementsInterface($this->interfaceName);
    }
}
