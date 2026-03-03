<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\{
    AttributeScanner as AttributeScannerContract,
    CacheStrategy,
    ClassFilter,
    DiscoveryResult as DiscoveryResultContract,
    NamespaceResolver,
};
use KaririCode\ClassDiscovery\Filter\AttributeFilter;

/**
 * PHP 8+ attribute-specialized scanner.
 *
 * Composes FileScanner with attribute-specific filtering:
 * targeted attribute matching and multi-attribute OR scanning.
 *
 * Uses composition (ADR-005) to avoid fragile base class issues
 * while reusing FileScanner's token-based parsing engine.
 *
 * @package KaririCode\ClassDiscovery\Scanner
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
final class AttributeScanner implements AttributeScannerContract
{
    private readonly FileScanner $fileScanner;
    private ?CacheStrategy $cache = null;

    public function __construct(
        private readonly NamespaceResolver $namespaceResolver,
        private readonly int $maxFileSize = 10 * 1024 * 1024, // 10MB
        private readonly int $maxFiles = 10_000,
    ) {
        $this->fileScanner = new FileScanner($namespaceResolver, $maxFileSize, $maxFiles);
    }

    #[\Override]
    public function scan(array $paths): DiscoveryResultContract
    {
        return $this->fileScanner->scan($paths);
    }

    #[\Override]
    public function scanForAttribute(string $attributeClass, array $paths): DiscoveryResultContract
    {
        // Fresh scanner to avoid filter accumulation between calls
        $scanner = new FileScanner($this->namespaceResolver, $this->maxFileSize, $this->maxFiles);
        if ($this->cache !== null) {
            $scanner->setCacheStrategy($this->cache);
        }
        $scanner->addFilter(new AttributeFilter($attributeClass));

        return $scanner->scan($paths);
    }

    #[\Override]
    public function scanForAttributes(array $attributeClasses, array $paths): DiscoveryResultContract
    {
        // Fresh scanner to avoid filter accumulation between calls
        $scanner = new FileScanner($this->namespaceResolver, $this->maxFileSize, $this->maxFiles);
        if ($this->cache !== null) {
            $scanner->setCacheStrategy($this->cache);
        }
        $scanner->addFilter(new AttributeFilter(...$attributeClasses));

        return $scanner->scan($paths);
    }

    #[\Override]
    public function addFilter(ClassFilter $filter): self
    {
        $this->fileScanner->addFilter($filter);

        return $this;
    }

    #[\Override]
    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;
        $this->fileScanner->setCacheStrategy($cache);

        return $this;
    }

    #[\Override]
    public function clearCache(): void
    {
        $this->fileScanner->clearCache();
    }
}
