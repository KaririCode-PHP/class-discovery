<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Result;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;

/**
 * Immutable container for class discovery scan results.
 *
 * All mutation operations return new instances. Thread-safe by design.
 * ARFA 1.3 P1: Immutable State Transformation.
 *
 * @package KaririCode\ClassDiscovery\Result
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final readonly class DiscoveryResult implements DiscoveryResultContract
{
    /**
     * @param array<string, ClassMetadata> $classes Keyed by FQCN
     * @param array<string, string>        $errors  Keyed by file path
     * @param float                        $scanDuration Scan time in seconds
     */
    public function __construct(
        private array $classes = [],
        private array $errors = [],
        public float $scanDuration = 0.0,
    ) {
    }

    #[\Override]
    public function getClasses(): array
    {
        return $this->classes;
    }

    #[\Override]
    public function getClass(string $className): ?ClassMetadata
    {
        return $this->classes[$className] ?? null;
    }

    #[\Override]
    public function hasClass(string $className): bool
    {
        return isset($this->classes[$className]);
    }

    #[\Override]
    public function filter(ClassFilter $filter): DiscoveryResultContract
    {
        $filtered = array_filter(
            $this->classes,
            static fn (ClassMetadata $metadata): bool => $filter->accept($metadata),
        );

        return new self($filtered, $this->errors, $this->scanDuration);
    }

    #[\Override]
    public function merge(DiscoveryResultContract $other): DiscoveryResultContract
    {
        return new self(
            array_merge($this->classes, $other->getClasses()),
            array_merge($this->errors, $other->getErrors()),
            $this->scanDuration + $other->getScanDuration(),
        );
    }

    #[\Override]
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    #[\Override]
    public function getErrors(): array
    {
        return $this->errors;
    }

    #[\Override]
    public function getScanDuration(): float
    {
        return $this->scanDuration;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->classes);
    }

    /**
     * @return \ArrayIterator<string, ClassMetadata>
     */
    #[\Override]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->classes);
    }
}
