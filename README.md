# KaririCode ClassDiscovery

<div align="center">

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e.svg)](LICENSE)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-4F46E5)](https://phpstan.org/)
[![Tests](https://img.shields.io/badge/Tests-222%20passing-22c55e)](https://kariricode.org)
[![Coverage](https://img.shields.io/badge/Coverage-95.44%25-22c55e)](https://kariricode.org)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-brightgreen)](https://kariricode.org)
[![KaririCode Framework](https://img.shields.io/badge/KaririCode-Framework-orange)](https://kariricode.org)

**PHP 8.4+ class discovery — token-based scanning, attribute automation, multi-tier caching and dependency analysis.**

Discover controllers, services, and event listeners automatically — without loading a single class.

[Installation](#installation) · [Quick Start](#quick-start) · [Use Cases](#real-world-use-cases) · [Scanners](#scanner-comparison) · [Caching](#caching) · [CI Integration](#ci-integration)

</div>

---

## The Problem

Modern PHP applications rely on convention over configuration — routes registered from `#[Route]`, services injected from `#[Service]`, listeners wired from `#[EventListener]`. Without a discovery engine, teams end up writing this manually:

```php
// routes.php — manually maintained, drifts over time
Router::get('/users', [UserController::class, 'index']);
Router::post('/users', [UserController::class, 'store']);
Router::get('/products', [ProductController::class, 'index']);
// ... 300 more lines
```

And for services:

```php
// bootstrap.php — duplicated, error-prone
$container->bind(MailerService::class, MailerService::class);
$container->bind(PaymentService::class, PaymentService::class);
// ... one entry per class forever
```

## The Solution

```bash
composer require kariricode/class-discovery
```

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Route::class));

// Discovers and returns all #[Route]-annotated controllers — instantly
$result = $scanner->scan(['src/Controller']);
```

One scan replaces hundreds of manual registrations. Results are **immutable**, **cacheable**, and resolved in **30–80ms cold / <3ms warm**.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4 or higher |
| Composer | 2.x |

---

## Installation

```bash
composer require kariricode/class-discovery
```

**Optional integrations:**

```bash
composer require kariricode/cache                   # PSR-16 cache backend
composer require kariricode/configurator            # Environment-aware configuration
composer require kariricode/dependency-injection    # PSR-11 container
```

---

## Quick Start

### 1 — Discover all classes

```php
use KaririCode\ClassDiscovery\Scanner\{ComposerNamespaceResolver, FileScanner};

$scanner = new FileScanner(new ComposerNamespaceResolver());
$result  = $scanner->scan(['src/']);

foreach ($result as $fqcn => $metadata) {
    echo "{$fqcn} — {$metadata->filePath}\n";
}

echo "Found " . $result->count() . " classes in "
   . round($result->getScanDuration() * 1000, 1) . "ms\n";
```

### 2 — Filter by attribute

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;

$scanner->addFilter(new AttributeFilter(Route::class));
$result = $scanner->scan(['src/Controller']);
```

### 3 — Enable caching

```php
use KaririCode\ClassDiscovery\Cache\{ChainCacheStrategy, FileCacheStrategy, MemoryCacheStrategy};

$cache = new ChainCacheStrategy(
    new MemoryCacheStrategy(),
    new FileCacheStrategy('/var/cache/discovery'),
);

$scanner->setCacheStrategy($cache);
$result = $scanner->scan(['src/']);  // cold: ~60ms · warm: <2ms
```

---

## Real-world Use Cases

### HTTP Router — auto-register controllers from `#[Route]`

```php
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
    ) {}
}

// Discovery
$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Route::class));
$result  = $scanner->scan(['src/Controller']);

foreach ($result as $fqcn => $metadata) {
    $router->mount($fqcn);  // class-level #[Route]

    foreach ($metadata->methods as $method) {
        if ($method->hasAttribute('Route')) {
            // Use ReflectionScanner to read path / method values
        }
    }
}
```

---

### DI Container — auto-register services from `#[Service]`

```php
$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(Service::class));
$scanner->setCacheStrategy($cache);  // warm: <2ms

$result = $scanner->scan(['src/Service', 'src/Repository']);

foreach ($result as $fqcn => $metadata) {
    $container->bind($fqcn, $fqcn);  // zero manual registration
}
```

---

### Event Dispatcher — auto-wire listeners from `#[EventListener]`

```php
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;

// ReflectionScanner reads actual attribute constructor values
$scanner = new ReflectionScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new AttributeFilter(EventListener::class));
$result  = $scanner->scan(['src/Listener']);

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

### Plugin System — discover extensions by interface

```php
use KaririCode\ClassDiscovery\Filter\InterfaceFilter;

$scanner = new FileScanner(new ComposerNamespaceResolver());
$scanner->addFilter(new InterfaceFilter(PaymentGatewayInterface::class));

$result = $scanner->scan(['plugins/']);

foreach ($result as $fqcn => $metadata) {
    $registry->register(new $fqcn());
}
```

---

### Refactoring Guard — detect circular dependencies

```php
use KaririCode\ClassDiscovery\Analyzer\{CircularDetector, DependencyAnalyzer};

$scanner  = new FileScanner(new ComposerNamespaceResolver());
$result   = $scanner->scan(['src/']);

$detector = new CircularDetector(
    new DependencyAnalyzer(),
    throwOnDetection: true,  // throws DiscoveryException::circularDependency()
);

$cycles = $detector->check($result);
// [['App\A', 'App\B', 'App\C', 'App\A']]
```

---

### Console — auto-register commands from `#[Command]`

```php
use KaririCode\ClassDiscovery\Filter\{AttributeFilter, NamespaceFilter};

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
| `AttributeScanner` | FileScanner + filter | ❌ Never | 50–100ms cold / <5ms warm | Attribute-driven discovery |
| `DirectoryScanner` | FileScanner + constraints | ❌ Never | dep. on I/O | Depth/pattern-bounded scan |
| `ReflectionScanner` | `ReflectionClass` | ✅ Required | 300–800ms | Full attribute argument values |

*per 1,000 classes — with cache, warm scans are typically **5–10× faster**

---

## Filters

```php
use KaririCode\ClassDiscovery\Filter\{
    AttributeFilter,
    InterfaceFilter,
    NamespaceFilter,
    StructuralFilter,
    CompositeFilter,
};

// By attribute (OR — multiple attribute classes)
$scanner->addFilter(new AttributeFilter(Route::class, Command::class));

// By implemented interface
$scanner->addFilter(new InterfaceFilter(HandlerInterface::class));

// By namespace prefix
$scanner->addFilter(new NamespaceFilter('App\\Handler'));

// By structural characteristics
$scanner->addFilter(new StructuralFilter(isFinal: true, isReadonly: true));

// OR-logic composite (attribute OR interface)
$scanner->addFilter(new CompositeFilter(
    requireAll: false,
    new AttributeFilter(Route::class),
    new InterfaceFilter(ControllerInterface::class),
));
```

---

## Caching

```php
use KaririCode\ClassDiscovery\Cache\{
    ChainCacheStrategy,     // L1 → L2 chain with automatic promotion
    FileCacheStrategy,      // Atomic writes (temp → rename), OPcache-friendly
    MemoryCacheStrategy,    // In-process, process-lifetime
};

// Multi-tier: Memory (L1) → File (L2)
$cache = new ChainCacheStrategy(
    new MemoryCacheStrategy(defaultTtl: 60),
    new FileCacheStrategy('/var/cache/discovery', defaultTtl: 3600),
);

$scanner->setCacheStrategy($cache);

// First request  : hits filesystem (~60ms)
// Every request after: hits memory   (<1ms)
$result = $scanner->scan(['src/']);
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

## CI Integration

### GitHub Actions — unified pipeline

```yaml
name: Quality
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: pcov
      - run: composer install --no-progress --no-scripts
      - run: vendor/bin/kcode init
      - run: vendor/bin/kcode quality
```

### GitHub Actions — parallel jobs

```yaml
jobs:
  cs-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - run: composer install --no-progress --no-scripts
      - run: vendor/bin/kcode init && vendor/bin/kcode cs:fix --check

  analyse:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - run: composer install --no-progress --no-scripts
      - run: vendor/bin/kcode init && vendor/bin/kcode analyse

  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', coverage: pcov }
      - run: composer install --no-progress --no-scripts
      - run: vendor/bin/kcode init && vendor/bin/kcode test
```

---

## Architecture

### Component layout

```
src/
├── Contract/      7 interfaces (ISP-compliant)
├── Scanner/       5 implementations (composition pattern)
├── Result/        5 final readonly DPOs (ARFA 1.3 P1)
├── Filter/        5 composable predicates (AND/OR)
├── Cache/         3 cache strategies (Chain, Memory, File)
├── Analyzer/      2 analyzers (dependency graph + DFS cycle detection)
├── Integration/   3 bridges (PSR-16, PSR-11, Configurator)
├── Enum/          2 PHP 8.1 enums (backed + pure)
└── Exception/     1 exception class, 10 named constructors
```

### Key design decisions

| Decision | Rationale | ADR |
|---|---|---|
| Token-based parsing | Never loads or executes discovered classes | [ADR-001](docs/adr/ADR-001-token-based-scanning.md) |
| Zero external dependencies | Works in any PHP 8.4 project, no version conflicts | [ADR-002](docs/adr/ADR-002-zero-external-dependencies.md) |
| Immutable result objects | Thread-safe, cacheable, ARFA 1.3 compliant | [ADR-003](docs/adr/ADR-003-immutable-discovery-results.md) |
| Composition over inheritance | Scanners compose `FileScanner`; no deep hierarchies | [ADR-005](docs/adr/ADR-005-composition-over-inheritance.md) |
| Multi-tier cache strategy | Memory (L1) → File (L2), 5–10× warm speedup | [ADR-006](docs/adr/ADR-006-multi-tier-cache-strategy.md) |

### Specifications

| Spec | Covers |
|---|---|
| [SPEC-001](docs/spec/SPEC-001-component-specification.md) | Component architecture, contracts, scanner strategies |

---

## Project Stats

| Metric | Value |
|---|---|
| PHP source files | 33 |
| Total source lines | ~2,800 |
| External runtime dependencies | 0 |
| Filter types | 5 (Attribute, Interface, Namespace, Structural, Composite) |
| Scanner strategies | 4 (File, Attribute, Directory, Reflection) |
| Cache strategies | 3 (Memory, File, Chain) |
| PHPStan level | 9 (0 errors) |
| Psalm level | 3 (0 errors) |
| Test suite | 222 tests · 440 assertions |
| Line coverage | 95.44% |
| PHP version | 8.4+ |
| ARFA compliance | 1.3 |

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

## Contributing

```bash
git clone https://github.com/kariricode/class-discovery.git
cd class-discovery
composer install
vendor/bin/kcode init
vendor/bin/kcode quality   # Must pass before opening a PR
```

CI enforces code quality (PHPStan level 9, Psalm, CS-Fixer, PHPUnit) on every push and PR.

---

## License

[MIT License](LICENSE) © [Walmir Silva](mailto:walmir.silva@kariricode.org)

---

<div align="center">

Part of the **[KaririCode Framework](https://kariricode.org)** ecosystem.

[kariricode.org](https://kariricode.org) · [GitHub](https://github.com/kariricode) · [Packagist](https://packagist.org/packages/kariricode/)

</div>
