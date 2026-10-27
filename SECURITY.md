# Security Policy — kariricode/class-discovery

## Reporting

Report vulnerabilities privately to walmir.silva@kariricode.org, not through public issues.
Include the affected version, a reproduction and the impact you observed.
Security fixes target the latest release line.

## Scope and level

ASVS 5.0, **Level 1** (`extra.kariricode.asvs`). The package has no network, authentication
or session surface; it reads source files and writes a cache. The applicable chapters are
V1.5 Safe Deserialization, V5 File Handling (V5.3 File Storage) and V16.5 Error Handling.

## Threat model

Assets: the integrity of the host process and of the files the cache writes.
Trust boundaries: scanned source (data in token mode, executed code in reflection mode),
the cache directory, and configuration values.

| Threat | Control | Evidence |
|---|---|---|
| Code execution while scanning | Token mode never `require`s scanned files | ADR-001 |
| Code execution in reflection mode | Autoloading executes files: `ReflectionScanner` is for trusted code only | SPEC-001 §5 |
| Symlink escape (CWE-22) | Followed links must resolve to the root or below `root/`, separator-aware; fail closed with 8702 | `DirectoryScannerIntegrationTest` |
| Resource exhaustion | Inclusive bounds on file size, file count (8711) and depth (8710) | `FileScannerIntegrationTest` |
| Object injection through the cache (CWE-502) | `unserialize()` restricted to an allow-list of result classes — ASVS v5.0.0-1.5.2 | `FileCacheStrategyTest` (a gadget is proven never to wake) |
| Cache tampering in a shared directory (CWE-377) | Directory must be private (not a symlink, not world-writable, owned by the user); created 0700; temp files via `tempnam()`; keys hashed into file names; 8721/8722 | `FileCacheStrategyTest` |
| Misconfiguration | Options validated, never coerced; 8740 | `ConfiguratorBridgeTest` |
| Information exposure through errors | Messages carry local paths for developers; each exception implements a contract family so an edge maps it to a status instead of echoing the message | SPEC-001 §6 |

## Out of scope

- Running `ReflectionScanner` over untrusted code.
- Secrets: the package neither reads nor stores credentials.

## Supply chain

The lock file is committed, `composer security` audits the locked set, `composer sbom`
emits a CycloneDX 1.5 SBOM, and CI pins every action by commit SHA (Spec §32).
