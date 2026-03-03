# KaririCode ClassDiscovery

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-brightgreen)]()
[![Tests](https://img.shields.io/badge/tests-222%20passing-brightgreen)]()
[![Coverage](https://img.shields.io/badge/coverage-95.44%25-green)]()
[![Quality](https://img.shields.io/badge/quality-cs--fixer%20%7C%20phpstan%20%7C%20psalm-blue)]()

> **PHP 8.4+ class discovery** — token-based scanning, attribute-driven automation, multi-tier caching and circular dependency detection.  
> Part of the [KaririCode Framework](https://kariricode.org). Zero external dependencies.

---

## Why kariricode/class-discovery?

Modern PHP applications rely heavily on **convention-over-configuration** — routes auto-registered from `#[Route]`, services injected from `#[Service]`, event listeners wired from `#[EventListener]`. This library is the **engine** behind that automation: it scans your codebase, reads PHP attributes, and returns rich metadata that your framework components can act on — without ever loading or executing the discovered classes.

Key decisions:
- **Token-based parsing** (`token_get_all`) — never triggers autoloading or class execution
- **Immutable results** — all result objects are `final readonly`, safe to cache and share
- **Composable filters** — combine `AttributeFilter`, `InterfaceFilter`, `NamespaceFilter`, `StructuralFilter` with AND/OR logic
- **Zero dependencies** — works in any PHP 8.4 project, no framework required

---

## Installation

```bash
composer require kariricode/class-discovery
```

**Optional integrations:**

```bash
composer require kariricode/cache                   # PSR-16 cache backend
composer require kariricode/configurator            # Environment-aware config
composer require kariricode/dependency-injection    # PSR-11 container
```

---

## Real-world Use Cases

### 1. HTTP Router — auto-register controllers from `#[Route]`

Discover all classes and methods annotated with `#[Route]` without manually listing routes in a config file.

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
    ) {}
}

// ── Discovery ─────────────────────────────────────────────────────
$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Route::class));

$result = $scanner->scan(['src/Controller']);

foreach ($result as $fqcn => $metadata) {
    // Class-level route
    if ($metadata->hasAttribute('Route')) {
        $router->mount($fqcn);
    }

    // Method-level routes (use ReflectionScanner for attribute arg values)
    foreach ($metadata->methods as $method) {
        if ($method->hasAttribute('Route')) {
            echo "  route: {$fqcn}::{$method->name}\n";
        }
    }
}
```

---

### 2. DI Container — auto-register services from `#[Service]`

Eliminate manual service registration. Scan once at boot and populate your container automatically.

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};
use KaririCode\ClassDiscovery\Cache\{ChainCacheStrategy, FileCacheStrategy, MemoryCacheStrategy};

// Multi-tier cache: Memory (L1) → File (L2)
$cache = new ChainCacheStrategy(
    new MemoryCacheStrategy(),
    new FileCacheStrategy('/var/cache/discovery'),
);

$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Service::class));
$scanner->setCacheStrategy($cache);  // cold: ~60ms, warm: <2ms

$result = $scanner->scan(['src/Service', 'src/Repository']);

foreach ($result as $fqcn => $metadata) {
    // Register every discovered class in the DI container
    $container->bind($fqcn, $fqcn);
}
```

---

### 3. Event Dispatcher — auto-wire listeners from `#[EventListener]`

Discover and register all event listeners without a YAML/XML mapping file.

```php
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, ReflectionScanner};
use KaririCode\ClassDiscovery\Filter\AttributeFilter;

// ReflectionScanner reads actual attribute constructor values
$scanner = new ReflectionScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(EventListener::class));

$result = $scanner->scan(['src/Listener']);

foreach ($result as $fqcn => $metadata) {
    foreach ($metadata->attributes as $attrMeta) {
        if ($attrMeta->instance instanceof EventListener) {
            $dispatcher->listen(
                event   : $attrMeta->instance->event,
                listener: $fqcn,
                priority: $attrMeta->instance->priority,
            );
        }
    }
}
```

---

### 4. Plugin System — discover extensions by interface

Find all classes implementing a specific interface across dynamically loaded modules.

```php
use KaririCode\ClassDiscovery\Filter\InterfaceFilter;
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new InterfaceFilter(PaymentGatewayInterface::class));

$result = $scanner->scan(['plugins/']);  // scan dynamically loaded plugin dirs

foreach ($result as $fqcn => $metadata) {
    $registry->register(new $fqcn());
}
```

---

### 5. Refactoring Guard — detect circular dependencies

Catch dependency cycles before they reach production.

```php
use KaririCode\ClassDiscovery\Analyzer\{CircularDetector, DependencyAnalyzer};
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

$scanner  = new FileScanner(new ComposerNamespaceResolver());
$result   = $scanner->scan(['src/']);

$detector = new CircularDetector(
    new DependencyAnalyzer(),
    throwOnDetection: true,  // throws DiscoveryException::circularDependency()
);

$cycles = $detector->check($result);
// Returns: [['App\A', 'App\B', 'App\C', 'App\A']]
```

---

### 6. Console Command Auto-registration

Discover all `#[Command]` annotated classes for a CLI application.

