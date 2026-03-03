# KaririCode ClassDiscovery

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-brightgreen)]()

PHP 8.4+ class discovery component with token-based scanning, attribute discovery, multi-tier caching, and dependency analysis. Part of the [KaririCode Framework](https://kariricode.org) ecosystem.

## Features

- **Token-based scanning** — discovers classes without loading or executing them (`token_get_all`)
- **Attribute discovery** — finds PHP 8+ attributes at class, method, property, parameter, and constant level
- **Multi-tier caching** — Memory (L1) → File (L2) with automatic promotion on miss
- **Circular dependency detection** — DFS-based cycle detection with formatted reporting
- **Security** — path traversal prevention, symlink containment, configurable resource limits
- **Zero external dependencies** — PHP 8.4+ standard library only
- **Immutable results** — all result objects are `final readonly` (ARFA 1.3 P1)

## Installation

```bash
composer require kariricode/class-discovery
```

**Requirements:** PHP 8.4+

**Optional integrations:**

```bash
composer require kariricode/cache          # PSR-16 cache backend
composer require kariricode/configurator   # Environment-aware configuration
composer require kariricode/dependency-injection  # PSR-11 container
```

## Quick Start

### Discover all classes in a directory

```php
use KaririCode\ClassDiscovery\Scanner\{FileScanner, ComposerNamespaceResolver};

$resolver = new ComposerNamespaceResolver();
$scanner = new FileScanner($resolver);

$result = $scanner->scan(['src/']);

foreach ($result as $fqcn => $metadata) {
    echo "{$fqcn} — {$metadata->filePath}\n";
}

echo "Found {$result->count()} classes in {$result->getScanDuration()}s\n";
```

### Discover classes by attribute

```php
use KaririCode\ClassDiscovery\Scanner\{AttributeScanner, ComposerNamespaceResolver};

$scanner = new AttributeScanner(new ComposerNamespaceResolver());

// Single attribute
$controllers = $scanner->scanForAttribute(Route::class, ['src/']);

// Multiple attributes (OR logic)
$components = $scanner->scanForAttributes(
    [Route::class, Command::class, EventListener::class],
    ['src/'],
);
```

### Filter results

```php
use KaririCode\ClassDiscovery\Filter\{
    AttributeFilter,
    InterfaceFilter,
    NamespaceFilter,
    StructuralFilter,
    CompositeFilter,
};

// By interface
$scanner->addFilter(new InterfaceFilter(MessageHandlerInterface::class));

// By namespace prefix
$scanner->addFilter(new NamespaceFilter('App\\Controller'));

// By structural characteristics
$scanner->addFilter(new StructuralFilter(isFinal: true, isReadonly: true));

// Composite: any filter passes (OR logic)
$scanner->addFilter(new CompositeFilter(
    requireAll: false,
    new AttributeFilter(Route::class),
    new InterfaceFilter(ControllerInterface::class),
));
```

### Enable caching

```php
use KaririCode\ClassDiscovery\Cache\{
    ChainCacheStrategy,
    FileCacheStrategy,
    MemoryCacheStrategy,
};

// Multi-tier: Memory (L1) → File (L2)
$cache = new ChainCacheStrategy(
    new MemoryCacheStrategy(),
    new FileCacheStrategy('/tmp/discovery_cache'),
);

$scanner->setCacheStrategy($cache);

// First scan: ~80ms (cold), subsequent: <5ms (warm)
$result = $scanner->scan(['src/']);
```

### Detect circular dependencies

```php
use KaririCode\ClassDiscovery\Analyzer\{DependencyAnalyzer, CircularDetector};

$detector = new CircularDetector(
    new DependencyAnalyzer(),
    throwOnDetection: true,  // throws DiscoveryException on cycle
);

$cycles = $detector->check($result);
// [['App\A', 'App\B', 'App\C', 'App\A']]
```

### Directory scanner with constraints

```php
use KaririCode\ClassDiscovery\Scanner\{DirectoryScanner, ComposerNamespaceResolver};

$scanner = new DirectoryScanner(new ComposerNamespaceResolver());

$result = $scanner
    ->setMaxDepth(5)
    ->setPattern('*Controller.php')
    ->setFollowSymlinks(false)
    ->scan(['src/']);
```

### PSR-11 container registration

```php
use KaririCode\ClassDiscovery\Integration\PSR11Integration;
use KaririCode\ClassDiscovery\Contract\{Scanner, AttributeScanner};

// Register in any PSR-11 container
$container->singleton(Scanner::class, fn () => PSR11Integration::createDefaultScanner($cache));
$container->singleton(AttributeScanner::class, fn () => PSR11Integration::createAttributeScanner($cache));
```

## Scanner Strategies

| Scanner | Mechanism | Class Loading | Performance (1k classes) | Use Case |
|---|---|---|---|---|
| `FileScanner` | `token_get_all()` | Never | 30-80ms cold, <3ms warm | General class listing |
| `AttributeScanner` | FileScanner + filter | Never | 50-100ms cold, <5ms warm | Attribute discovery |
| `DirectoryScanner` | FileScanner + constraints | Never | 200-500ms (I/O bound) | Depth/pattern-limited scan |
| `ReflectionScanner` | `ReflectionClass` | Required | 300-800ms | Rich metadata (resolved attribute args) |

## Ecosystem Integration

| Component | Discovered Attributes |
|---|---|
| `kariricode/router` | `#[Route]`, `#[Middleware]`, `#[Guard]` |
| `kariricode/console` | `#[Command]`, `#[Argument]`, `#[Option]` |
| `kariricode/dependency-injection` | `#[Singleton]`, `#[Scoped]`, `#[Tagged]` |
| `kariricode/event-dispatcher` | `#[EventListener]`, `#[EventSubscriber]` |
| `kariricode/websocket` | `#[WebSocketRoute]`, `#[OnConnect]`, `#[OnMessage]` |
| `kariricode/i18n` | `#[TranslatableResource]` |

## Error Handling

All exceptions use `DiscoveryException` with unique error codes:

```php
try {
    $result = $scanner->scan(['/nonexistent']);
} catch (DiscoveryException $e) {
    match ($e->getCode()) {
        1001 => handlePathNotFound($e),
        1002 => handlePathTraversal($e),
        1003 => handleSymlinkEscape($e),
        1004 => handleMaxDepthExceeded($e),
        1005 => handleMaxFilesExceeded($e),
        1010 => handleCircularDependency($e),
        default => throw $e,
    };
}
```

## Architecture

```
src/
├── Contract/           7 interfaces (ISP)
├── Scanner/            5 implementations (composition pattern)
├── Result/             5 readonly DPOs (ARFA 1.3 P1)
├── Filter/             5 filter predicates (AND/OR composable)
├── Cache/              3 strategies (Strategy pattern)
├── Analyzer/           2 analyzers (graph + cycle detection)
├── Integration/        3 bridges (PSR-16, PSR-11, Configurator)
├── Enum/               2 enums (1 backed, 1 pure)
└── Exception/          1 exception (10 named constructors)

33 files · ~2,800 lines · zero external dependencies
```

## Documentation

- [SPEC-001: Component Specification](docs/spec/SPEC-001-component-specification.md)
- [ADR-001: Token-Based Scanning Over Reflection](docs/adr/ADR-001-token-based-scanning.md)
- [ADR-002: Zero External Dependencies](docs/adr/ADR-002-zero-external-dependencies.md)
- [ADR-003: Immutable Discovery Results](docs/adr/ADR-003-immutable-discovery-results.md)
- [ADR-004: Backed Enum for AttributeTarget](docs/adr/ADR-004-backed-enum-attribute-target.md)
- [ADR-005: Composition Over Inheritance](docs/adr/ADR-005-composition-over-inheritance.md)
- [ADR-006: Multi-Tier Cache Strategy](docs/adr/ADR-006-multi-tier-cache-strategy.md)

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

Walmir Silva — [walmir.silva@kariricode.org](mailto:walmir.silva@kariricode.org)
