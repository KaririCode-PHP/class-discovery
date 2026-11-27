# ADR-007: Quality Toolchain per Spec V4.3.3

**Status:** Accepted  
**Date:** 2026-10-27  
**Authors:** Walmir Silva <walmir.silva@kariricode.org>

## Context

Releases 1.0.0 and 1.1.0 delegated quality tooling to KaririCode\Devkit: `kcode init` generated
the tool configuration under `.kcode/`, `kcode quality` ran CS-Fixer, PHPStan, Psalm and PHPUnit,
and CI downloaded `kcode.phar` from the Devkit's *latest* release without a pinned version or
checksum, then patched the generated `phpunit.xml.dist` with `sed`.

Spec V4.3.3 asks for more than that pipeline runs: Psalm at the strictest level the code supports
(the Devkit default is 3), mutation testing with MSI ≥ 80 % and complexity budgets (§13, §29.1), a
dependency audit against the lock file, a CycloneDX SBOM and CI actions pinned by commit SHA (§24,
§32). Where the Devkit and the Specification disagree on component tooling, the Specification
prevails, and the components revised for V4.3.3 (contract, dependency-injection, circuit-breaker,
rate-limiter, authorization, property-inspector) converged on one toolchain.

## Decision

- Tool configuration lives at the repository root: `phpunit.xml.dist`, `phpstan.neon` (level max),
  `psalm.xml` (errorLevel 1), `.php-cs-fixer.dist.php` (PER Coding Style), `infection.json5`.
- The tools are explicit `require-dev` entries; `composer quality` runs the whole gate, and each
  step has its own script (`cs-check`, `phpstan`, `psalm`, `complexity`, `test`, `infection`,
  `security`, `sbom`).
- `tools/complexity-budget.php` covers the §13 budgets PHPStan does not (cyclomatic, nesting,
  parameters); `tools/sbom.php` writes the CycloneDX 1.5 SBOM.
- One CI workflow, every action pinned by commit SHA; its release job publishes a GitHub Release
  only for a `v*` tag whose gate passed, with the notes taken from `CHANGELOG.md`.
- `devkit.php`, the `kcode` scripts and the `.kcode/` convention are removed.

## Alternatives Considered

- **Keep the Devkit pipeline.** Rejected: its gate is below §29.1, it downloads an unpinned
  executable in CI, and the generated configuration needed `sed` patches to pass.
- **Run both.** Rejected: two configurations of the same tools drift, and a green Devkit run
  would not mean a green Spec gate.
- **Wait for the Devkit to generate Spec-conformant configuration.** Rejected for this release;
  revisit if it does, since it would remove five near-identical files per component.

## Consequences

- Contributors run `composer install && composer quality`; CI runs the same scripts.
- Tool versions are pinned by the lock file, which is committed (§24).
- The configuration files are excluded from the package with `export-ignore`.

## References

- KaririCode Specification V4.3.3, §13, §24, §29, §32.
- kariricode/contract 2.11.0 — `.github/workflows/ci.yml` (reference toolchain).
- CVE-2025-30066 (tj-actions/changed-files) — why tags are not pins.
