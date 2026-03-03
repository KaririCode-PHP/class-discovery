<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Integration;

use KaririCode\ClassDiscovery\Analyzer\{CircularDetector, DependencyAnalyzer};
use KaririCode\ClassDiscovery\Contract\{AttributeScanner, CacheStrategy, Scanner};
use KaririCode\ClassDiscovery\Scanner\{
    AttributeScanner as AttributeScannerImpl,
    ComposerNamespaceResolver,
    ReflectionScanner,
};

/**
 * PSR-11 container service definitions for class discovery.
 *
 * Provides factory methods that can be registered in any PSR-11
 * compatible container (KaririCode\DI, PHP-DI, etc.).
 *
 * ## Registration Example
 *
 * ```php
 * $container->singleton(Scanner::class, fn () => PSR11Integration::createDefaultScanner());
 * $container->singleton(AttributeScanner::class, fn () => PSR11Integration::createAttributeScanner());
 * $container->singleton(DependencyAnalyzer::class, fn () => new DependencyAnalyzer());
 * ```
 */
final readonly class PSR11Integration
{
    public static function createDefaultScanner(?CacheStrategy $cache = null): Scanner
    {
        $resolver = new ComposerNamespaceResolver();
        $scanner = new AttributeScannerImpl($resolver);

        if ($cache !== null) {
            $scanner->setCacheStrategy($cache);
        }

        return $scanner;
    }

    public static function createAttributeScanner(?CacheStrategy $cache = null): AttributeScanner
    {
        $resolver = new ComposerNamespaceResolver();
        $scanner = new AttributeScannerImpl($resolver);

        if ($cache !== null) {
            $scanner->setCacheStrategy($cache);
        }

        return $scanner;
    }

    public static function createReflectionScanner(): ReflectionScanner
    {
        return new ReflectionScanner(new ComposerNamespaceResolver());
    }

    public static function createCircularDetector(bool $throwOnDetection = false): CircularDetector
    {
        return new CircularDetector(
            new DependencyAnalyzer(),
            $throwOnDetection,
        );
    }
}
