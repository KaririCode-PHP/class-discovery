# ADR-003: Immutable Discovery Results (ARFA 1.3 P1)

**Status:** Accepted
**Date:** 2025-12-20
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Discovery results are shared across multiple consumers: the DI container reads them to register services, the Router reads them to map routes, the EventDispatcher reads them to find listeners. If any consumer mutates the result, other consumers see corrupted data.

ARFA 1.3 Principle 1 (Immutable State Transformation) mandates:

> *"State objects passed between pipeline stages MUST be immutable. Transformations produce new instances."*

## Decision

All Result objects (`ClassMetadata`, `MethodMetadata`, `PropertyMetadata`, `AttributeMetadata`, `DiscoveryResult`) are `final readonly` classes. `DiscoveryResult::filter()` and `DiscoveryResult::merge()` return new instances.

Public properties are the API — no getters, no `toArray()`, no `jsonSerialize()` (ARFA 1.3 E-001). PHP's native `json_encode()` handles readonly public properties directly.

## Alternatives Considered

- **Mutable result collections with setters.** Rejected: a result shared through the cache could be corrupted by any consumer (ARFA 1.3 P1).
- **Plain arrays.** Rejected: no type safety, and arrays as domain types are a Spec §18 antipattern.

## Consequences

**Positive:**

- Thread-safe by construction: no locks, no defensive copies
- Consumers cannot corrupt shared state
- `filter()` and `merge()` compose safely in pipelines
- `json_encode()` works without custom serialization methods

**Negative:**

- Memory overhead: `filter()` creates a new array (not a view/iterator)
- Cannot incrementally build results (must collect all classes, then construct)

**Mitigations:**

- For 1k-10k classes, array copy overhead is negligible (<1ms, <1MB)
- Builder pattern unnecessary: scanners collect internally, construct once

## References

- ARFA 1.3 Specification §2.1 — P1: Immutable State Transformation.
- ARFA 1.3 Errata E-001 — `jsonSerialize()` = `toArray()` antipattern.
- Bloch, J. (2018). *Effective Java*, 3rd ed., Item 17 — Minimize mutability.
