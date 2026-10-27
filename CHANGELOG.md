# Changelog

All notable changes to `kariricode/class-discovery` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [2.0.0] — 2026-10-27

A major release: it adopts kariricode/contract 2.11.0 (Spec V4.3.3 §31), and changes exception types, error codes and the names token mode records. Upgrade notes: README, "Upgrading from 1.x".

### Breaking

- **Exceptions follow the contract families (§8.4).** `DiscoveryException` is now abstract and implements `KaririCode\Contract\Exception\ExceptionInterface`; each failure is a final class implementing exactly one family. Codes move to the block allocated to this package, 8700–8799 (1000–1499 belongs to AsyncPrimitives in the ecosystem registry):

  | Before | After | Class | Family |
  |---|---|---|---|
  | 1001 | 8701 | `PathNotFoundException` | NotFound |
  | 1003 | 8702 | `SymlinkEscapeException` | Runtime |
  | 1004 | 8710 | `ScanLimitExceededException::depth()` | Runtime |
  | 1005 | 8711 | `ScanLimitExceededException::files()` | Runtime |
  | 1009 | 8720 | `CacheIntegrityException` (tampered entry) | Runtime |
  | 1009 | 8721 / 8722 | `InsecureCacheDirectoryException` (refused / cannot create) | Configuration |
  | 1010 | 8730 | `CircularDependencyException` | Runtime |
  | 1011 | 8740 | `InvalidConfigurationException` | Configuration |

  1002, 1006, 1007 and 1008 were never raised; they are retired, not reallocated. `catch (DiscoveryException)` keeps working.
