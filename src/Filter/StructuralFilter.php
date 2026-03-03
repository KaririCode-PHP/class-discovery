<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Filters classes by structural characteristics (final, abstract, readonly, etc.).
 */
final readonly class StructuralFilter implements ClassFilter
{
    public function __construct(
        private ?bool $isFinal = null,
        private ?bool $isAbstract = null,
        private ?bool $isReadonly = null,
        private ?bool $isInterface = null,
        private ?bool $isEnum = null,
        private ?bool $isTrait = null,
    ) {
    }

    #[\Override]
    public function accept(ClassMetadata $metadata): bool
    {
        if ($this->isFinal !== null && $metadata->isFinal !== $this->isFinal) {
            return false;
        }
        if ($this->isAbstract !== null && $metadata->isAbstract !== $this->isAbstract) {
            return false;
        }
        if ($this->isReadonly !== null && $metadata->isReadonly !== $this->isReadonly) {
            return false;
        }
        if ($this->isInterface !== null && $metadata->isInterface !== $this->isInterface) {
            return false;
        }
        if ($this->isEnum !== null && $metadata->isEnum !== $this->isEnum) {
            return false;
        }
        if ($this->isTrait !== null && $metadata->isTrait !== $this->isTrait) {
            return false;
        }

        return true;
    }
}
