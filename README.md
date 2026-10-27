# KaririCode ClassDiscovery

<div align="center">

[![CI](https://github.com/KaririCode-PHP/class-discovery/actions/workflows/ci.yml/badge.svg)](https://github.com/KaririCode-PHP/class-discovery/actions/workflows/ci.yml)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e.svg)](LICENSE)
[![PHPStan max](https://img.shields.io/badge/PHPStan-level%20max-4F46E5)](https://phpstan.org/)
[![Psalm level 1](https://img.shields.io/badge/Psalm-level%201-4F46E5)](https://psalm.dev/)
[![Tests](https://img.shields.io/badge/Tests-311%20passing-22c55e)](tests)
[![Coverage](https://img.shields.io/badge/Coverage-97.75%25-22c55e)](docs/spec/SPEC-001-component-specification.md)
[![MSI](https://img.shields.io/badge/MSI-83%25-22c55e)](https://infection.github.io/)
[![KaririCode Framework](https://img.shields.io/badge/KaririCode-Framework-orange)](https://kariricode.org)

**PHP 8.4+ class discovery — token-based scanning, attribute automation, multi-tier caching and dependency analysis.**

Discover controllers, services, and event listeners automatically — without loading a single class.

[Installation](#installation) · [Quick Start](#quick-start) · [Use Cases](#real-world-use-cases) · [Scanners](#scanner-comparison) · [Caching](#caching) · [Upgrading](#upgrading-from-1x)

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
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;

// FQCNs of every class carrying #[Route] — on the class or on any action — without loading them
$controllers = new AttributeScanner()->discover(Route::class, ['src/Controller']);
```

One scan replaces hundreds of manual registrations. Results are **immutable** and **cacheable**: about 23 ms per 1,000 classes cold, and under 0.1 ms from the memory cache ([measured](docs/spec/SPEC-001-component-specification.md#4--scanner-strategies)).

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4 or higher |
| Composer | 2.x |
| `kariricode/contract` | ^2.11 (interfaces only) |
| `psr/clock` | ^1.0 (interfaces only) |

---

## Installation

```bash
composer require kariricode/class-discovery
```

**Optional integrations:**

```bash
composer require kariricode/cache                   # PSR-16 cache backend
composer require kariricode/configurator            # Environment-aware configuration
composer require kariricode/dependency-injection    # Container for ClassDiscoveryServiceProvider
```

---

## Quick Start

### 1 — Discover all classes

```php
use KaririCode\ClassDiscovery\Scanner\FileScanner;

$result = new FileScanner()->scan(['src/']);

foreach ($result as $fqcn => $metadata) {
    echo "{$fqcn} — {$metadata->filePath}\n";
}

echo "Found " . count($result) . " classes in "
   . round($result->getScanDuration() * 1000, 1) . "ms\n";
```

### 2 — Filter by attribute

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\FileScanner;

$scanner = new FileScanner();
$scanner->addFilter(new AttributeFilter(Route::class)); // class, method or property attribute
$result = $scanner->scan(['src/Controller']);
```

### 3 — Enable caching

```php
use KaririCode\ClassDiscovery\Cache\{ChainCacheStrategy, FileCacheStrategy, MemoryCacheStrategy};

$scanner->setCacheStrategy(new ChainCacheStrategy(
    new MemoryCacheStrategy(),
    new FileCacheStrategy('/var/cache/discovery'),
));

$result = $scanner->scan(['src/']); // later identical scans are served from the cache
```

### Token mode and reflection mode

Both modes report **fully qualified names** — `use` imports, aliases and groups are resolved the way PHP resolves them.

| | Token mode (`FileScanner`, `AttributeScanner`, `DirectoryScanner`) | Reflection mode (`ReflectionScanner`) |
|---|---|---|
| Loads the scanned code | Never | Yes, through the autoloader — trusted code only |
| Classes, parents, interfaces, traits | Yes | Yes (inherited interfaces too) |
| Methods, properties and their attributes | Yes | Yes |
| Declared types | No | Yes |
| Attribute arguments / `instance` | No | Yes |

Discover with token mode; read attribute values with reflection mode.

---

## Real-world Use Cases

### HTTP Router — auto-register controllers from `#[Route]`

```php
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
    ) {}
}

use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;

// Reflection mode builds each #[Route] — path and method included
$scanner = new ReflectionScanner();
$scanner->addFilter(new AttributeFilter(Route::class));

foreach ($scanner->scan(['src/Controller']) as $fqcn => $metadata) {
    foreach ($metadata->methods as $action => $method) {
        $route = $method->getAttribute(Route::class)?->instance;

        if ($route instanceof Route) {
            $router->add($route->method, $route->path, [$fqcn, $action]);
        }
    }
}
```

---

### DI Container — auto-register services from `#[Service]`

```php
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;

$scanner = new AttributeScanner();
$scanner->setCacheStrategy($cache);

foreach ($scanner->discover(Service::class, ['src/Service', 'src/Repository']) as $fqcn) {
    $container->bind($fqcn, $fqcn, singleton: true); // zero manual registration
}
```

---

### Event Dispatcher — auto-wire listeners from `#[EventListener]`

```php
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;

// ReflectionScanner instantiates the attribute: its constructor values are on ->instance
$scanner = new ReflectionScanner();
$scanner->addFilter(new AttributeFilter(EventListener::class));

foreach ($scanner->scan(['src/Listener']) as $fqcn => $metadata) {
    $listener = $metadata->getAttribute(EventListener::class)?->instance;

    if ($listener instanceof EventListener) {
        $dispatcher->listen(
            event: $listener->event,
            listener: $fqcn,
            priority: $listener->priority,
        );
    }
}
```

---

### Plugin System — discover extensions by interface

```php
use KaririCode\ClassDiscovery\Filter\InterfaceFilter;
use KaririCode\ClassDiscovery\Scanner\FileScanner;

$scanner = new FileScanner();
$scanner->addFilter(new InterfaceFilter(PaymentGateway::class)); // declared directly by the class

foreach ($scanner->scan(['plugins/']) as $fqcn => $metadata) {
    $registry->register(new $fqcn());
}
```

---

### Refactoring Guard — detect circular dependencies

```php
use KaririCode\ClassDiscovery\Analyzer\{CircularDetector, DependencyAnalyzer};
use KaririCode\ClassDiscovery\Scanner\ReflectionScanner;

$result = new ReflectionScanner()->scan(['src/']); // constructor types come from reflection

$detector = new CircularDetector(new DependencyAnalyzer());

foreach ($detector->check($result) as $cycle) {
    echo $cycle->chain(), "\n"; // App\A → App\B → App\C → App\A
}

// In CI: fail the build instead
new CircularDetector(new DependencyAnalyzer(), throwOnDetection: true)->check($result); // CircularDependencyException (8730)
```

---

### Console — auto-register commands from `#[Command]`

```php
use KaririCode\ClassDiscovery\Filter\{AttributeFilter, NamespaceFilter};
use KaririCode\ClassDiscovery\Scanner\FileScanner;

$scanner = new FileScanner();
$scanner->addFilter(new AttributeFilter(Command::class));
$scanner->addFilter(new NamespaceFilter('App\\Console\\Command')); // filters combine with AND

foreach ($scanner->scan(['src/Console']) as $fqcn => $metadata) {
    $application->add(new $fqcn());
}
```

---

## Scanner Comparison

| Scanner | Parser | Loads Classes | 1,000 classes, cold* | Best For |
|---|---|---|---|---|
| `FileScanner` | `PhpToken::tokenize()` | ❌ Never | ~23 ms | General listing, members and attributes |
| `AttributeScanner` | FileScanner + per-call filter | ❌ Never | ~23 ms | Attribute-driven discovery, `discover()` |
| `DirectoryScanner` | FileScanner + constraints | ❌ Never | ~24 ms | Depth/pattern/symlink-bounded scans |
| `ReflectionScanner` | `ReflectionClass` | ✅ Required | ~48 ms (29 ms once loaded) | Types, attribute arguments and instances |

\*Medians on generated classes, PHP 8.4 CLI without OPcache ([SPEC-001 §4](docs/spec/SPEC-001-component-specification.md#4--scanner-strategies)). A memory-cache hit returns in under 0.1 ms. Measure on your own codebase before relying on absolute values.

---

## Filters

```php
use KaririCode\ClassDiscovery\Filter\{
    AttributeFilter,
    CompositeFilter,
    InterfaceFilter,
    NamespaceFilter,
    StructuralFilter,
};

// By attribute (OR — multiple attribute classes)
$scanner->addFilter(new AttributeFilter(Route::class, Command::class));

// By implemented interface
$scanner->addFilter(new InterfaceFilter(Handler::class));

// By namespace prefix
$scanner->addFilter(new NamespaceFilter('App\\Handler'));

// By structural characteristics
$scanner->addFilter(new StructuralFilter(isFinal: true, isReadonly: true));

// OR-logic composite (attribute OR interface) — $requireAll is positional:
// PHP forbids positional arguments after a named one
$scanner->addFilter(new CompositeFilter(
    false,
    new AttributeFilter(Route::class),
    new InterfaceFilter(Controller::class),
));
```

Filters added with `addFilter()` combine with AND.

---

## Caching

```php
use KaririCode\ClassDiscovery\Cache\{
    ChainCacheStrategy,     // L1 → L2 chain with automatic promotion
    FileCacheStrategy,      // Atomic writes, private directory, allow-listed unserialize()
    MemoryCacheStrategy,    // In-process, process lifetime
};

// Multi-tier: Memory (L1) → File (L2)
$scanner->setCacheStrategy(new ChainCacheStrategy(
    new MemoryCacheStrategy(),
    new FileCacheStrategy('/var/cache/discovery'), // refused (8721) if not private
));
```

- Entries expire after one hour by default (`CacheStrategy::set(..., ttl: 3600)`; `0` never expires). Both caches take an optional PSR-20 clock, so expiry follows your application's clock.
- The file cache restores only result objects. An entry that would come back incomplete — an attribute `instance`, or an object inside its arguments — is a miss and gets recomputed, never served half-restored.
- `CacheBridge` adapts any PSR-16 cache.

---

## Container Registration

```php
use KaririCode\ClassDiscovery\Contract\AttributeScanner;
use KaririCode\ClassDiscovery\Provider\ClassDiscoveryServiceProvider;

$container->addProvider(new ClassDiscoveryServiceProvider([
    'max_files' => 5000,
    'cache' => ['dir' => '/var/cache/discovery'],
]));
$container->boot();

$controllers = $container->get(AttributeScanner::class)->discover(Route::class, ['src/']);
```

The provider implements `KaririCode\Contract\DI\ServiceProvider`, so it works with any `BindableContainer`. It binds `Contract\Scanner`, `Contract\AttributeScanner`, `ReflectionScanner`, `DependencyAnalyzer` and `CircularDetector`, lazily.

---

## Error Handling

Every failure extends the abstract `DiscoveryException` and implements one family of kariricode/contract (`NotFound`, `Configuration`, `Runtime`), so an HTTP edge maps it without knowing this package. Codes live in the block 8700–8799:

```php
use KaririCode\ClassDiscovery\Exception\DiscoveryException;

try {
    $result = $scanner->scan(['/path/that/does/not/exist']);
} catch (DiscoveryException $e) {
    match ($e->getCode()) {
        8701 => handlePathNotFound($e),
        8702 => handleSymlinkEscape($e),       // followed link resolves outside the scan root
        8710, 8711 => handleScanLimit($e),     // depth / file count
        8720 => handleTamperedCache($e),
        8721, 8722 => handleCacheDirectory($e),
        8730 => handleCircularDependency($e),
        8740 => handleInvalidConfiguration($e),
        default => throw $e,
    };
}
```

---

## Upgrading from 1.x

2.0.0 is a major release. The full list is in the [CHANGELOG](CHANGELOG.md); the changes most code meets:

- **Scanners take no resolver:** `new FileScanner(new ComposerNamespaceResolver())` → `new FileScanner()`.
- **Fully qualified names in token mode:** filter with `Route::class`, not `'Route'`.
- **Exceptions:** `DiscoveryException::pathNotFound()` and its siblings became final classes (`PathNotFoundException::at()`, …); codes moved from 1001–1011 to 8701–8740. `catch (DiscoveryException $e)` still works.
- **Container:** `PSR11Integration` → `ClassDiscoveryServiceProvider`.
- **Analyzer:** `buildGraph()` returns `DependencyGraph`; cycles are `DependencyCycle` objects.
- **Metadata:** `visibility` is the `Visibility` enum; `AttributeMetadata::$instance` (1.1.0) is unchanged.

---

## Architecture

### Component layout

```
src/
├── Contract/      7 interfaces (ISP-compliant)
├── Scanner/       4 scanners · PSR-4 resolver · token engine (TokenStream, ImportTable, ClassBodyParser)
├── Result/        5 final readonly DPOs (ARFA 1.3 P1)
├── Filter/        5 composable predicates (AND/OR)
├── Cache/         3 cache strategies (Chain, Memory, File) · PSR-20 SystemClock
├── Analyzer/      graph builder · cycle search · DependencyGraph/DependencyCycle value objects
├── Integration/   PSR-16 bridge · Configurator bridge
├── Provider/      ClassDiscoveryServiceProvider (kariricode/contract)
├── Enum/          AttributeTarget (backed) · Visibility
└── Exception/     abstract DiscoveryException + 7 final exceptions (contract families, 8701–8740)
```

### Key design decisions

| Decision | Rationale | ADR |
|---|---|---|
| Token-based parsing | Never loads or executes discovered classes | [ADR-001](docs/adr/ADR-001-token-based-scanning.md) |
| Minimal dependencies | Interfaces only: kariricode/contract and PSR-20 | [ADR-002](docs/adr/ADR-002-zero-external-dependencies.md) |
| Immutable result objects | Safe to share and cache; ARFA 1.3 P1 | [ADR-003](docs/adr/ADR-003-immutable-discovery-results.md) |
| Backed enum for attribute targets | Values mirror `Attribute::TARGET_*` | [ADR-004](docs/adr/ADR-004-backed-enum-attribute-target.md) |
| Composition over inheritance | Scanners compose `FileScanner`; no deep hierarchies | [ADR-005](docs/adr/ADR-005-composition-over-inheritance.md) |
| Multi-tier cache strategy | Memory (L1) → File (L2), integrity-checked | [ADR-006](docs/adr/ADR-006-multi-tier-cache-strategy.md) |
| Quality toolchain per Spec | Root configs, SHA-pinned CI, SBOM | [ADR-007](docs/adr/ADR-007-quality-toolchain-per-spec.md) |
| No `SerializableData` on metadata | Attribute arguments and instances have no JSON form | [ADR-008](docs/adr/ADR-008-metadata-dpos-without-serializable-data.md) |
| Parameter budget for readonly DPOs | Promoted constructors are the data shape | [ADR-009](docs/adr/ADR-009-parameter-budget-for-readonly-dpos.md) |

### Specifications

| Document | Covers |
|---|---|
| [SPEC-001](docs/spec/SPEC-001-component-specification.md) | Architecture, scanner strategies, security model, error codes, ARFA matrix, measured gate |
| [QD-V433-COMPLIANCE](docs/quality/QD-V433-COMPLIANCE.md) | Spec V4.3.3 compliance status |
| [Review log](docs/TEST-REVIEW.md) | Findings, fixes and decisions, pass by pass |

---

## Quality Gate

```bash
composer quality   # cs-check · phpstan · psalm · complexity · test · infection · security · sbom
```

| Check | Result |
|---|---|
| PHPUnit (Unit, Integration, Conformance) | 311 tests · 1,434 assertions |
| PHPStan level max | 0 errors, no suppressions |
| Psalm errorLevel 1 | 0 errors |
| PER Coding Style | clean |
| Complexity budgets (Spec §13) | pass |
| Mutation testing (Infection) | MSI 83 % (gate 80 %) |
| Line coverage | 97.75 % |

Measured locally for this release; CI runs the same scripts on every push and tag. `composer infection` needs a coverage driver (pcov or Xdebug).

---

## Project Stats

| Metric | Value |
|---|---|
| PHP source files | 48 |
| Source lines | 3,957 |
| Runtime dependencies | 2, interfaces only (kariricode/contract, psr/clock) |
| Filter types | 5 (Attribute, Interface, Namespace, Structural, Composite) |
| Scanner strategies | 4 (File, Attribute, Directory, Reflection) |
| Cache strategies | 3 (Memory, File, Chain) + PSR-16 bridge |
| PHP version | 8.4+ |
| ARFA compliance | 1.3 (level 1) · Spec V4.3.3 |

---

## KaririCode Ecosystem Integration

| Component | Attributes Discovered |
|---|---|
| `kariricode/router` | `#[Route]`, `#[Middleware]`, `#[Guard]` |
| `kariricode/console` | `#[Command]`, `#[Argument]`, `#[Option]` |
| `kariricode/dependency-injection` | `#[Singleton]`, `#[Scoped]`, `#[Tagged]` |
| `kariricode/event-dispatcher` | `#[EventListener]`, `#[EventSubscriber]` |
| `kariricode/observability` | `#[Traced]` (through `$scanner->discover(...)`) |
| `kariricode/websocket` | `#[WebSocketRoute]`, `#[OnConnect]`, `#[OnMessage]` |
| `kariricode/i18n` | `#[TranslatableResource]` |

---

## Contributing

```bash
git clone https://github.com/KaririCode-PHP/class-discovery.git
cd class-discovery
composer install
composer quality   # must pass before opening a PR
```

CI runs the same gate on every push and pull request; dependency changes in a PR also go through GitHub's dependency review. Security reports: see [SECURITY.md](SECURITY.md).

---

## License

[MIT License](LICENSE) © [Walmir Silva](mailto:walmir.silva@kariricode.org)

---

<div align="center">

Part of the **[KaririCode Framework](https://kariricode.org)** ecosystem.

[kariricode.org](https://kariricode.org) · [GitHub](https://github.com/KaririCode-PHP) · [Packagist](https://packagist.org/packages/kariricode/class-discovery)

</div>
