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

## Alternatives Considered

- **Reflection only.** Loads and executes every scanned file; cannot scan code with missing dependencies or unsafe side effects. Kept as ReflectionScanner, opt-in.
- **AST parser (nikic/php-parser).** Full syntax fidelity, but a runtime dependency (ADR-002) and a much heavier per-file cost for what discovery needs: declarations, names and attributes.
- **Composer classmap.** Only class names; no attributes or members, and stale until the next dump.

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

## Addendum (2026-09) — Declaration-anchored parsing

**Context.** The first implementation walked the token stream once, with extractors that consumed their terminator token. The main loop's increment then skipped that terminator, so an `implements` clause after `extends` was lost, and the class `{` was stepped over — the scan continued into the body, leaking member modifiers (`readonly` of a promoted parameter) and member attributes into class metadata. Static review did not catch it; the first real test run did.

**Decision.** Tokenize with `PhpToken::tokenize()` — the object form of `token_get_all()`, same lexer — and parse in two anchored phases: find the declaration keyword (a `class`/`interface`/`trait`/`enum` token followed by a name), then read the *preamble* (namespace, attributes and modifiers before it, reset at statement boundaries) and the *header* (tokens between the name and the body's `{`). Every extractor returns the index of the last token it consumed.

**Consequences.** Body tokens cannot influence class metadata by construction. Grouped attributes (`#[A, B]`), adjacent groups (`#[A]#[B]`) and repeated attributes are handled; an interface's `extends` list populates `interfaces` (parity with reflection). Names are still recorded as written — `use` resolution remains out of scope for token mode.

## Addendum (2026-10) — Fully qualified names and class bodies

**Context.** Consumers integrate with FQCNs: KC Observability calls `discover(Traced::class, $dirs)`, and `#[Traced]` sits on methods. Recording names as written and reading only class-level attributes made both calls return nothing — silently.

**Decision.** Class-name resolution in PHP happens at compile time, from tokens alone, so token mode applies it exactly (`ImportTable`: imports per namespace, aliases case-insensitive, groups, `namespace\` relative names, no global fallback for classes). `ClassBodyParser` reads members — methods, declared and promoted properties, trait uses — with their resolved attributes. Types stay with reflection.

**Consequences.** Token and reflection modes report the same names; `AttributeFilter(Traced::class)` finds a class through a method attribute without loading it. Callers that filtered by the short name must switch to the FQCN (CHANGELOG 2.0.0).

## References

- Nikic, N. (2014). *PHP Internals — Tokenizer*. https://wiki.php.net/internals/engine/tokenizer
- PHP RFC: Attributes v2. https://wiki.php.net/rfc/attributes_v2
- PHP RFC: Object-based token_get_all() alternative (PhpToken). https://wiki.php.net/rfc/token_as_object
