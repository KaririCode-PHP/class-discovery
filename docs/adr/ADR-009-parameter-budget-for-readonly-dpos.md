# ADR-009: Parameter Budget and Readonly DPO Constructors

**Status:** Accepted (proposed as a §13 erratum)  
**Date:** 2026-10-04  
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Spec §13 budgets methods at four parameters. Five constructors exceed it, and all are
promoted constructors of `final readonly` classes: `ClassMetadata` (16), `PropertyMetadata`
(8), `MethodMetadata` (7), `StructuralFilter` (6), `AttributeMetadata` (5). In such a class the
constructor *is* the data shape, and §28 asks for named arguments from three parameters on.

## Decision

`tools/complexity-budget.php` reports these constructors as exemptions (visible notes, not
silent passes) when the class is `readonly` and every parameter is promoted. Every other method
keeps the four-parameter budget. The rule is proposed to the specification as an erratum to §13.

## Alternatives Considered

- **Parameter Object.** Rejected: it relocates the same fields into a second readonly class
  and adds an indirection to every construction.
- **Builders.** Rejected: mutable construction for immutable data, and an indirection §18
  lists as an antipattern when it only wraps `new`.
- **Enforce the budget anyway.** Rejected: it would force one of the two above for no gain.

## Consequences

- The exemption is mechanical and narrow; a non-promoted or non-readonly constructor is still
  a violation.
- Setting `EXEMPT_READONLY_PROMOTED_CONSTRUCTORS` to `false` restores strict enforcement.

## References

- KaririCode Specification V4.3.3, §13 (complexity budgets) and §28 (named arguments).
- Fowler, M. *Refactoring* (2nd ed.) — Introduce Parameter Object, and when it does not pay.
