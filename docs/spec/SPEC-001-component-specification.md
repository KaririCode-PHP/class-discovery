# SPEC-001: KaririCode\ClassDiscovery Component Specification

**Version:** 3.2.0
**Status:** Active
**Date:** 2026-02-28
**Authors:** Walmir Silva <walmir.silva@kariricode.org>
**ARFA:** 1.3 / Code Quality Directive V4.0

---

## §1 — Purpose

ClassDiscovery provides automated PHP class, interface, enum, and trait discovery with attribute scanning, namespace resolution, dependency analysis, and caching. It serves as the foundation for attribute-driven bootstrapping across the KaririCode ecosystem.

## §2 — Requirements

| ID | Requirement | Priority |
|---|---|---|
| REQ-01 | Discover classes from filesystem paths without class loading | P0 |
| REQ-02 | Discover PHP 8+ attributes at class, method, property, parameter, and constant level | P0 |
| REQ-03 | Resolve FQCNs from file paths using PSR-4 conventions | P0 |
| REQ-04 | Filter results by attribute, interface, namespace, and structural characteristics | P0 |
| REQ-05 | Cache discovery results with configurable strategy | P1 |
| REQ-06 | Detect circular dependencies in discovered class graphs | P1 |
| REQ-07 | Integrate with PSR-16 cache, PSR-11 container, and KaririCode Configurator | P2 |
| REQ-08 | Zero external dependencies (PHP 8.4+ stdlib only) | P0 |
| REQ-09 | Immutable result objects (ARFA 1.3 P1) | P0 |
| REQ-10 | Security: no code execution, path traversal prevention, symlink containment | P0 |

## §3 — Architecture

### §3.1 Module Structure

```
src/
├── Contract/           7 interfaces (ISP)
│   ├── Scanner             Core scan contract
│   ├── AttributeScanner    Attribute-specialized extension
│   ├── DirectoryScanner    Depth/pattern/symlink-constrained extension
│   ├── DiscoveryResult     Immutable result container
│   ├── ClassFilter         Filter predicate
│   ├── CacheStrategy       Cache abstraction
│   └── NamespaceResolver   PSR-4 FQCN resolution
├── Scanner/            5 implementations
│   ├── FileScanner              Token-based core engine (final)
│   ├── AttributeScanner         Composes FileScanner + attribute filtering
│   ├── DirectoryScanner         Composes FileScanner + traversal constraints
│   ├── ReflectionScanner        Runtime reflection (requires class loading)
│   └── ComposerNamespaceResolver   PSR-4 resolution from composer.json
├── Result/             5 readonly DPOs
│   ├── DiscoveryResult     Immutable scan result container
│   ├── ClassMetadata       Per-class metadata snapshot
│   ├── MethodMetadata      Per-method metadata snapshot
│   ├── PropertyMetadata    Per-property metadata snapshot
│   └── AttributeMetadata   Per-attribute metadata snapshot
├── Filter/             5 filters
│   ├── AttributeFilter     Filter by attribute presence (OR)
│   ├── InterfaceFilter     Filter by interface implementation
│   ├── NamespaceFilter     Filter by namespace prefix
│   ├── StructuralFilter    Filter by class modifiers (final, abstract, readonly, etc.)
│   └── CompositeFilter     Combine filters with AND/OR logic
├── Cache/              3 strategies
│   ├── MemoryCacheStrategy     Request-scoped (dev/test)
│   ├── FileCacheStrategy       Disk-based with atomic writes (production)
│   └── ChainCacheStrategy      Multi-tier with promotion (L1→L2)
├── Analyzer/           2 analyzers
│   ├── DependencyAnalyzer      Builds class dependency graph
│   └── CircularDetector        DFS cycle detection with reporting
├── Integration/        3 bridges
│   ├── CacheBridge             PSR-16 adapter
│   ├── ConfiguratorBridge      KaririCode\Configurator adapter
│   └── PSR11Integration        PSR-11 container factory methods
├── Enum/               2 enums
│   ├── AttributeTarget         Backed (int) — maps Attribute::TARGET_*
│   └── ScannerType             Pure — strategy classification
└── Exception/          1 exception class
    └── DiscoveryException      10 named constructors, codes 1001-1010
```

### §3.2 Composition Graph

All scanner variants compose `FileScanner` (ADR-005):

```
FileScanner (final, token-based core engine)
  ├── AttributeScanner    (composes: adds attribute-specific filtering)
  ├── DirectoryScanner    (composes: adds depth/pattern/symlink constraints)
  └── ReflectionScanner   (composes: adds runtime reflection metadata)
```

