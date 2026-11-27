# ClassDiscovery V3.2 — Test Suite Deep Review

Date: 2026-02-28
Reviewer pass: static cross-check (no PHP runtime in sandbox; network disabled)

## Severity legend
- **P0** — test will fail or fatal-error when run
- **P1** — test passes but validates the wrong thing / masks a real defect
- **P2** — robustness / strictness / hygiene

---

### TEST-001 (P0) — `DiscoveryExceptionTest`: invalid dynamic static call
`DiscoveryException::$method(...$args)` parses as *static property* access (`::$method`),
not a dynamic method call. `$method` is not a static property → fatal error on every
data-provider row.
**Fix:** `DiscoveryException::{$method}(...$args)`.

### TEST-002 (P0) — `DiscoveryExceptionTest`: `@dataProvider` doc-comment
PHPUnit 11 deprecates doc-comment metadata; combined with `failOnWarning="true"` this can
fail the suite.
**Fix:** use the `#[DataProvider('namedConstructorProvider')]` attribute.

### TEST-003 (P0) — Fixture `AttributedClass.php` declares 4 classes in one file
`FileScanner::parseFile()` stops at the first class-body `{`, so it returns metadata for the
*first* class only (`SampleAttribute`). `AttributedClass` (4th in the file) is never
discovered, so `AttributeScannerIntegrationTest::testScanForAttributeFindsAnnotatedClass`
can never find it → fails. Also violates PSR-4 (one class per file).
**Fix:** split into `SampleAttribute.php`, `MethodAttribute.php`, `PropertyAttribute.php`,
`AttributedClass.php` (one class each).

### TEST-004 (P0) — Attribute name matching: FQCN vs source token
The token scanner records the attribute identifier *as written in source* (short name,
e.g. `SampleAttribute`), not a resolved FQCN. The integration tests filtered by FQCN
(`KaririCode\…\SampleAttribute`) → `ClassMetadata::hasAttribute()` lookup misses → fails.
**Fix:** filter by the short identifier the scanner actually stores. Documented as a known
scanner limitation (no `use`-alias resolution in token mode).

### TEST-005 (P1) — Integration tests inject a throwaway `NamespaceResolver`
`FileScanner` derives the namespace from source tokens and **never calls the injected
`NamespaceResolver`** (the dependency is dead in the parse path). The custom anonymous
resolver in the integration tests implies it drives resolution; it does not. Tests pass only
because every fixture self-declares the matching namespace.
**Fix:** use the real `ComposerNamespaceResolver` (project `composer.json`, dev autoload) and
add a comment that namespaces come from tokens. Separately flagged as a source design
observation (see SRC-001).

### TEST-006 (P2) — `testSymlinksNotFollowedByDefault` pollutes the fixture dir
Creates `tests/Fixture/link_test`; broken-symlink cleanup via `file_exists()` misses dangling
links (returns false for them) → flaky on reruns.
**Fix:** build an isolated temp directory with a real `.php` file + symlink; clean up via
`is_link()`.

### TEST-007 (P2) — `FileScannerIntegrationTest::testScanWithFilter` may be vacuous
If the filtered set were empty the loop would assert nothing → `failOnRisky="true"` marks it
risky.
**Fix:** assert `count() > 0` before the per-item loop.

### TEST-008 (P2) — `phpunit.xml` `beStrictAboutCoverageMetadata="true"`
Forces `#[CoversClass]`/`#[CoversNothing]` on every test under coverage runs; otherwise risky.
Adds friction with no value here.
**Fix:** remove the attribute.

---

## Source observation (not a test bug)

### SRC-001 — `FileScanner` injects `NamespaceResolver` but never uses it
Token-based namespace extraction is authoritative and correct, so the resolver has no correct
call site in a parse-based scanner. The parameter is retained across the scanner API
(`FileScanner`, `AttributeScanner`, `DirectoryScanner`, `ReflectionScanner`) and the ecosystem
(`ConfiguratorBridge`, `PSR11Integration`). Removing it is an ecosystem-wide breaking change,
so this pass only **documents** it; the decision to drop it is deferred to the maintainer.

---

## Second review pass (post-correction)

