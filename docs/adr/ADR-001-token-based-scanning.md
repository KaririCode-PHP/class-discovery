# ADR-001: Token-Based Scanning Over Reflection

**Status:** Accepted
**Date:** 2025-12-15
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Class discovery requires parsing PHP files to extract metadata: class names, namespaces, attributes, interfaces, parent classes. Two approaches exist:

1. **Reflection-based**: Load the class via autoloader, then use `ReflectionClass` for rich introspection.
2. **Token-based**: Parse PHP tokens via `token_get_all()` without loading or executing the class.

Reflection provides richer data (resolved attribute arguments, complete type hierarchy) but requires:

- A functional autoloader
- All dependencies loaded (transitive)
- Code execution risk (static initializers, `require_once` side-effects)
- 10x slower due to class loading overhead

Many KaririCode ecosystem consumers (Router, Console, DI, EventDispatcher) need only class-level metadata — FQCN, namespace, attributes, interfaces — which token parsing provides without class loading.

## Decision

Use `token_get_all()` as the primary scanning engine (`FileScanner`). Provide `ReflectionScanner` as an opt-in alternative for cases requiring fully resolved attribute arguments.

## Consequences

**Positive:**

- 10x performance gain: 30-80ms for 1k classes (token) vs. 300-800ms (reflection)
- Zero class loading: scanned code never executes, eliminating side-effect risk
- No autoloader dependency: works on codebases before autoloader is configured
- Attribute discovery via `T_ATTRIBUTE` token matches without instantiation

**Negative:**

- Attribute constructor arguments are not resolved (token scanning sees syntax, not runtime values)
- Union/intersection types are captured as strings, not resolved `ReflectionType` objects
- Anonymous classes require lookback heuristics (`T_NEW` check)

**Mitigations:**

- `ReflectionScanner` available when resolved arguments are needed
- `AttributeScanner` composing `FileScanner` covers 95% of ecosystem use cases
- Performance characteristics documented in `Scanner` contract

## References

- Nikic, N. (2014). *PHP Internals — Tokenizer*. https://wiki.php.net/internals/engine/tokenizer
- PHP RFC: Attributes v2. https://wiki.php.net/rfc/attributes_v2
