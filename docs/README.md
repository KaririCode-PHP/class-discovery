# Documentation — kariricode/class-discovery

| Document | Purpose |
|---|---|
| [SPEC-001](spec/SPEC-001-component-specification.md) | Architecture, scanners, security model, error codes, ARFA matrix, measured gate |
| [ADR-001](adr/ADR-001-token-based-scanning.md) | Token-based scanning; FQCN resolution and class bodies (addenda) |
| [ADR-002](adr/ADR-002-zero-external-dependencies.md) | Dependencies: interfaces only — kariricode/contract and PSR-20 |
| [ADR-003](adr/ADR-003-immutable-discovery-results.md) | Immutable discovery results (ARFA 1.3 P1) |
| [ADR-004](adr/ADR-004-backed-enum-attribute-target.md) | Backed enum for `AttributeTarget` (E-003) |
| [ADR-005](adr/ADR-005-composition-over-inheritance.md) | Scanners compose the token engine |
| [ADR-006](adr/ADR-006-multi-tier-cache-strategy.md) | Memory → file cache; integrity and key identity (addendum) |
| [ADR-007](adr/ADR-007-quality-toolchain-per-spec.md) | Quality toolchain per Spec V4.3.3 (replaces the Devkit pipeline of 1.x) |
| [ADR-008](adr/ADR-008-metadata-dpos-without-serializable-data.md) | Metadata DPOs do not declare `SerializableData` |
| [ADR-009](adr/ADR-009-parameter-budget-for-readonly-dpos.md) | Parameter budget and readonly DPO constructors |
| [Compliance (QD-V433)](quality/QD-V433-COMPLIANCE.md) | Gate status — PENDING until CI |
| [Review log](TEST-REVIEW.md) | Findings, fixes and decisions, pass by pass |
| [Security policy](../SECURITY.md) | Reporting, ASVS scope, threat model |
| [Changelog](../CHANGELOG.md) | Release notes, including the 1.x → 2.0.0 migration |
