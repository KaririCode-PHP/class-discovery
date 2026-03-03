<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Filter;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;

/**
 * Filters classes by implemented interface.
 *
 * @package KaririCode\ClassDiscovery\Filter
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
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
