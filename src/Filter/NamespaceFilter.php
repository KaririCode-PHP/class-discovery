<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

final readonly class NamespaceFilter implements ClassFilter
{
    public function __construct(
        private string $namespacePrefix,
    ) {
    }

    #[\Override]
    public function accept(ClassMetadata $metadata): bool
    {
        return str_starts_with($metadata->namespace, $this->namespacePrefix);
    }
}
