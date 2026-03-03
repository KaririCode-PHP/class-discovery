# ADR-006: Multi-Tier Cache Strategy

**Status:** Accepted
**Date:** 2025-12-20
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Class discovery is an I/O-heavy operation: reading filesystem, tokenizing files, building metadata. In production, scan results change only on deployment. In development, results change on every file save.

Cache requirements differ across environments:

| Environment | Latency | Persistence | Invalidation |
|---|---|---|---|
| Development | <5ms warm | None (request-scoped) | Automatic |
| Production | <1ms warm | Cross-request | On deploy |
| Testing | Zero overhead | None | Per test |

A single cache strategy cannot serve all three.

## Decision

Implement the Strategy pattern with three built-in implementations and a Chain composite:

1. **MemoryCacheStrategy** — In-memory array, request-scoped. Zero I/O. Development/testing.
2. **FileCacheStrategy** — Serialized PHP on disk with atomic writes. OPcache-friendly. Production.
3. **ChainCacheStrategy** — Multi-tier: checks each tier in order, promotes on miss (L1 miss + L2 hit → write to L1).

Typical production configuration: `Chain(Memory, File)`.

External cache backends (Redis, Memcached) are supported via `CacheBridge` adapter to PSR-16.

## Consequences

**Positive:**

- 15x performance boost in production: <5ms warm vs. 50-100ms cold
- Atomic writes prevent cache corruption under concurrent requests
- Chain promotion avoids repeated L2 reads within the same request
- Strategy pattern allows environment-specific configuration without code changes

**Negative:**

- File cache uses PHP `serialize()` — not human-readable
- `ChainCacheStrategy` has last-write-wins semantics (no distributed lock)
- No automatic invalidation on file changes (manual `clearCache()` or deploy hook required)

**Mitigations:**

- Dev mode uses `MemoryCacheStrategy` (no stale cache risk)
- Deploy scripts call `clearCache()` as a standard step
- `CacheBridge` enables Redis/Memcached with built-in TTL and eviction

## References

- Fowler, M. (2002). *Patterns of Enterprise Application Architecture* — Identity Map, Unit of Work.
- PHP OPcache internals. https://www.php.net/manual/en/book.opcache.php
