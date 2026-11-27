# ADR-008: Metadata DPOs Do Not Implement SerializableData

**Status:** Accepted  
**Date:** 2026-10-04  
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

kariricode/contract 2.11.0 adds `SerializableData` (errata E-001): a marker promising that an
object's public readonly properties *are* its JSON form, nested objects included, and that a
pure enum breaks that promise. The metadata DPOs (`ClassMetadata`, `MethodMetadata`,
`PropertyMetadata`, `AttributeMetadata`) expose public readonly properties, but
`AttributeMetadata::$arguments` holds whatever constant expression the attribute was written
with. In reflection mode that includes pure enum cases (`#[Route(method: Method::Get)]`) and
objects built with `new` in initializers.

## Decision

The metadata DPOs do not implement `SerializableData`. `arguments` keeps the resolved values
ReflectionScanner exists to provide. No `toArray()`/`jsonSerialize()` is added either (E-001,
§18): the DPOs stay JSON-encodable natively whenever their arguments are.

## Alternatives Considered

- **Normalize arguments to a JSON-safe form** (enum case → `Class::Case` string, object →
  class name). Rejected: consumers such as a router read `Method::Get` itself; turning it into a
  string destroys the value and makes token and reflection results differ in kind.
- **Implement the marker anyway.** Rejected: the precondition would be false for a common
  input, and a marker that lies is worse than none.
- **Implement it only on DPOs without arguments.** Rejected: `ClassMetadata` nests
  `AttributeMetadata`, so the promise fails transitively.

## Consequences

- `AttributeMetadata::$instance` (since 1.1.0) holds the attribute object ReflectionScanner built —
  an arbitrary class — which is one more reason the marker could not hold.
- A consumer that needs a JSON export of discovery results maps `arguments` itself.
- The decision is pinned by tests: no metadata DPO declares `SerializableData`, and a pure enum
  argument is shown to have no JSON form.
- Revisit if attribute arguments ever gain a JSON-safe representation in the contract.

## References

- kariricode/contract 2.11.0 — `SerializableData` semantics (KC Contract).
- KaririCode Specification V4.3.3, E-001 and §18.
- PHP RFC: Enumerations — pure enums have no scalar form. https://wiki.php.net/rfc/enumerations
