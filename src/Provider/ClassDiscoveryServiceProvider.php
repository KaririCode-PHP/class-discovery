<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Provider;

use KaririCode\ClassDiscovery\Analyzer\CircularDetector;
use KaririCode\ClassDiscovery\Analyzer\DependencyAnalyzer;
use KaririCode\ClassDiscovery\Contract\AttributeScanner;
use KaririCode\ClassDiscovery\Contract\Scanner;
use KaririCode\ClassDiscovery\Integration\ConfiguratorBridge;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;
use KaririCode\Contract\DI\BindableContainer;
use KaririCode\Contract\DI\ServiceProvider;

/**
 * Registers class discovery in any container that implements the ecosystem
 * contract (Spec V4.3.3 §31) — the kernel, the DI component or a test double.
 *
 * `register()` only binds factories; nothing is built until first resolved,
 * so every provider can register before any boots. Options are the ones of
 * ConfiguratorBridge (SPEC-001 §9) and are validated when the scanner is
 * first built (8740 on a wrong type).
 *
 * | Id                   | Instance                                  |
 * |----------------------|-------------------------------------------|
 * | AttributeScanner     | token-mode scanner, cached per the options |
 * | Scanner              | the same AttributeScanner instance          |
 * | ReflectionScanner    | reflection mode — autoloads, trusted code only |
 * | DependencyAnalyzer   | stateless graph builder                    |
 * | CircularDetector     | non-throwing detector over that analyzer   |
 *
 * ```php
 * $provider = new ClassDiscoveryServiceProvider(['max_files' => 5000]);
 * $provider->register($container);
 * $provider->boot();
 *
 * $container->get(AttributeScanner::class)->discover(Route::class, ['src/']);
 * ```
 */
final readonly class ClassDiscoveryServiceProvider implements ServiceProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function register(BindableContainer $container): void
    {
        $config = $this->config;

        $container->bind(AttributeScanner::class, static fn(): AttributeScanner => ConfiguratorBridge::createScanner($config), singleton: true);
        $container->bind(Scanner::class, static fn(BindableContainer $c): mixed => $c->get(AttributeScanner::class), singleton: true);
        $container->bind(ReflectionScanner::class, static fn(): ReflectionScanner => new ReflectionScanner(), singleton: true);
        $container->bind(DependencyAnalyzer::class, null, singleton: true);
        $container->bind(CircularDetector::class, static fn(BindableContainer $c): CircularDetector => new CircularDetector(
            self::analyzer($c),
        ), singleton: true);
    }

    public function boot(): void {}

    private static function analyzer(BindableContainer $container): DependencyAnalyzer
    {
        return self::analyzerOrDefault($container->get(DependencyAnalyzer::class));
    }

    private static function analyzerOrDefault(mixed $service): DependencyAnalyzer
    {
        return $service instanceof DependencyAnalyzer ? $service : new DependencyAnalyzer();
    }
}