A fresh adversarial pass over the corrected suite. Confirmed all eight first-pass
corrections hold and the suite is structurally sound (balanced braces, valid headers,
all imports resolve, every test extends `TestCase`). One additional test defect was
found and fixed, plus two latent source observations.

### TEST-009 (P0) — `CompositeFilterTest`: positional argument after named argument
`CompositeFilter::__construct(bool $requireAll = false, ClassFilter ...$filters)` takes a
scalar followed by a variadic. Four tests called it as
`new CompositeFilter(requireAll: false, new NamespaceFilter(...), ...)` — a **named argument
followed by positional arguments**, which is a fatal `Error` in PHP
("Cannot use positional argument after named argument"). Four of five `CompositeFilter`
tests would have fatally errored before reaching any assertion.
**Fix:** pass `$requireAll` positionally (`new CompositeFilter(false, new NamespaceFilter(...), …)`).
The two zero-filter call sites (`new CompositeFilter(false)`) were already valid and were
normalised to positional for consistency. A whole-suite sweep confirmed no other
named-then-positional call sites exist.

## Additional source observations (not test bugs)

### SRC-002 — `AttributeScanner::scanForAttribute()` accumulates filters across calls
Each call does `$this->addFilter(new AttributeFilter(...))` on the persistent inner
`FileScanner`. Calling `scanForAttribute('A')` then `scanForAttribute('B')` on the same
instance leaves **both** filters active, so the second scan returns classes matching A *and*
B (FileScanner requires all filters to pass) rather than B alone. Not triggered by the suite
(every test uses a fresh scanner). **Status: FIXED in the fourth pass** — each call builds its own engine.

### SRC-003 — `DirectoryScanner::scan()` re-adds filters to its inner `FileScanner`
On every `scan()` the loop re-applies `$this->filters` to the persistent inner `FileScanner`.
Repeated `scan()` calls on the same instance duplicate the filters and drift the FileScanner
cache key (filter class names participate in the key), defeating the cache on subsequent
runs. Filters are idempotent so results stay correct, but the duplication is wasteful. Not
triggered by the suite (each test scans once). **Status: FIXED in the fourth pass** — a FileScanner is built per scan.

## Verdict
Test suite is correct as far as static analysis can establish. Remaining items (SRC-001/002/003)
are source-level design decisions, not test defects, and are left to the maintainer. The suite
has not been executed (no PHP runtime in this environment); run `composer install && vendor/bin/phpunit`
locally to confirm green.

---

## Third review pass — ARFA 1.3 / KaririCode conformance

Audited the suite against the component's own conformance criteria (SPEC-001 §7) and the
KaririCode code-quality conventions, in addition to another correctness sweep.

### Conformance of the test code (all PASS)
- `declare(strict_types=1)` in every file; every test class `final`; every method typed.
- No external dependencies — only PHPUnit, KaririCode symbols, and the PHP-core `Attribute`
  class (attribute definitions + `TARGET_*` constants), which is not a package dependency.
- Fatal-level sweep: no named-argument-before-positional call sites remain; all variadic
  constructors (`CompositeFilter`, `ChainCacheStrategy`, `AttributeFilter`) are called
  positional-safe.

### TEST-010 (P2) — ARFA principles were not executably verified
The suite exercised behaviour but never asserted the ARFA guarantees the component advertises
for its Result layer. Added `tests/Unit/ArfaComplianceTest.php` (data-driven over all five
Result classes):
- **P1** — each Result class is `final` and `readonly`.
- **E-001** — no `toArray()`/`jsonSerialize()` shim; `JsonSerializable` not implemented; the
  four public DPOs json-encode straight from their public readonly properties.
- **E-002** — no property hooks on any Result-class property (`ReflectionProperty::hasHooks()`).

### Source conformance confirmed (no change needed)
- E-001: `src/Result/*` declares no `toArray`/`jsonSerialize` and implements no
  `JsonSerializable` (the only textual hit is an explanatory docblock).
- E-002: no property hooks anywhere in `src/Result/`.
- P1: all five Result classes are `final readonly`; `filter()`/`merge()` return new instances.
- E-003: `AttributeTarget: int` (backed, justified); `ScannerType` pure — covered by the enum tests.

No new correctness defects were found in this pass.

---

## Fourth pass — executed quality gate (2026-09)

