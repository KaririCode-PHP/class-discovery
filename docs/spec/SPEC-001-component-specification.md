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
| REQ-07 | Integrate with PSR-16 cache, kariricode/contract containers (`DI\ServiceProvider`), and KaririCode Configurator | P2 |
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
│   ├── ComposerNamespaceResolver   PSR-4 resolution from composer.json
│   ├── TokenStream              Token navigation; extractors return the last consumed index (@internal)
│   ├── ImportTable              PHP class-name resolution for token mode (@internal)
│   ├── ClassBodyParser          Members and their attributes from tokens (@internal)
│   └── ScanCacheKey             Configuration-sensitive cache key (@internal)
├── Result/             5 readonly DPOs
│   ├── DiscoveryResult     Immutable scan result container
│   ├── ClassMetadata       Per-class metadata snapshot
│   ├── MethodMetadata      Per-method metadata snapshot
│   ├── PropertyMetadata    Per-property metadata snapshot
│   └── AttributeMetadata   Per-attribute metadata snapshot; `instance` in reflection mode
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
│   ├── DependencyAnalyzer      Builds the dependency graph (stateless)
│   ├── DependencyGraph         Value object: FQCN → dependencies
│   ├── DependencyCycle         Value object: one cycle, chain() for messages
│   ├── CycleSearch             One DFS run's state (@internal)
│   └── CircularDetector        Cycle detection with reporting
├── Provider/
│   └── ClassDiscoveryServiceProvider  contract DI\ServiceProvider; lazy bindings
├── Integration/        3 bridges
│   ├── CacheBridge             PSR-16 adapter
│   ├── ConfiguratorBridge      KaririCode\Configurator adapter
├── Enum/               2 enums
│   ├── AttributeTarget         Backed (int) — maps Attribute::TARGET_*
│   └── Visibility             Backed by the keyword: JSON form of the DPOs (E-001)
└── Exception/          1 exception class
    ├── DiscoveryException      abstract; implements Contract\Exception\ExceptionInterface
    └── 7 final exceptions      one contract family each; codes 8701–8740
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

| Scanner | Mechanism | Class Loading | Use Case |
|---|---|---|---|
| FileScanner | `PhpToken::tokenize()`, declaration-anchored | Never | General class listing |
| AttributeScanner | FileScanner + per-call attribute filter | Never | Attribute discovery |
| DirectoryScanner | Constrained traversal + FileScanner | Never | Depth/pattern/symlink-bounded scans |
| ReflectionScanner | FileScanner inventory + `ReflectionClass` | Required (executes files) | Resolved names and attribute arguments |

**Measured** on 1,000 generated classes (PHP 8.4.23 CLI, no OPcache, median of 7 runs, CI sandbox; reproduce with any corpus before relying on absolute values):

| Scanner | Cold | Warm (MemoryCacheStrategy hit) |
|---|---|---|
| FileScanner | 22.8 ms | < 0.1 ms |
| AttributeScanner (`scanForAttribute`) | 22.9 ms | < 0.1 ms |
| DirectoryScanner | 24.0 ms | < 0.1 ms |
| ReflectionScanner | 47.8 ms first run (autoload), 29.2 ms once loaded | < 0.1 ms |

Both modes record **fully qualified names**. Token mode resolves them with PHP's compile-time rules (`ImportTable`: imports, aliases, groups, relative names; no global fallback for classes) and reads class bodies (`ClassBodyParser`: methods, declared and promoted properties, trait uses, each with resolved attributes) — but no declared types. Reflection mode adds types and resolved attribute arguments.

## §5 — Security Model

| Threat | Mitigation | Implementation |
|---|---|---|
| Code execution | Token-only parsing | FileScanner/AttributeScanner/DirectoryScanner never `require`; ReflectionScanner autoloads — trusted code only |
| Path traversal | `realpath()` resolution | Every scan path resolved before processing |
| Symlink escape (CWE-22) | Separator-aware containment, fail closed | Followed links must resolve to the root or below `root/`; `/app/src-evil` is outside `/app/src` → 8702 |
| Resource exhaustion | Bounded work | `maxFileSize` (10 MB, inclusive), `maxFiles` (10k, inclusive), `maxDepth` (10, min 1) |
| Cache object injection (CWE-502) | `unserialize()` allow-list | Only result classes are restored; foreign objects never instantiate → purge + 8720. An entry whose attribute `instance` (or an object inside its arguments) would come back incomplete is a miss, never served |
| Cache tampering / shared temp (CWE-377) | Private directory, fail closed | Refuses symlinked, world-writable or foreign-owned directories (8721) or uncreatable ones (8722); creates 0700; temp files via `tempnam()` |
| Cache key traversal | Hashed file names | `discovery_<xxh128(key)>.cache` — no key reaches outside the directory |
| Cache cross-talk | Configuration-sensitive keys | `ScanCacheKey`: scanner + realpath'd paths + each filter's serialized state |
| Misconfiguration | Fail fast | Wrong option types raise 8740 instead of being coerced to 0/false |

**ASVS level:** 1 (ASVS 5.0, declared in `composer.json` `extra.kariricode.asvs`). The component has no network, authentication or session surface; the controls above cover file handling and deserialization.

## §6 — Error Codes

Block allocated in the ecosystem registry: **8700–8799** (`extra.kariricode.error-code-range`). Every failure extends the abstract `DiscoveryException` (`implements Contract\Exception\ExceptionInterface`) and implements exactly one contract family (Spec §8.4); the conformance suite enforces family, finality, uniqueness and range.

