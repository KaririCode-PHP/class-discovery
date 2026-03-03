# ADR-004: Backed Enum for AttributeTarget (ARFA 1.3 E-003)

**Status:** Accepted
**Date:** 2025-12-20
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

ARFA 1.3 V3.2 originally mandated pure enums for all type classifications. Errata E-003 relaxed this:

> *"Backed enums are permitted when external representation is required by the domain — e.g., mapping to a database column, wire format, or native language constant."*

`AttributeTarget` classifies where a PHP attribute was applied. PHP's native `Attribute` class defines integer bitmask constants: `TARGET_CLASS = 1`, `TARGET_METHOD = 2`, `TARGET_PROPERTY = 4`, etc.

## Decision

`AttributeTarget` is a backed enum with `int` values mapping directly to PHP's `Attribute::TARGET_*` constants:

```php
enum AttributeTarget: int
{
    case Class_   = Attribute::TARGET_CLASS;       // 1
    case Method   = Attribute::TARGET_METHOD;      // 2
    case Property = Attribute::TARGET_PROPERTY;    // 4
    case Parameter = Attribute::TARGET_PARAMETER;  // 8
    case ClassConstant = Attribute::TARGET_CLASS_CONSTANT; // 16
    case All      = Attribute::TARGET_ALL;         // 63
}
```

`ScannerType`, which has no external representation, remains a pure enum.

## Consequences

**Positive:**

- Direct interop with PHP's `Attribute::TARGET_*` bitmask via `->value`
- `AttributeTarget::from($reflectionAttribute->getTarget())` works natively
- Bitwise operations possible: `$target->value & Attribute::TARGET_METHOD`

**Negative:**

- Backed enums allow `::from()` / `::tryFrom()` which may hide invalid values

## References

- PHP RFC: Enumerations. https://wiki.php.net/rfc/enumerations
- ARFA 1.3 Errata E-003 — Backed enum relaxation.