The first three passes were static cross-checks: no PHP runtime was available.
This pass ran the real toolchain — PHP 8.4.23, PHPUnit 11.5, PHPStan 2 (level max),
Psalm 6 (errorLevel 1), PHP-CS-Fixer (PER-CS), Infection 0.29 with pcov — against the
KaririCode Specification (§13 budgets, §28, §29 gate) and ARFA 1.3.

**Candid note.** The static passes declared the suite correct. The first real run failed
one test, and that failure exposed a P0 in `src/` that all three passes had missed.
Mutation analysis then exposed further defects and weak tests. Static review has limits;
every number below was measured.

### Source findings (all fixed, each pinned by a regression test)

| ID | Sev. | Finding |
|---|---|---|
| SRC-004 | P0 | FileScanner extractors consumed their terminator token: `implements` was lost after `extends`, and the class body was scanned — member `readonly`/attributes leaked into class metadata. Rewritten on `PhpToken::tokenize()`, anchored on the declaration (ADR-001 addendum). |
| SEC-001 | P0 | File cache `unserialize()` without restrictions, under the shared temp dir (CWE-502). Allow-list; a gadget object is proven never to wake. |
| SEC-002 | P0 | Symlink containment by plain prefix: `/app/src-evil` passed as inside `/app/src` (CWE-22). Separator-aware check. |
| SRC-005 | P0 | Cache key hashed filter class names only: `AttributeFilter('A')`/`('B')` collided. `ScanCacheKey` includes filter configuration. |
| SEC-003 | P1 | Predictable temp file, 0755 directory, key-to-path traversal. `tempnam()`, 0700, hashed file names; insecure directories refused (1009). |
| SRC-006 | P1 | Grouped attributes `#[A, B]` kept only `A`; `Foo::class` before the declaration could hide the class. |
| SRC-007 | P1 | ReflectionScanner dropped traits; autoload `\Error` aborted the scan. |
| SRC-008 | P1 | DependencyAnalyzer made `A\|B` and `?Foo` graph nodes — cycles through them were invisible. |
| SRC-009 | P1 | ConfiguratorBridge coerced invalid options (`'abc'` → 0). Validated; new code 1011. |
| SRC-010 | P1 | ComposerNamespaceResolver: PSR-4 lists kept only the last directory; first match beat the deepest; empty prefix gave `\Foo`; non-`.php` files resolved. |
| SRC-002/003 | P1 | Filter accumulation across calls / scans — fixed (see above). |

### Documentation findings (fixed)

| ID | Finding |
|---|---|
| DOC-001 | README's headline example `scanForAttribute(Route::class)` returns nothing in token mode when the source uses `use` imports — names are recorded as written. Example and a mode note corrected. |
| DOC-002 | Performance figures in SPEC/README were never measured (Spec E-037 forbids unbenchmarked numbers). Replaced with medians measured on 1,000 generated classes. |
| DOC-003 | Codes 1002, 1006, 1007, 1008 are defined but never raised — now marked *reserved*. |

### Test-suite findings (fixed)

- TEST-011 — assertions the type system already proves (e.g. `assertInstanceOf(Scanner::class, …)` on a `Scanner` return type, constant-folded enum checks) could never fail. Replaced by behavioral assertions or moved to the Conformance suite. `testUniqueErrorCodes` checked a hard-coded array; the Conformance suite now invokes every factory and checks the declared range.
- TEST-012 — regression proof: run against the previous `src/`, the 24 new regression tests produced 23 failures and 1 error.
- A dead guard (`::class` check in `isDeclaration()`) was removed: its mutants were unkillable because the name check already rejects every `::class` fetch.

### Gate (Spec §29.1), measured

| Check | Before this pass | After |
|---|---|---|
| PHPUnit | 177 tests, 1 failure | 257 tests, 1189 assertions, green |
| PHPStan level max | 52 src + 336 tests | 0 + 0 (one suppression: SRC-001) |
| Psalm errorLevel 1 (src) | 49 | 0 |
| PER-CS | 27 files | clean |
| Cognitive ≤ 15 | 3 violations | 0 |
| Cyclomatic ≤ 10 / nesting ≤ 3 / params ≤ 4 | 3 real violations | 0 (5 DPO constructors under DEC-002) |
| Mutation (MSI / covered) | 76 % / 78 % | 84 % / 86 % |
| Line coverage | not measurable | 96.5 % |

