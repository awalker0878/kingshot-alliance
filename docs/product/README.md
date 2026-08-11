# Product and program documentation

[← Documentation home](../README.md)

This directory owns **repository-wide product and program governance**: the completed baseline plan, current capability/status navigation, documentation/architecture governance, phase-wide historical acceptance evidence, production hardening, and real-production approval state.

Domain-specific product scopes, implementation plans, slice validations, accessibility records, and increment exit evidence belong with the code-owning domain under `docs/domains/<domain>/`.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved Phase 0–6 baseline, canonical repository structure, delivery governance, and program definition of done.
- [Documentation standard](documentation-standard.md) — normative repository documentation ownership, structure, naming, standard formats, and CI contract.
- [Documentation completeness standard](documentation-completeness-standard.md) — normative definition of document, coverage, and phase completion plus the hard `continue` gate.
- [Documentation Completion Program](documentation-program-plan.md) — phased DCP-P0 through DCP-P7 roadmap for domain, security, operations, interface, evidence, architecture, and maintenance completeness.
- [Documentation program status](documentation-program-status.md) — authoritative current DCP phase and continuation decision.
- [Domain contract standard](domain-contract-standard.md) — DCP-P1 requirements for code-local maps, canonical domain contracts, capability splitting, code-area review, and P1 CI.
- [Domain coverage matrix](domain-coverage-matrix.md) — completed DCP-P1 inventory of all 14 domains and every required material capability contract.
- [DCP-P1 exit report](domain-contract-completeness-exit-report.md) — accepted P1 scope and protected validation evidence.
- [Security documentation standard](security-documentation-standard.md) — DCP-P2 requirements for shared security policy, domain security profiles, focused living security reviews, privacy/data-protection boundaries, and P2 CI.
- [Security coverage matrix](security-coverage-matrix.md) — frozen DCP-P2 inventory covering all 14 domains and the required focused security-review set.
- [Current capability matrix](current-capability-matrix.md) — present-tense implemented capabilities and explicit non-capabilities/boundaries.
- [Definition of done](definition-of-done.md) — repository-level completion expectations.
- [Repository structure audit](repository-structure-audit.md) — physical repository/documentation structure evidence.
- [Domain boundary audit](domain-boundary-audit.md) — cross-domain ownership and supported-contract evidence.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for real production cutover until required external/deployment evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical launch-control expectations feeding the current production-approval process.

Use the [domain index](../domains/README.md) for current business/runtime ownership and domain-specific delivery/security/operations evidence. In particular, all `KINGDOMS-001` through `KINGDOMS-003` product/acceptance records remain under the [Kingdoms product evidence index](../domains/kingdoms/product/README.md), with current security navigation under the [Kingdoms security profile](../domains/kingdoms/security/README.md).

## Documentation Completion Program

The repository's ownership migration is complete; ongoing documentation completion is governed by the [Documentation Completion Program](documentation-program-plan.md).

The DCP is sequential and hard-gated. Every phase must reach 100% required documentation coverage before the next phase may start. The user command `continue` reads the [program status ledger](documentation-program-status.md): if the current phase is incomplete, work remains in that phase; only a fully complete gate advances to the next phase.

The normative completion definition is [Documentation completeness standard](documentation-completeness-standard.md). Specialized standards are introduced phase-by-phase instead of continuously expanding one monolithic documentation standard.

DCP-P1 is complete under the [Domain contract standard](domain-contract-standard.md) and [Domain coverage matrix](domain-coverage-matrix.md). Current DCP-P2 execution uses the [Security documentation standard](security-documentation-standard.md) and [Security coverage matrix](security-coverage-matrix.md). Later phases create their focused standards only when that phase becomes active.

## Historical program acceptance

The baseline implementation plan ends at Phase 6. Phase-wide accepted delivery evidence remains here because it records the overall program sequence rather than one current code-domain contract:

- [Phase 0 exit report](phase-0-exit-report.md)
- [Phase 1 exit report](phase-1-exit-report.md)
- [Phase 2 exit report](phase-2-exit-report.md)
- [Phase 3 exit report](phase-3-exit-report.md)
- [Phase 4 exit report](phase-4-exit-report.md)
- [Phase 5 exit report](phase-5-exit-report.md)
- [Phase 6 exit report](phase-6-exit-report.md)

Supporting phase-wide program evidence includes:

- [Phase 3 scope](phase-3-scope.md)
- [Phases 1–4 alignment audit](phases-1-4-alignment-audit.md)
- [Phase 1 accessibility review](phase-1-accessibility-review.md)
- [Phase 2 accessibility](phase-2-accessibility.md)
- [Phase 3 accessibility](phase-3-accessibility.md)
- [Phase 4 accessibility](phase-4-accessibility.md)
- [Phase 5 accessibility](phase-5-accessibility.md)
- [Phase 6 accessibility](phase-6-accessibility.md)

These are historical acceptance/program records. Navigation/path maintenance is appropriate; do not rewrite them into current feature documentation.

## Domain-specific product evidence

A product record moves under its owning domain when its scope, implementation sequence, validation, accessibility, or acceptance evidence is primarily about that domain's code/business contract.

Canonical pattern:

```text
docs/domains/<domain>/product/
  README.md
  <domain-specific scope/evidence>.md
```

Current example:

- [Kingdoms product and acceptance evidence](../domains/kingdoms/product/README.md) — `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` scope, plans, decisions, validations, accessibility, and exit evidence.

Top-level `docs/product/` should not become an inventory of implementation files owned by individual domains.

## Status vocabulary

Use status terms consistently:

- **Planned** — approved scope exists but runtime implementation has not started.
- **In progress** — runtime implementation/evidence is being produced.
- **Candidate** — implementation is ready for final protected validation but the gate has not passed.
- **Validated** — a defined slice/evidence gate passed on the recorded implementation.
- **Accepted** — repository/product completion gate passed and evidence is recorded.
- **Approved** — an accountable owner explicitly approved scope or an external/production decision; approval does not imply implementation is complete.
- **Not yet approved / Pending** — required evidence/accountable approval remains outstanding.

Do not use **Accepted** and **Approved** interchangeably. Repository production hardening can be Accepted while real production launch remains Not yet approved.

The DCP uses its own documentation-work states (`Not started`, `In progress`, `Blocked`, `Candidate`, `Complete`) as defined by [Documentation completeness standard](documentation-completeness-standard.md); those states describe documentation-program progress rather than runtime product acceptance.

## Updating program state

When product work changes:

1. Keep cross-program baseline/governance/status records here.
2. Put domain-specific scope, implementation, validation, security/operations references, accessibility, and acceptance evidence under the owning domain.
3. Update the [current capability matrix](current-capability-matrix.md) with present-tense status and links to the owning domain.
4. Preserve historical phase-wide evidence rather than extending it as current feature documentation.
5. Keep real production approval separate from repository/product acceptance.
6. Follow the [documentation standard](documentation-standard.md) and protected architecture/link checks.
7. For Documentation Completion Program work, follow the [current DCP status](documentation-program-status.md) and do not advance a phase until [documentation completeness](documentation-completeness-standard.md) is satisfied.
8. Keep the active phase's frozen coverage matrix synchronized with actual required artifacts and its focused normative standard.

There is no Phase 7 in the current baseline. Accepted post-program increments are domain-owned evidence, not continuation of the historical phase numbering. `DCP-P0` through `DCP-P7` are documentation-governance phase IDs and are separate from the historical product implementation phases.
