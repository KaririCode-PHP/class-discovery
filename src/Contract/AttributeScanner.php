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
 * ```php
 * function controllers(AttributeScanner $scanner): array
 * {
 *     return $scanner->discover(Route::class, ['src/']); // FQCNs, in either mode
 * }
 * ```
 */
interface AttributeScanner extends Scanner
{
    /**
     * Scan for a specific attribute class (single target).
     *
     * More efficient than scan() + manual filtering: exits early
     * when attribute match is impossible (no #[...] tokens in file).
     *
     * @param string $attributeClass Attribute FQCN (`Route::class`); token mode resolves
     *                               `use` imports, so both modes match the same name
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
     * @param array<string> $attributeClasses Attribute identifiers (OR semantics)
     * @param array<string> $paths                  Paths to scan
     *
     * @return DiscoveryResult Classes with any matching attribute
     */
    public function scanForAttributes(array $attributeClasses, array $paths): DiscoveryResult;

    /**
     * FQCNs of the classes carrying the attribute — the class-finder shape
     * consumed through typed closures (`$scanner->discover(...)`).
     *
     * @param array<string> $paths Paths to scan
     *
     * @return list<string>
     */
    public function discover(string $attributeClass, array $paths): array;
}