### §3.3 Data Flow

```
Paths → Scanner.scan() → collectPhpFiles() → parseFile() → token_get_all()
  → ClassMetadata → passesFilters() → DiscoveryResult (immutable)
  → CacheStrategy.set()
```

## §4 — Scanner Strategies

| Scanner | Mechanism | Class Loading | Use Case | Performance (1k classes) |
|---|---|---|---|---|
| FileScanner | `token_get_all()` | Never | General class listing | 30-80ms cold, <3ms warm |
| AttributeScanner | FileScanner + filter | Never | Attribute discovery | 50-100ms cold, <5ms warm |
| DirectoryScanner | FileScanner + traversal | Never | Constrained filesystem scan | 200-500ms (I/O bound) |
| ReflectionScanner | `ReflectionClass` | Required | Rich metadata (resolved args) | 300-800ms cold |

## §5 — Security Model

| Threat | Mitigation | Implementation |
|---|---|---|
| Code execution | Token-only parsing | `token_get_all()` — never `require`/`include` |
| Path traversal | `realpath()` validation | All paths resolved before processing |
| Symlink escape | Containment check | Target must resolve within scan root |
| Resource exhaustion | Configurable limits | `maxFileSize` (10MB), `maxFiles` (10k), `maxDepth` (10) |
| Cache poisoning | Atomic writes | File cache uses temp-file + rename |

## §6 — Error Codes

| Code | Named Constructor | Meaning |
|---|---|---|
| 1001 | `pathNotFound` | Scan path does not exist |
| 1002 | `pathTraversalDetected` | Path resolves outside allowed root |
| 1003 | `symlinkEscape` | Symlink target outside scan root |
| 1004 | `maxDepthExceeded` | Directory nesting exceeds limit |
| 1005 | `maxFilesExceeded` | File count exceeds limit |
| 1006 | `scanTimeout` | Scan exceeded time limit |
| 1007 | `tokenParseFailure` | `token_get_all()` failed |
| 1008 | `namespaceResolutionError` | PSR-4 resolution failed |
| 1009 | `cacheCorruption` | Cache entry invalid or unreadable |
| 1010 | `circularDependency` | Circular dependency detected in graph |

## §7 — ARFA 1.3 Compliance Matrix

| Principle/Errata | Requirement | Implementation |
|---|---|---|
| P1 Immutable State | Result objects immutable | All 5 Result classes `final readonly`; `filter()`/`merge()` return new instances |
| P4 Protocol Agnostic | No HTTP/CLI coupling | Zero framework coupling; works in any SAPI |
| P5 Continuous Observability | Scan metrics | `scanDuration` on `DiscoveryResult`; error collection |
| E-001 | No `jsonSerialize()`/`toArray()` | Public readonly properties are the API |
| E-002 | No property hooks on readonly | Zero occurrences |
| E-003 | Backed enum justified | `AttributeTarget: int` maps `Attribute::TARGET_*` |
| E-005 | Documentation quality | Substance-focused, no artificial padding |

## §8 — Ecosystem Integration

| Consumer Component | Discovered Attributes | Discovery Method |
|---|---|---|
| KaririCode\Router | `#[Route]`, `#[Middleware]`, `#[Guard]` | `scanForAttributes()` |
| KaririCode\Console | `#[Command]`, `#[Argument]`, `#[Option]` | `scanForAttributes()` |
| KaririCode\DI | `#[Singleton]`, `#[Scoped]`, `#[Tagged]` | `scanForAttributes()` |
| KaririCode\EventDispatcher | `#[EventListener]`, `#[EventSubscriber]` | `scanForAttributes()` |
| KaririCode\WebSocket | `#[WebSocketRoute]`, `#[OnConnect]`, `#[OnMessage]` | `scanForAttributes()` |
| KaririCode\I18n | `#[TranslatableResource]` | `scanForAttribute()` |

## §9 — Configuration (via ConfiguratorBridge)

| Key | Type | Default | Description |
|---|---|---|---|
| `discovery.cache.enabled` | bool | `true` | Enable/disable caching |
| `discovery.cache.dir` | string | `sys_get_temp_dir()/kariricode_discovery` | Cache directory |
| `discovery.cache.ttl` | int | `3600` | Cache TTL in seconds |
| `discovery.max_files` | int | `10000` | Max files per scan |
| `discovery.max_file_size` | int | `10485760` | Max file size in bytes (10MB) |
| `discovery.paths` | array | `['src/']` | Paths to scan |
| `discovery.include_dev` | bool | `false` | Include `autoload-dev` mappings |
