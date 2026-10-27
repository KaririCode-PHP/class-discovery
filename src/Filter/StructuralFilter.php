<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Filters classes by structural characteristics (final, abstract, readonly, etc.).
 *
 * ```php
 * $scanner->addFilter(new StructuralFilter(isFinal: true, isAbstract: false)); // null flags impose nothing
 * ```
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
    ) {}

    public function accept(ClassMetadata $metadata): bool
    {
        return array_all(
            $this->constraints($metadata),
            static fn(array $constraint): bool => $constraint[0] === null || $constraint[0] === $constraint[1],
        );
    }

    /**
     * Each configured flag paired with the class's actual value; a null flag imposes nothing.
     *
     * @return list<array{0: ?bool, 1: bool}>
     */
    private function constraints(ClassMetadata $metadata): array
    {
        return [
            [$this->isFinal, $metadata->isFinal],
            [$this->isAbstract, $metadata->isAbstract],
            [$this->isReadonly, $metadata->isReadonly],
            [$this->isInterface, $metadata->isInterface],
            [$this->isEnum, $metadata->isEnum],
            [$this->isTrait, $metadata->isTrait],
        ];
    }
}
