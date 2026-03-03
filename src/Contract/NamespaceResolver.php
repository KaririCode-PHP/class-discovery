<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

/**
 * Resolves fully qualified class names from filesystem paths using PSR-4 rules.
 *
 * Implementations typically parse composer.json autoload sections
 * to build the namespace-to-directory mapping.
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
interface NamespaceResolver
{
    /**
     * Resolve FQCN from a file path.
     *
     * @param string $filePath Absolute path to PHP file
     *
     * @return string|null FQCN or null if unresolvable
     */
    public function resolve(string $filePath): ?string;

    /**
     * Register a PSR-4 namespace prefix to directory mapping.
     *
     * @param string $prefix    Namespace prefix (e.g., 'App\\')
     * @param string $directory Base directory for the prefix
     *
     * @return self For method chaining
     */
    public function addMapping(string $prefix, string $directory): self;
}
