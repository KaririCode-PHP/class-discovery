# ADR-002: Zero External Dependencies

**Status:** Accepted
**Date:** 2025-12-15
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Class discovery is a foundational operation — it runs before the DI container, before the router, before event listeners are registered. Any dependency on external packages introduces:

1. **Circular risk**: If ClassDiscovery depends on Package X, and Package X uses ClassDiscovery for its own bootstrapping, we have a circular dependency.
2. **Version conflict**: Consumer projects may require different versions of shared dependencies.
3. **Attack surface**: Each dependency is a supply-chain vector.
4. **Bootstrap ordering**: Discovery must work before Composer's autoloader finishes loading all packages.

The KaririCode Framework follows a zero-external-dependency policy for core infrastructure components (ARFA 1.3 §4.2).

## Decision

ClassDiscovery depends exclusively on PHP 8.4+ standard library. Integration with external systems (PSR-16 cache, PSR-11 container, Configurator) is provided via optional bridge classes that type-hint `object` with PHPStan annotations.

## Alternatives Considered

- **Depend on mature libraries** (php-parser, symfony/finder). Rejected: a foundation component would impose their versions on every application.
- **Vendor copies of those libraries.** Rejected: unowned code to patch and relicense.
- **No dependency at all, even on the ecosystem contract.** Rejected in 2026-10: Spec V4.3.3 §31 routes coupling through kariricode/contract (addendum).

## Consequences

**Positive:**

- Zero Composer conflicts — installs cleanly in any project
- No supply-chain risk from transitive dependencies
- Bootstrap-safe: works before any autoloader or container is configured
- Package size: minimal (33 files, ~2.8k lines)

**Negative:**

- Bridge classes use `object` typing instead of interface typing for PSR-16/PSR-11
- Must reimplement utilities that packages like `symfony/finder` provide (directory traversal, glob matching)
- Cache implementations cannot leverage existing cache backends without bridge setup

**Mitigations:**

- `@phpstan-param` annotations provide static analysis coverage for bridge classes
- `composer.json` `suggest` section documents optional integrations
- Built-in cache strategies (Memory, File, Chain) cover 95% of use cases without external packages

## Addendum (2026-10) — The ecosystem contract

**Context.** Spec V4.3.3 §31 routes every coupling between KaririCode components through kariricode/contract, and §8.4 requires each exception to implement one of its families so an edge maps failures without knowing the package.

**Decision.** Require `kariricode/contract ^2.11` — interfaces only, no runtime code. Third-party packages stay out of `require`; PSR interfaces remain the other exception: PSR-20 (`psr/clock`, required — cache expiry reads an injectable clock) and PSR-16 (`CacheBridge`, suggested).

**Consequences.** The package has one runtime dependency inside the ecosystem. Contract 2.11.0 must be published before this release (Packagist shows 2.8.6).

## References

- Martin, R. C. (2017). *Clean Architecture*, Ch. 14 — Component Cohesion.
- ARFA 1.3 Specification §4.2 — Zero External Dependencies for Infrastructure Components.
