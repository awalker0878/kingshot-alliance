# Product and program documentation

[← Documentation home](../README.md)

This directory owns **repository-wide product/program governance**: completed baseline scope, Documentation Completion Program standards/status/evidence, current capability and architecture navigation, architecture audits, historical phase-wide acceptance, repository-controlled production hardening, and the separate real-production approval state.

Domain-specific current behavior and domain-owned acceptance evidence belong under `docs/domains/<domain>/`.

## Authoritative current records

### Baseline and program control

- [Implementation plan](implementation-plan.md) — accepted product Phase 0–6 baseline and canonical repository architecture.
- [Repository documentation standard](documentation-standard.md) — ownership, structure, naming, source-of-truth and stable CI rules.
- [Documentation completeness standard](documentation-completeness-standard.md) — DCP completion/exact-gate semantics.
- [Documentation Completion Program](documentation-program-plan.md) — P0–P7 roadmap and final maintenance end state.
- [Documentation program status](documentation-program-status.md) — authoritative current DCP control state.
- [Definition of Done](definition-of-done.md) — normal accepted-change checklist.

### DCP standards, inventories, and evidence

- P1: [Domain contract standard](domain-contract-standard.md) · [coverage](domain-coverage-matrix.md) · [exit](domain-contract-completeness-exit-report.md)
- P2: [Security standard](security-documentation-standard.md) · [coverage](security-coverage-matrix.md) · [exit](security-completeness-exit-report.md)
- P3: [Operations standard](operations-documentation-standard.md) · [coverage](operations-coverage-matrix.md) · [exit](operations-completeness-exit-report.md)
- P4: [Interface standard](interface-documentation-standard.md) · [coverage](interface-coverage-matrix.md) · [exit](interface-completeness-exit-report.md)
- P5: [Testing/evidence standard](testing-evidence-standard.md) · [coverage](testing-evidence-coverage-matrix.md) · [exit](testing-evidence-completeness-exit-report.md)
- P6: [Architecture/governance standard](architecture-governance-standard.md) · [coverage](architecture-governance-coverage-matrix.md) · [exit](architecture-governance-completeness-exit-report.md)
- P7: [Documentation maintenance standard](documentation-maintenance-standard.md) · [coverage](documentation-maintenance-coverage-matrix.md) · [final DCP exit](documentation-completion-program-exit-report.md)

### Current system/product navigation

- [Current architecture and ADR index](../adr/README.md)
- [Cross-domain dependency map](cross-domain-dependency-map.md)
- [Shared architecture/product glossary](glossary.md)
- [Current capability matrix](current-capability-matrix.md)
- [Repository structure audit](repository-structure-audit.md)
- [Domain boundary audit](domain-boundary-audit.md)
- [Domain index](../domains/README.md)
- [Shared security](../security/README.md)
- [Shared operations](../operations/README.md)

### Production boundary

- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for real production cutover until required external infrastructure/operator evidence exists.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical launch-control expectations feeding the current approval process.

## Documentation Completion Program state

DCP-P0 through P6 are fully closed.

P6 content candidate `3bf6b7a7479e64739c1d650bcb02ccbfba25ffdf` produced validated candidate/evidence head `b2d63ffceea50658c989a569a44ad98fc47db75a`, which passed DR `31518789039`, CodeQL `31518789038`, and CI `31518789030`.

P6 final evidence/status transition head `1b3e86ea4a698fbac917337672bef356e8b178b1` independently passed DR `31519423839`, CodeQL `31519423835`, and CI `31519423818`, including frontend/PHP/documentation checks, immutable image, staging, backup/restore, and image scan.

That closes P6 and makes **DCP-P7 — Maintenance automation and final acceptance** authoritative.

P7 is governed by the [documentation maintenance standard](documentation-maintenance-standard.md) and frozen [maintenance/final-acceptance matrix](documentation-maintenance-coverage-matrix.md). P7 defines change-driven obligations and final aggregate automation rather than adding another domain-content layer.

There is no P8. After P7's exact protected candidate and final evidence/status gates close, future documentation work is normal change-driven maintenance under the current standards and Definition of Done.

## Shared versus domain-owned authority

Top-level shared/program areas remain:

- `product/` — program scope/governance/status/current architecture/audits/historical phase-wide acceptance/production decisions/DCP;
- `security/` — shared baseline, historical phase-wide threat evidence, production security boundary;
- `operations/` — shared runtime/configuration/observability/deployment/recovery/runbooks and historical phase-wide operating evidence;
- `adr/` — current architecture index and durable architecture decisions.

Domain-owned current material remains deterministic:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
docs/domains/<domain>/testing/README.md
```

Domain-specific capability and product/acceptance evidence also stay with the owner when required. P6 confirmed no additional shared→domain relocation was necessary.

## Historical program acceptance

Historical product Phase 0–6 evidence remains here as program history. Accepted post-baseline Kingdoms evidence remains domain-owned at [Kingdoms product evidence](../domains/kingdoms/product/README.md).

Historical test counts, SHAs, workflow IDs, old phase-next-step wording, and accepted rationale remain historical. Current living standards/contracts/navigation evolve separately.

## Status vocabulary

- DCP/documentation work: `Not started`, `In progress`, `Blocked`, `Candidate`, `Complete`.
- ADR lifecycle: `Proposed`, `Accepted`, `Superseded`, `Rejected`.
- Product/release state: `Planned`, `In progress`, `Candidate`, `Validated`, `Accepted`, `Approved`, `Not implemented`, `Not yet approved` / `Pending` as applicable.

Do not use **Accepted** and **Approved** interchangeably. See [Shared glossary](glossary.md).

## Normal maintenance after DCP

For every material change:

1. identify the owning domain/shared area;
2. apply the [documentation maintenance standard](documentation-maintenance-standard.md) to determine affected living documents/evidence/navigation;
3. update only contracts actually changed by the work;
4. preserve historical acceptance/decision evidence;
5. update ADR/dependency/capability/audit/glossary surfaces only when their system-level meaning changes;
6. keep repository acceptance separate from real-production approval; and
7. require protected documentation architecture/maintenance checks on the exact final head.

A harmless internal refactor does not require artificial prose churn when no documented contract changed.
