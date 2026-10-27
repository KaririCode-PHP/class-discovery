# QD-V4.3.3 Compliance — kariricode/class-discovery 2.0.0

- **Status: PENDING** — the local gate is green; compliance is declared when it passes in CI (E-006)
- **Date:** 2026-10-27
- **Component:** kariricode/class-discovery 2.0.0 (branch `release/2.0.0`; Packagist has 1.1.0)
- **Authors:** Walmir Silva <walmir.silva@kariricode.org>

## 1. Gate (Spec V4.3.3 §29.1)

| Gate | Command | Local result | CI |
|---|---|---|---|
| Composer metadata | `composer validate --strict` | valid | pending |
| Code style (PER-CS) | `composer cs-check` | clean | pending |
| Static analysis | `composer phpstan` | level max, 0 errors, no suppressions | pending |
| Type coverage | `composer psalm` | errorLevel 1, 0 errors | pending |
| Complexity (§13) | `composer complexity` | within budgets (readonly DPO constructors exempt, ADR-009) | pending |
| Tests incl. conformance (§29.2) | `composer test` | 311 tests, green | pending |
| Mutation | `composer infection` | MSI 83 %, covered MSI 85 % (gate 80 %) | pending |
| Line coverage | PHPUnit + pcov | 97.75 % | pending |
| Known vulnerabilities | `composer security` | none | pending |
| SBOM | `composer sbom` | CycloneDX 1.5; runtime: kariricode/contract, psr/clock | pending |

The local run resolved kariricode/contract 2.11.0 from its `main` branch (commit 62dea5b) through a
path repository, because the version is not tagged yet; nothing of that setup is committed.

## 2. Blocking prerequisites, in order

1. **Tag kariricode/contract `v2.11.0`.** Its code is on `main` (62dea5b, also `release/2.11.0`);
   only the tag is missing, so Packagist still shows 2.8.6. Until then `composer install` cannot
   resolve `kariricode/contract ^2.11`, and CI stops at that step.
2. **Commit `composer.lock`** generated against the published contract (§24).
3. **A green CI run** on the release pull request.
4. **Merge, then a signed tag `v2.0.0`.** CI's release job publishes the GitHub Release with the
   CHANGELOG section of that version, after the gate passes on the tag.

## 3. Verified against the real contract

`KaririCode\Contract\DI\ServiceProvider`, `DI\BindableContainer` and the six exception families on
contract `main` (62dea5b) match what this package implements and calls. A factory passed to
`bind()` receives the container — stated by the contract's docblock and implemented that way by
kariricode/dependency-injection.

## 4. Known gaps (do not block the gate)

- Windows paths: the resolver normalizes `\`, but no Windows runner exercises it.
- The unreadable-`composer.json` test is skipped when tests run as root (file permissions do not
  bind root); it runs on CI's non-root runner.
- Rejection of a cache directory owned by another user is implemented (POSIX) but not exercised by
  the suite.
