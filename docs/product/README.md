# Product and program documentation

[← Documentation home](../README.md)

This directory owns **repository-wide product/program governance**: completed baseline scope, DCP standards/status/evidence, current capability and architecture navigation, architecture audits, historical phase-wide acceptance, repository production hardening, and the separate real-production approval state.

Domain-specific current behavior and domain-owned acceptance evidence belong under `docs/domains/<domain>/`.

## Authoritative current records

### Baseline and DCP control

- [Implementation plan](implementation-plan.md) — accepted Phase 0–6 baseline and canonical repository structure.
- [Documentation standard](documentation-standard.md) — documentation ownership/structure/naming/source-of-truth rules.
- [Documentation completeness standard](documentation-completeness-standard.md) — hard DCP completion/`continue` gate.
- [Documentation Completion Program](documentation-program-plan.md) — DCP-P0 through P7 roadmap.
- [Documentation program status](documentation-program-status.md) — authoritative current DCP phase.

### DCP standards and evidence

- P1: [Domain contract standard](domain-contract-standard.md) · [coverage](domain-coverage-matrix.md) · [exit](domain-contract-completeness-exit-report.md)
- P2: [Security standard](security-documentation-standard.md) · [coverage](security-coverage-matrix.md) · [exit](security-completeness-exit-report.md)
- P3: [Operations standard](operations-documentation-standard.md) · [coverage](operations-coverage-matrix.md) · [exit](operations-completeness-exit-report.md)
- P4: [Interface standard](interface-documentation-standard.md) · [coverage](interface-coverage-matrix.md) · [exit](interface-completeness-exit-report.md)
- P5: [Testing/evidence standard](testing-evidence-standard.md) · [coverage](testing-evidence-coverage-matrix.md) · [exit](testing-evidence-completeness-exit-report.md)
- P6: [Architecture/governance standard](architecture-governance-standard.md) · [coverage](architecture-governance-coverage-matrix.md) · exit evidence is produced at the P6 candidate gate.

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

- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled hardening.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for real production cutover until required external evidence exists.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical launch-control expectations feeding the current approval process.

## Documentation Completion Program state

DCP-P0 through P5 are complete. P5 final transition head `983b662bac8873ba2eb71ccec8a6c9e5d1331923` independently passed:

- Dependency Review `31516665602`;
- CodeQL `31516665615`; and
- CI `31516665593`.

That closes P5 and makes **DCP-P6 — Architecture and program-governance consolidation** authoritative.

P6 is governed by the [architecture/governance standard](architecture-governance-standard.md) and frozen [coverage matrix](architecture-governance-coverage-matrix.md). P7 remains blocked until P6 reaches 100% inventory coverage and passes both candidate and final exact-head protected gates.

## Shared versus domain-owned authority

Top-level shared/program areas:

- `product/` — program scope/governance/current-state/architecture audits/historical phase acceptance/production decisions/DCP;
- `security/` — shared baseline, historical phase-wide threat evidence, production security boundary;
- `operations/` — shared runtime/configuration/observability/deployment/recovery/runbooks and historical phase-wide operating evidence;
- `adr/` — durable architecture decisions and current architecture index.

Domain-owned current material:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
docs/domains/<domain>/testing/README.md
```

Domain-specific capability and product/acceptance evidence also stay under the owner when required. Cross-domain summaries may mention many domains but must link rather than absorb deep owner detail.

P6's shared ownership audit found no additional domain-file relocation is required.

## Historical program acceptance

Phase-wide historical evidence remains here because it records the original cross-program delivery sequence:

- [Phase 0 exit](phase-0-exit-report.md)
- [Phase 1 exit](phase-1-exit-report.md)
- [Phase 2 exit](phase-2-exit-report.md)
- [Phase 3 exit](phase-3-exit-report.md)
- [Phase 4 exit](phase-4-exit-report.md)
- [Phase 5 exit](phase-5-exit-report.md) — P5-hardened with recovered final-head workflow identity.
- [Phase 6 exit](phase-6-exit-report.md) — P5-hardened with recovered implementation/final workflow identities.

Accessibility and other phase-wide acceptance artifacts remain historical. Do not rewrite their test counts or old "next phase" wording into current runtime truth.

Accepted post-baseline Kingdoms evidence is domain-owned at [Kingdoms product evidence](../domains/kingdoms/product/README.md).

## Status vocabulary

Shared product/release status:

- **Planned** — approved scope exists; implementation not started.
- **In progress** — implementation/evidence is being produced.
- **Candidate** — content/implementation is complete for a gate; protected acceptance pending.
- **Validated** — a defined gate passed on the recorded revision.
- **Accepted** — repository/product completion gate passed and immutable evidence is retained.
- **Approved** — accountable scope/external decision explicitly approved; distinct from Accepted.
- **Not implemented** — no accepted runtime capability exists.
- **Not yet approved / Pending** — accountable/external evidence remains outstanding.

DCP documentation-work states are `Not started`, `In progress`, `Blocked`, `Candidate`, and `Complete` under the completeness standard.

ADR lifecycle states are `Proposed`, `Accepted`, `Superseded`, and `Rejected` under the P6 architecture/governance standard.

See [glossary](glossary.md) for system-wide terminology.

## Updating program state

1. Keep cross-program baseline/governance/status/current architecture/historical acceptance here.
2. Keep current domain behavior and domain-owned evidence with its code owner.
3. Update [current capability](current-capability-matrix.md) when capability or explicit non-capability changes.
4. Update [cross-domain dependencies](cross-domain-dependency-map.md) when supported owner direction changes materially.
5. Use ADRs for durable architecture decisions; preserve superseded rationale.
6. Keep historical evidence identity/counts historical.
7. Keep repository acceptance separate from real-production approval.
8. Never advance the DCP around an incomplete frozen inventory or protected exact-head gate.

There is no product Phase 7 in the accepted baseline. `DCP-P0` through `DCP-P7` are documentation-governance phase IDs and are separate from product Phase 0–6.