| Code | Exception (factory) | Family | Meaning |
|---|---|---|---|
| 8701 | `PathNotFoundException::at()` | NotFound | Scan path does not exist |
| 8702 | `SymlinkEscapeException::outside()` | Runtime | Followed symlink resolves outside the scan root |
| 8710 | `ScanLimitExceededException::depth()` | Runtime | Directory nesting exceeds the limit |
| 8711 | `ScanLimitExceededException::files()` | Runtime | File count exceeds the limit |
| 8720 | `CacheIntegrityException::tampered()` | Runtime | Cache entry decoded to a foreign type (purged first) |
| 8721 | `InsecureCacheDirectoryException::because()` | Configuration | Cache directory is a symlink, world-writable or foreign-owned |
| 8722 | `InsecureCacheDirectoryException::uncreatable()` | Configuration | Cache directory cannot be created |
| 8730 | `CircularDependencyException::chain()` | Runtime | Circular dependency in the class graph |
| 8740 | `InvalidConfigurationException::option()` | Configuration | Option of the wrong type |

Codes 1001–1011 of 1.x map as listed in the CHANGELOG; 1002, 1006, 1007 and 1008 were never raised and are retired.

## §7 — ARFA 1.3 Compliance Matrix

| Principle/Errata | Requirement | Implementation | Verified by |
|---|---|---|---|
| P1 Immutable State | Result objects immutable | All 5 Result classes `final readonly`; `filter()`/`merge()` return new instances | `Conformance/ArfaComplianceTest` |
| P4 Protocol Agnostic | No HTTP/CLI coupling | Zero framework coupling; works in any SAPI | review |
| P5 Continuous Observability | Scan metrics | `scanDuration` on `DiscoveryResult`; per-file error collection | Integration suites |
| E-001 | No serialization shims | Public readonly properties + native `json_encode()`; `SerializableData` deliberately not declared (ADR-008: pure-enum arguments have no JSON form) | `ArfaComplianceTest`, `SpecConformanceTest` |
| E-002 | No property hooks on readonly | Zero occurrences | `ArfaComplianceTest` |
| E-003 | Backed enum only for external contracts | `AttributeTarget: int` mirrors `Attribute::TARGET_*` (ADR-004); `Visibility: string` is the JSON form of the DPOs (E-001) | `SpecConformanceTest` |
| E-004 | Named constructors allowed | `PathNotFoundException::at()` and the other family factories, `ScanCacheKey::of()` | review |
| E-005 | Documentation quality | Substance over volume | review |
| E-006 | Score from automated checks | §10 gate, all measured | `composer quality` |
| E-007 | Hooks only for computed/validation | No hooks | `ArfaComplianceTest` |
| E-008 | Type taxonomy | Results are readonly DPOs; `CycleSearch` is internal algorithm state | review |
| E-009 | Conformance level declared | Level 1 (Structural) — `extra.arfa.level` | `SpecConformanceTest` |
| E-010 | Canonical version | ARFA 1.3 | `SpecConformanceTest` |
| E-011, E-012 | PayloadState / AdaptiveContext | Not applicable (not used) | — |

## §8 — Ecosystem Integration

| Consumer Component | Discovered Attributes | Discovery Method |
|---|---|---|
| KaririCode\Router | `#[Route]`, `#[Middleware]`, `#[Guard]` | `scanForAttributes()` |
| KaririCode\Console | `#[Command]`, `#[Argument]`, `#[Option]` | `scanForAttributes()` |
| KaririCode\DI | `#[Singleton]`, `#[Scoped]`, `#[Tagged]` | `scanForAttributes()` |
| KaririCode\EventDispatcher | `#[EventListener]`, `#[EventSubscriber]` | `scanForAttributes()` |
| KaririCode\WebSocket | `#[WebSocketRoute]`, `#[OnConnect]`, `#[OnMessage]` | `scanForAttributes()` |
| KaririCode\I18n | `#[TranslatableResource]` | `scanForAttribute()` |
| KaririCode\Observability | `#[Traced]` (on methods) | `discover(...)` as a typed closure |

## §9 — Configuration (via ConfiguratorBridge)

Values are validated, not coerced: a wrong type raises `InvalidConfigurationException` (8740). Integers accept numeric strings and booleans accept `"true"`/`"false"`/`"1"`/`"0"`, so environment-sourced values work unchanged.

| Key | Type | Default | Description |
|---|---|---|---|
| `max_file_size` | int | `10485760` | Max file size in bytes (inclusive) |
| `max_files` | int | `10000` | Max files per scan (inclusive) |
| `cache.enabled` | bool | `true` | Memory (L1) → File (L2) cache |
| `cache.dir` | string | `<sys temp>/kariricode_discovery_<uid>` | Per-user default; must be private (§5) |

## §10 — Quality Gate (Spec §29.1, measured locally)

Local evidence, not the verdict (E-006): compliance is declared when the gate passes in CI (`.github/workflows/ci.yml`, same scripts). Status: [QD-V433](../quality/QD-V433-COMPLIANCE.md).

| Check | Command | Result |
|---|---|---|
| Unit + Integration + Conformance | `composer test` | 311 tests, 1,434 assertions, green |
| Static analysis | `composer phpstan` | level max, 0 errors, no suppressions |
| Type coverage | `composer psalm` | errorLevel 1, 0 errors |
| Code style | `composer cs-check` | PER Coding Style, clean |
| Complexity (§13) | `composer phpstan` + `composer complexity` | cognitive ≤ 15, cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4 — pass (readonly DPO constructors exempt, ADR-009) |
| Mutation | `composer infection` | MSI 83 %, covered MSI 85 %, mutation coverage 97 % (gate 80 %) |
| Line coverage | PHPUnit + pcov | 97.75 % lines (825/844), 92.00 % methods |
| Security | `composer security` | no advisories |