### Open decisions (maintainer)

- **SRC-001** — `NamespaceResolver` stays unused on the token path (the one PHPStan suppression).
- **DEC-002** — Spec §13 "≤ 4 parameters" vs. readonly DPO constructors (ClassMetadata has 16). Applied as a *visible* exemption in `tools/complexity-budget.php` (reported as notes); set `EXEMPT_READONLY_PROMOTED_CONSTRUCTORS` to `false` to enforce.
- **DEC-003** — should token mode resolve `use` imports so it records FQCNs like reflection mode? Today names are recorded as written; changing that alters identifiers for every consumer.
- **Release** — CHANGELOG is *Unreleased*; 3.3.0 is suggested because of the behavior changes listed there.

### Not verified here

- Windows path handling (`\` normalization in the resolver) — no Windows runner.
- Memory-cache expiry — no clock abstraction; ~10 mutants in `MemoryCacheStrategy` survive for that reason.
- Foreign-owner rejection of the cache directory — the suite runs as root, so only the world-writable and symlink branches are exercised.

---

## Fifth pass — ecosystem alignment, step 1 (2026-10)

Cross-chat audit against kariricode/contract 2.11.0 and Spec V4.3.3 (KC Contract, KC Cache,
KC Console, KC Observability, KC DependencyInjection), then step 1 of the resulting plan.

### Findings that drove step 1

| ID | Sev. | Finding | Resolution |
|---|---|---|---|
| ECO-001 | P0 | KC Observability calls `discover(Traced::class, $dirs)`; no published or local version had `discover()`, and token mode recorded names as written, so FQCN filters returned nothing. `#[Traced]` sits on methods, which token mode did not read. | `discover()` on `AttributeScanner` and its contract; `ImportTable` resolves names like PHP; `ClassBodyParser` reads members and their attributes (DEC-003 closed). |
| ECO-002 | P0 | Exceptions implemented no contract family (§8.4). | Abstract `DiscoveryException` + 7 final exceptions, one family each; conformance rule added. |
| ECO-003 | P0 | Codes 1001–1011 sat inside AsyncPrimitives' block (1000–1499, KC Cache registry). | Block 8700–8799; old codes mapped in the CHANGELOG; never-raised codes retired. |
| ECO-004 | P1 | Legacy `extra.arfa` metadata. | Canonical `extra.kariricode` (component, spec 4.3.3, arfa, asvs, error-code-range). |
| ECO-005 | P1 | One code (1009) with two meanings. | Split: 8720 tampered entry (Runtime) / 8721–8722 cache directory (Configuration). |

### Provenance note

Part of this step — the split of the token engine into `TokenStream`, `ImportTable` and
`ClassBodyParser`, their tests, and the removal of the temporary path repository from
`composer.json` — was written in a continuation of the session that is not in the visible
transcript. It was audited before being kept: full gate re-run, no dead code left in
`FileScanner`, and the behavior is pinned by `ImportTableTest` and `ClassBodyParsingTest`.

### Conformance rule clarified

§18 "inheritance depth ≤ 2" counts the package's own ancestors only. PHP's built-in
exception chain (`\RuntimeException` → `\Exception`) is stable and not a fragile base
class; the sibling components use the same shape (abstract base + final families).

### Still open (steps 2–3)

Supply chain (§24/§32: committed lock, `composer audit --locked`, SBOM, SHA-pinned CI,
`SECURITY.md`), a `Contract\DI\ServiceProvider` replacing `PSR11Integration`, §17
documentation (C4 examples, docblock tags, ADR "Alternatives Considered"), §18 items
(`visibility` as string, array returns in the Analyzer, unused `ScannerType`), PSR-20 clock,
and the `SerializableData` decision.

---

## Sixth pass — ecosystem alignment, steps 2 and 3 (2026-10)

### Step 2 — supply chain and container contract

- **ECO-006** `Integration\PSR11Integration` (factories that only wrap `new`, §18) replaced by
  `Provider\ClassDiscoveryServiceProvider` implementing contract `DI\ServiceProvider`.
  `register()` only binds — proven with an invalid option that fails on the first `get()`,
  never during registration.
- **ECO-007** §24/§32: lock committed (no longer ignored), `composer security` =
  `composer audit --locked`, `composer sbom` (CycloneDX 1.5, `tools/sbom.php`), CI with
  actions pinned by commit SHA (resolved with `git ls-remote`, not typed from memory),
  `SECURITY.md` (threat model; ASVS 5.0 chapters checked against the OWASP index).
- **ECO-008** E-006: `docs/quality/QD-V40-COMPLIANCE.md` with status PENDING; local results
  are labeled as such in SPEC §10 and the README.

The committed lock itself waits for kariricode/contract 2.11.0 on Packagist: a lock resolved
through a local path repository would be wrong for everyone else.

### Step 3 — §17 and §18

- `visibility` is `Enum\Visibility` (backed by the keyword: the JSON form is the external
  contract, E-001/E-003); `ScannerType` removed (unused, speculative cases).
- `@package`, `@author`, `@license`, `@since` removed from 18 files (they restate the
  namespace, git history and LICENSE); "Alternatives Considered" added to the six ADRs.

### Completed afterwards (same pass)

- **§18** — the Analyzer returns `DependencyGraph` and `list<DependencyCycle>` instead of raw arrays;
  the cycle message is built by `DependencyCycle::chain()`.
- **PSR-20** — both caches take a `ClockInterface`; expiry is now tested at its boundary
  (served at exactly the TTL, purged one second later) and for a zero TTL ten years on.
  This closes the "memory-cache expiry not verified" item of the fourth pass.
- **§17 C4** — every public type carries a usage example, written against the 2.0.0 API.

### Provenance note (second occurrence)

The step-2 and step-3 work listed above under "Step 2" and "Step 3" (provider, CI, SBOM,
`SECURITY.md`, QD document, `Visibility`, `ScannerType` removal, docblock tags, ADR
alternatives) appeared in the working copy from a session continuation that is not in the
visible transcript. It was audited before being kept: full gate; CI action SHAs matched an
independent `git ls-remote` resolution; the SBOM validated as CycloneDX 1.5 with the contract as
the only runtime component; the provider binds lazily; `Visibility` is backed, so DPO JSON
stays native. One overstatement was corrected (the CHANGELOG said the lock was committed — the
lock resolved here points at a local path and must not ship).

### Still open

- `SerializableData` on the metadata DPOs — needs a decision (reflection arguments may hold
  pure enums, which break the marker's precondition).
- Mutation hardening of `ClassBodyParser` and `TokenStream`.
- First CI run, signed tag, and the lock — all wait for kariricode/contract 2.11.0 on Packagist.

---

## Seventh pass — decisions delegated by the maintainer (2026-10)

The maintainer delegated the open decisions ("choose, decide and do"). Each was taken on the
evidence gathered in the previous passes and recorded where it will be found later.

| Item | Decision | Record |
|---|---|---|
| `SerializableData` | Not declared by the metadata DPOs: pure-enum arguments have no JSON form, and normalizing them would destroy the resolved values. Pinned by two conformance tests. | ADR-008 |
| SRC-001 | Resolver removed from every scanner constructor in 2.0.0 — dead parameter, misleading API, and the project's only PHPStan suppression (now zero). `ComposerNamespaceResolver` stays as a utility. | CHANGELOG |
| DEC-002 | Readonly promoted constructors exempt from the parameter budget, visibly; proposed as a §13 erratum. | ADR-009 |
| Error block | 8700–8799 final. | SPEC §6 |

### Self-inflicted measurement errors in the sixth pass (for the record)

- An Infection run was invalidated by editing source docblocks while it ran: line numbers
  shifted under the coverage map (MSI read 55 %, mutation coverage 70 %). Rule adopted: no edits
  to `src/` or `tests/` while a mutation run is in flight.
- `pkill -f`/`pgrep -f` with the pattern in the same script matched the tool's own shell.
  Processes are now selected by binary name (`ps -C php8.4`).

### Measured after the seventh pass (source frozen during the run)

- PHPUnit: 299 tests, green; PHPStan max 0 errors with no suppressions; Psalm level 1: 0.
- Infection: 1,100 mutants, MSI 82 %, covered MSI 84 %, mutation coverage 97 %.
  `ClassBodyParser` + `TokenStream` were at 71 % in isolation; `ClassBodyMembersTest` pins
  the behaviours their survivors exposed (attribute groups, separators, multi-property
  declarations, case-insensitive constructors, readonly classes, trait blocks, truncation).
- Line coverage: 97.26 % (817/840).

---

## Eighth pass — reconciliation with the published 1.1.0 and release preparation (2026-10-27)

Asked to push and release, this pass compared the 2.0.0 tree with what is actually published
(`v1.1.0`, repository now at `KaririCode-PHP/class-discovery`) instead of overwriting it, and
re-ran the gate against the real kariricode/contract 2.11.0 instead of a local stand-in.

### Findings

| ID | Finding | Resolution |
|---|---|---|
| REL-001 | 1.1.0 shipped `AttributeMetadata::$instance` (ReflectionScanner instantiates attributes); the 2.0.0 tree, built from the February code, did not have it — releasing would have silently removed a published feature. 1.1.0 had no test for it. | Ported, with the first tests: instances on every target, an attribute that cannot be instantiated (TARGET_CLASS on a method) keeps its arguments, token mode never instantiates. The file cache treats an incomplete instance as a miss. |
| REL-002 | Our README would have replaced the 1.1.0 README and its six real-world use cases. Three published examples never worked: `CompositeFilter(requireAll: false, …)` (named before positional — a fatal error), `defaultTtl` constructor arguments that do not exist, and `FileScanner` + `AttributeFilter(Route::class)`, empty whenever the code used `use`. | The 1.1.0 structure and use cases kept, every example rewritten against the 2.0.0 API; "Upgrading from 1.x" added. |
| REL-003 | 1.1.0 behaviours absent from 2.0.0: `@mkdir` (an uncreatable cache directory must fail closed without a PHP warning) and `@file_get_contents` (unreadable `composer.json`). | Ported, with tests (8722; unreadable file — skipped as root, runs in CI). |
| REL-004 | Resolver scenarios tested in 1.1.0 and not in 2.0.0: invalid JSON, missing `autoload`, unreadable file, nothing found walking up. | Ported; the walk-up test now asserts the project's `composer.json` was not found instead of only "no crash". |
| REL-005 | `clearCache()` with a configured cache was untested for AttributeScanner and DirectoryScanner. | Tests added. |
| REL-006 | CHANGELOG entries had gone stale across passes: codes 1009/1011 in Security and Changed, `createNamespaceResolver()` (removed in the seventh pass) under Added, `extra.arfa` under Added. | Corrected; 1.0.0 and 1.1.0 sections added from the git history (the readonly services and `#[CoversClass]` were already in 1.0.0). |
| REL-007 | The 1.x CI downloaded `kcode.phar` from the Devkit's *latest* release and patched its output with `sed`; actions were pinned by tag. | ADR-007: Spec toolchain; one SHA-pinned workflow with a dependency review and a release job (notes from the CHANGELOG, only after the gate passes on the tag). Every action SHA was checked with `git ls-remote`. |

### Verified against the real contract

Contract 2.11.0 is committed on `main` (62dea5b) but not tagged. `DI\ServiceProvider`,
`DI\BindableContainer` and the exception families match what this package implements and calls;
a factory given to `bind()` receives the container (contract docblock; dependency-injection
implementation). The whole suite ran against it.

### Environment (not committed)

The session's proxy refuses `api.github.com`/`codeload.github.com` for repositories outside the
session, so Composer installed from git mirrors; `phpstan/phpstan` (a PHAR versioned in git) was
provided by a shallow clone of the locked tag 2.3.3, and the contract by its `main` checkout — both
as global path repositories. The resulting `composer.lock` points at local paths and is not part of
the commit; it is regenerated after the contract tag (QD-V433 §2).

### Gate, measured

311 tests and 1,434 assertions green (one skipped as root); PHPStan max and Psalm level 1: 0 errors,
no suppressions; PER-CS clean; §13 budgets met; no advisories; SBOM with two runtime components
(kariricode/contract, psr/clock); line coverage 97.75 %. Mutation score: see SPEC §10.
