<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

final readonly class InterfaceFilter implements ClassFilter
{
    public function __construct(
        private string $interfaceName,
    ) {
    }

    #[\Override]
    public function accept(ClassMetadata $metadata): bool
    {
        return $metadata->implementsInterface($this->interfaceName);
    }
}
