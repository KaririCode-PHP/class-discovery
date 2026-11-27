<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Double;

use KaririCode\Contract\DI\BindableContainer;

/**
 * Smallest container honouring the kariricode/contract 2.11.0 semantics:
 * a callable factory receives the container, `null` builds the abstract,
 * singletons are built once. Counts builds so tests can assert laziness.
 */
final class InMemoryContainer implements BindableContainer
{
    /** @var array<string, array{concrete: callable|string|null, singleton: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public int $builds = 0;

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id] ?? throw new \LogicException("Unknown service: {$id}");
        $built = $this->build($id, $binding['concrete']);

        if ($binding['singleton']) {
            $this->instances[$id] = $built;
        }

        return $built;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || array_key_exists($id, $this->instances);
    }

    public function bind(string $abstract, callable|string|null $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => $singleton];
    }

    public function instance(string $abstract, mixed $value): void
    {
        $this->instances[$abstract] = $value;
    }

    private function build(string $id, callable|string|null $concrete): mixed
    {
        ++$this->builds;
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        $class = $concrete ?? $id;
        if (!class_exists($class)) {
            throw new \LogicException("Cannot build {$class}");
        }

        return new \ReflectionClass($class)->newInstance();
    }
}