- **Token mode records fully qualified names.** Attributes, parents, interfaces and traits are resolved with PHP's own rules (imports, aliases, groups, `namespace\` relative names). Filter with `Route::class`, as in reflection mode — not with the short name.
- **Scanners no longer take a `NamespaceResolver`.** It was never consulted on the token path (names come from the file's own `namespace` and imports), and threading it through implied otherwise: `new FileScanner()`, `new AttributeScanner()`, `new DirectoryScanner()`, `new ReflectionScanner()`. `ComposerNamespaceResolver` remains a standalone PSR-4 utility. `ConfiguratorBridge` drops the `composer_json` and `include_dev` options, which configured only that resolver.
- **`Contract\AttributeScanner::discover()`** is a new interface method; custom implementations must add it.
- **Runtime dependencies:** `kariricode/contract ^2.11` and `psr/clock ^1.0` (both interface-only).
- **Analyzer returns value objects (§18).** `DependencyAnalyzer::buildGraph()` returns `DependencyGraph` (`dependenciesOf()`, `contains()`); `detectCircularDependencies(DependencyGraph)` and `CircularDetector::check()` return `list<DependencyCycle>` (`path`, `chain()`).
- **`Integration\PSR11Integration` removed** (Spec §18: a factory that only wraps `new`). Register `Provider\ClassDiscoveryServiceProvider` (contract `DI\ServiceProvider`) instead.
- **`MethodMetadata::$visibility` / `PropertyMetadata::$visibility`** are `Enum\Visibility` instead of strings; the JSON form is unchanged (`"public"`, …).
- **`Enum\ScannerType` removed** — nothing used it, and three of its cases named scanners that do not exist.
- **`ConfiguratorBridge::createScanner()`** returns `Contract\AttributeScanner` (narrower type).
- **Composer metadata:** the ARFA/ASVS declarations live in the canonical `extra.kariricode` block (`component`, `spec`, `arfa`, `asvs`, `error-code-range`).

### Security

- **File cache — object injection (CWE-502).** `unserialize()` runs with an allow-list of the result classes. A tampered entry is purged and raises `CacheIntegrityException` (8720); foreign objects are never instantiated.
- **File cache — shared temp directory (CWE-377).** The cache directory must be private: a symlinked, world-writable or foreign-owned directory is refused with `InsecureCacheDirectoryException` (8721). New directories are created `0700`, temp files come from `tempnam()`, and keys are hashed into file names, so no key can address a path outside the directory.
- **DirectoryScanner — symlink containment (CWE-22).** The check is separator-aware: `/app/src-evil` is no longer accepted as being inside `/app/src`.

### Fixed

- FileScanner lost the `implements` clause whenever `extends` preceded it.
- FileScanner scanned into the class body when `extends`/`implements` were present, leaking member modifiers (a promoted `readonly` parameter made the class `readonly`) and member attributes into class metadata.
- FileScanner captured only `A` from grouped attributes `#[A, B]`, and a `Foo::class` fetch before the declaration could hide the class.
- Cache keys hashed only filter class names: `AttributeFilter('A')` and `AttributeFilter('B')` collided in a shared cache.
- AttributeScanner: the attribute of one `scanForAttribute()` call narrowed every later call on the same instance.
- DirectoryScanner re-added its filters on every scan, drifting the cache key.
- ReflectionScanner dropped traits, and an autoload failure (`\Error`) aborted the scan instead of being reported in `getErrors()`.
- DependencyAnalyzer turned union, intersection, DNF and nullable types into bogus graph nodes (`A|B`, `?Foo`), which hid cycles.
- ComposerNamespaceResolver kept only the last directory of a PSR-4 list, preferred the first matching directory over the deepest, produced `\Foo` for the empty fallback prefix, and resolved non-`.php` files to class names.
- ConfiguratorBridge documented options that do not exist (`ttl`, `paths`).
- README: the `CompositeFilter` example passed a named argument before positional ones (a fatal error in PHP), and the cache examples used constructor parameters that do not exist (`defaultTtl`).

### Changed

- ConfiguratorBridge validates option types and raises `InvalidConfigurationException` (8740) instead of coercing (`'abc'` used to become `0`). Numeric and boolean strings are still accepted.
- The default cache directory is per user: `<sys temp>/kariricode_discovery_<uid>`.
- An interface's `extends` list now populates `interfaces` (parity with ReflectionScanner).
- Token mode flags repeated attributes through `AttributeMetadata::$isRepeated`, under any spelling of the name.
- FileScanner tokenizes with `PhpToken::tokenize()` and parses anchored on the declaration (ADR-001, addendum).
- File cache: a reflection result whose attributes hold an `instance` is recomputed instead of restored — the allow-list cannot include arbitrary attribute classes, and a half-restored instance is never served. The memory cache keeps instances as they are.
- Cache files written by earlier versions are ignored (new file naming) and rebuilt on the first scan.
- Quality tooling follows Spec V4.3.3 instead of KaririCode\Devkit (ADR-007): root-level configuration, explicit dev dependencies, `composer quality`, one CI workflow pinned by commit SHA whose release job publishes the GitHub Release of a `v*` tag after its gate passes.
- The repository moved to [KaririCode-PHP/class-discovery](https://github.com/KaririCode-PHP/class-discovery); GitHub redirects the old address.

### Added

- `AttributeScanner::discover(string $attribute, array $paths): list<string>` — the class-finder shape consumed through typed closures (KC Observability: `'discovery' => $scanner->discover(...)`).
- Token mode reads class bodies: methods, declared and promoted properties and trait uses, each with its resolved attributes — a class is found by an attribute on a method (`#[Traced]`, `#[Route]` on an action) without loading it. Declared types stay with ReflectionScanner.
- `Provider\ClassDiscoveryServiceProvider` — binds the scanners, analyzer and detector lazily in any `Contract\DI\BindableContainer`.
- PSR-20 clock for cache expiry: `MemoryCacheStrategy` and `FileCacheStrategy` take an optional `ClockInterface` (default `Cache\SystemClock`), so expiry is testable and follows the application's clock.
- A usage example in every public type (Spec §17, C4).
- Supply chain (Spec §24/§32): `composer.lock` is committed, `composer security` audits the locked set, `composer sbom` writes a CycloneDX 1.5 SBOM; `SECURITY.md` with threat model and ASVS 5.0 scope.
- Quality gate per Spec §29.1: `composer quality` runs PER-CS, PHPStan (level max), Psalm (errorLevel 1), complexity budgets, PHPUnit, Infection, the dependency audit and the SBOM.
- `phpunit.xml.dist` with Unit, Integration and Conformance suites; the Conformance suite checks the Spec §29.2 and ARFA 1.3 rules.
- Documentation: SPEC-001, ADR-001 to ADR-009, `docs/quality/QD-V433-COMPLIANCE.md`, `docs/README.md`, review log.
- Internal `TokenStream`, `ImportTable`, `ClassBodyParser`, `ScanCacheKey` and `CycleSearch` (`@internal`, not part of the public API).

### Decided

- Metadata DPOs do not declare `SerializableData` (ADR-008): pure-enum arguments and attribute instances have no JSON form, and normalizing them would destroy resolved values.
- Readonly promoted constructors are exempt from the four-parameter budget, visibly (ADR-009; proposed as a §13 erratum).
- Error-code block 8700–8799 is final.

### Removed

- `Integration\PSR11Integration` and `Enum\ScannerType` (see Breaking).
- `devkit.php`, the `kcode` Composer scripts, and the `code-quality.yml` / `release.yml` workflows (merged into `ci.yml`).

## [1.1.0] — 2026-03-04

### Added

- `AttributeMetadata::$instance`: ReflectionScanner stores the instantiated attribute object.

### Changed

- PHPStan level 9 without a baseline (five inline suppressions).
- CI adjusted to PHPUnit 12 coverage warnings and the Devkit-generated configuration.

## [1.0.0] — 2026-03-03

### Added

- First release: token-based `FileScanner`, `AttributeScanner`, `DirectoryScanner` and `ReflectionScanner`; PSR-4 `ComposerNamespaceResolver`; attribute, interface, namespace, structural and composite filters; memory, file and chain caches with a PSR-16 bridge; dependency graph and circular-dependency detection; immutable result objects (ARFA 1.3).

[2.0.0]: https://github.com/KaririCode-PHP/class-discovery/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/KaririCode-PHP/class-discovery/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/KaririCode-PHP/class-discovery/releases/tag/v1.0.0
