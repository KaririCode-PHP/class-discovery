# ADR-005: Composition Over Inheritance for Scanner Variants

**Status:** Accepted
**Date:** 2026-02-28
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

The initial design had `AttributeScanner extends FileScanner`, with `FileScanner` exposing three `protected` methods (`parseFile`, `extractAttribute`, `collectPhpFiles`) forming an implicit extension API.

Code review (2026-02-28) identified three problems:

1. **Fragile base class**: Any change to `FileScanner`'s protected methods could break `AttributeScanner` silently.
2. **No-op override**: `AttributeScanner::parseFile()` read the file twice (once for a `str_contains('#[')` check, once via `parent::parseFile()`) and both branches executed identical code — the "early-exit optimization" was dead code.
3. **Prevents sealing**: `FileScanner` could not be `final`, leaving its internals exposed.

`DirectoryScanner` already used composition (delegating to `FileScanner`), making the inheritance in `AttributeScanner` inconsistent.

## Decision

Refactor `AttributeScanner` to compose `FileScanner` (same pattern as `DirectoryScanner`). Make `FileScanner` `final` with all methods `private`.

**Before:**
```
FileScanner (class, 3 protected methods)
  └── AttributeScanner (extends)
DirectoryScanner (composes FileScanner)
ReflectionScanner (composes FileScanner)
```

**After:**
```
FileScanner (final class, all private)
  ├── AttributeScanner (composes)
  ├── DirectoryScanner (composes)
  └── ReflectionScanner (composes)
```

## Alternatives Considered

- **`AttributeScanner extends FileScanner`** (the 1.x design). Rejected: fragile base class; it produced the double-read defect BUG-006.
- **Shared traits.** Rejected: hidden coupling to private state, untestable in isolation.

## Consequences

**Positive:**

- `FileScanner` is `final` — no unintended subclassing
- All internal methods are `private` — no implicit extension API
- Eliminates the double file read (BUG-006)
- Consistent composition pattern across all scanner variants
- Each scanner variant can evolve independently

**Negative:**

- `AttributeScanner` cannot override `parseFile` for future token-level optimizations
- Slight indirection: `AttributeScanner.scan()` delegates to `FileScanner.scan()`

**Mitigations:**

- Token-level optimizations can be added to `FileScanner` itself (configurable via constructor flags)
- Delegation overhead is negligible (one method call per scan)

## References

- Gamma, E. et al. (1994). *Design Patterns*, Ch. 1 — "Favor object composition over class inheritance."
- Bloch, J. (2018). *Effective Java*, 3rd ed., Item 18 — Favor composition over inheritance.