```php
use KaririCode\ClassDiscovery\Filter\{AttributeFilter, NamespaceFilter};
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Command::class));
$scanner->addFilter(new NamespaceFilter('App\\Console\\Command'));

$result = $scanner->scan(['src/Console']);

foreach ($result as $fqcn => $metadata) {
    $application->add(new $fqcn());
}
```

---

## Scanner Comparison

| Scanner | Parser | Loads Classes | Performance* | Best For |
|---|---|---|---|---|
| `FileScanner` | `token_get_all` | ❌ Never | 30–80ms cold / <3ms warm | General listing, attribute names |
| `AttributeScanner` | `FileScanner` + filter | ❌ Never | 50–100ms cold / <5ms warm | Attribute-driven discovery |
| `DirectoryScanner` | `FileScanner` + constraints | ❌ Never | dep. on I/O | Depth/pattern-bounded scan |
| `ReflectionScanner` | `ReflectionClass` | ✅ Required | 300–800ms | Full attribute argument values |

*per 1,000 classes — with `FileCacheStrategy` warm scans are typically **5–10× faster**

---

## Filters (Composable)

```php
use KaririCode\ClassDiscovery\Filter\{
    AttributeFilter,
    InterfaceFilter,
    NamespaceFilter,
    StructuralFilter,
    CompositeFilter,
};

// Match classes that implement an interface AND are in a namespace
$scanner->addFilter(new InterfaceFilter(HandlerInterface::class));
$scanner->addFilter(new NamespaceFilter('App\\Handler'));

// OR-logic: match by attribute OR by interface (composite)
$scanner->addFilter(new CompositeFilter(
    requireAll: false,                              // OR mode
    new AttributeFilter(Route::class),
    new InterfaceFilter(ControllerInterface::class),
));

// Structural: only abstract, non-readonly classes
$scanner->addFilter(new StructuralFilter(isAbstract: true, isReadonly: false));
```

---

## Caching

```php
use KaririCode\ClassDiscovery\Cache\{
    ChainCacheStrategy,
    FileCacheStrategy,
    MemoryCacheStrategy,
};

// L1: in-memory (process lifetime)
// L2: file-based (cross-request, atomic writes)
$cache = new ChainCacheStrategy(
    new MemoryCacheStrategy(defaultTtl: 60),
    new FileCacheStrategy('/var/cache/discovery', defaultTtl: 3600),
);

$scanner->setCacheStrategy($cache);
$result = $scanner->scan(['src/']);  // cold: filesystem; warm: <2ms from memory
```

---

## PSR-11 Container Registration

```php
use KaririCode\ClassDiscovery\Integration\PSR11Integration;
use KaririCode\ClassDiscovery\Contract\{Scanner, AttributeScanner};

$container->singleton(
    Scanner::class,
    fn () => PSR11Integration::createDefaultScanner($cache),
);

$container->singleton(
    AttributeScanner::class,
    fn () => PSR11Integration::createAttributeScanner($cache),
);
```

---

## Error Handling

```php
use KaririCode\ClassDiscovery\Exception\DiscoveryException;

try {
    $result = $scanner->scan(['/path/that/does/not/exist']);
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

---

## Architecture

```
src/
├── Contract/      7 interfaces (ISP-compliant)
├── Scanner/       5 implementations using composition
├── Result/        5 final readonly DPOs (ARFA 1.3 P1)
├── Filter/        5 composable predicates (AND/OR)
├── Cache/         3 cache strategies (Chain, Memory, File)
├── Analyzer/      2 analyzers (dependency graph + DFS cycle detection)
├── Integration/   3 bridges (PSR-16, PSR-11, Configurator)
├── Enum/          2 PHP 8.1 enums (backed + pure)
└── Exception/     1 exception class, 10 named constructors

33 files · ~2,800 lines · 222 tests · 95.44% line coverage · zero dependencies
```

---

## KaririCode Ecosystem Integration

| Component | Attributes Discovered |
|---|---|
| `kariricode/router` | `#[Route]`, `#[Middleware]`, `#[Guard]` |
| `kariricode/console` | `#[Command]`, `#[Argument]`, `#[Option]` |
| `kariricode/dependency-injection` | `#[Singleton]`, `#[Scoped]`, `#[Tagged]` |
| `kariricode/event-dispatcher` | `#[EventListener]`, `#[EventSubscriber]` |
| `kariricode/websocket` | `#[WebSocketRoute]`, `#[OnConnect]`, `#[OnMessage]` |
| `kariricode/i18n` | `#[TranslatableResource]` |

---

## Documentation

- [SPEC-001: Component Specification](docs/spec/SPEC-001-component-specification.md)
- [ADR-001: Token-Based Scanning Over Reflection](docs/adr/ADR-001-token-based-scanning.md)
- [ADR-002: Zero External Dependencies](docs/adr/ADR-002-zero-external-dependencies.md)
- [ADR-003: Immutable Discovery Results](docs/adr/ADR-003-immutable-discovery-results.md)
- [ADR-004: Backed Enum for AttributeTarget](docs/adr/ADR-004-backed-enum-attribute-target.md)
- [ADR-005: Composition Over Inheritance](docs/adr/ADR-005-composition-over-inheritance.md)
- [ADR-006: Multi-Tier Cache Strategy](docs/adr/ADR-006-multi-tier-cache-strategy.md)

---

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

Walmir Silva — [walmir.silva@kariricode.org](mailto:walmir.silva@kariricode.org)
