<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Contract;

/**
 * Scanner specialized in PHP 8+ attribute discovery.
 *
 * Token-based parsing (no class loading required) provides 10x performance
 * over ReflectionScanner for attribute-heavy codebases. Supports all five
 * attribute targets: CLASS, METHOD, PROPERTY, PARAMETER, CLASS_CONSTANT.
 *
 * ## Ecosystem Integration
 *
 * | Consumer Component    | Discovered Attributes                      |
 * |-----------------------|--------------------------------------------|
 * | KaririCode\Router     | #[Route], #[Middleware], #[Guard]           |
 * | KaririCode\Console    | #[Command], #[Argument], #[Option]          |
 * | KaririCode\DI         | #[Singleton], #[Scoped], #[Tagged]          |
 * | KaririCode\Event      | #[EventListener], #[EventSubscriber]        |
 * | KaririCode\WebSocket  | #[WebSocketRoute], #[OnConnect], #[OnMessage] |
 * | KaririCode\I18n       | #[TranslatableResource]                     |
 *
 * @package KaririCode\ClassDiscovery\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.1.0
 */
interface AttributeScanner extends Scanner
{
    /**
     * Scan for a specific attribute class (single target).
     *
     * More efficient than scan() + manual filtering: exits early
     * when attribute match is impossible (no #[...] tokens in file).
     *
     * @param class-string $attributeClass Attribute to find
     * @param array<string> $paths         Paths to scan
     *
     * @return DiscoveryResult Classes with the attribute
     */
    public function scanForAttribute(string $attributeClass, array $paths): DiscoveryResult;

    /**
     * Scan for multiple attributes (OR logic).
     *
     * Returns classes that have ANY of the specified attributes.
     * Useful for discovering all annotated components in a single pass.
     *
     * @param array<class-string> $attributeClasses Attributes to find
     * @param array<string> $paths                  Paths to scan
     *
     * @return DiscoveryResult Classes with any matching attribute
     */
    public function scanForAttributes(array $attributeClasses, array $paths): DiscoveryResult;
}
