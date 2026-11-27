# ADR-010: Quality Toolchain through KaririCode Devkit, with the Root Configs

**Status:** Accepted; amends ADR-007 (where the tools come from)  
**Date:** 2026-11-27  
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

ADR-007 moved the tools into `require-dev` because Devkit 1.x ran its own generated configs (Psalm at level 3, no mutation gate) and CI fetched an unpinned `kcode.phar`. Devkit 1.2.1 runs a component's committed configs instead (`'root_configs' => true`), and the ecosystem now installs the toolchain through it: kariricode/contract, kariricode/exception and kariricode/property-inspector moved first, each recording the change in an ADR of its own.

## Decision

- `require-dev` is removed. `devkit.php` pins the tools exactly: PHPUnit 11.5.56, PHPStan 2.3.7 with phpstan-phpunit 2.1.4 and tomasvotruba/cognitive-complexity 1.2.0, Psalm 6.19.1, PHP-CS-Fixer 3.95.27 and Infection 0.35.5. kcode installs them into `.kcode/vendor/`.
- psr/simple-cache 3.0.0 is listed there too. It is not a tool: the tests and the analysers need it for `Integration\CacheBridge`, which stays an optional integration (`suggest`). `tests/bootstrap.php` loads the project's autoloader first and the toolchain's second; PHPStan scans it and Psalm reads it as an extra file.
- The root configs of ADR-007 stay and are what kcode runs. Paths that pointed into `vendor/` point into `.kcode/vendor/`.
- The gates and commands of ADR-007 are unchanged: `composer quality` and the scripts behind it, which now call kcode. `composer security` also audits the toolchain.
- `composer.lock` is committed and holds the runtime only: kariricode/contract 2.12.0 and psr/clock 1.0.0. `tools/sbom.php` reads the development components from `.kcode/composer.lock`.
- CI downloads kcode 1.2.1 by version and checks its SHA-256 before `kcode init`. The release job edits the notes of a release that already exists for the tag (one created from the GitHub UI) instead of failing.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Keep the tools in `require-dev` (ADR-007) | the rest of the ecosystem installs them through kcode |
| kcode with its generated configs | weaker than §29.1: the reason for ADR-007 still holds for them |
| psr/simple-cache in `require-dev` alone | a second dependency channel for one interface package; the toolchain already carries test-only packages elsewhere in the ecosystem |
| Unpinned tools | a gate result could not be reproduced from the commit that produced it (§32) |

## Consequences

- One toolchain model across the ecosystem; this package's gates are unchanged.
- Contributors install kcode once (README, Contributing).
- Transitive versions of the toolchain resolve at install time until Devkit commits a `devkit.lock`.
