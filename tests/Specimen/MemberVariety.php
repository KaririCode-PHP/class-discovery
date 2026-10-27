<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Specimen;

/**
 * Reflection specimen: one member of every shape ReflectionScanner must map —
 * visibilities, static/abstract, union, nullable and untyped declarations.
 * Kept outside tests/Fixture so directory-wide fixture counts stay stable.
 */
abstract class MemberVariety
{
    public static int $instances = 0;

    protected ?string $label = null;

    /** @var int|string */
    private int|string $key = 0;

    /** @var mixed */
    public $untyped;

    abstract protected function template(): void;

    public static function describe(): string
    {
        return static::class;
    }

    /**
     * @param mixed $raw
     */
    private function combine(int|string $id, $raw, ?\Countable $items = null): int|string
    {
        return $id . (string) $this->key . (is_scalar($raw) ? (string) $raw : '') . (string) $items?->count();
    }
}
