# Product and program documentation

[← Documentation home](../README.md)

This directory owns **repository-wide product and program governance**: the completed baseline plan, current capability/status navigation, documentation/architecture governance, phase-wide historical acceptance evidence, production hardening, and real-production approval state.

Domain-specific product scopes, implementation plans, slice validations, accessibility records, and increment exit evidence belong with the code-owning domain under `docs/domains/<domain>/`.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved Phase 0–6 baseline, canonical repository structure, delivery governance, and program definition of done.
- [Documentation standard](documentation-standard.md) — normative repository documentation ownership, structure, naming, standard formats, and CI contract.
- [Current capability matrix](current-capability-matrix.md) — present-tense implemented capabilities and explicit non-capabilities/boundaries.
- [Definition of done](definition-of-done.md) — repository-level completion expectations.
- [Repository structure audit](repository-structure-audit.md) — physical repository/documentation structure evidence.
- [Domain boundary audit](domain-boundary-audit.md) — cross-domain ownership and supported-contract evidence.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for real production cutover until required external/deployment evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical launch-control expectations feeding the current production-approval process.

Use the [domain index](../domains/README.md) for current business/runtime ownership and domain-specific delivery evidence. In particular, all `KINGDOMS-001` through `KINGDOMS-003` product/acceptance records now live under the [Kingdoms product evidence index](../domains/kingdoms/product/README.md).

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

## Updating program state

When product work changes:

1. Keep cross-program baseline/governance/status records here.
2. Put domain-specific scope, implementation, validation, security/operations references, accessibility, and acceptance evidence under the owning domain.
3. Update the [current capability matrix](current-capability-matrix.md) with present-tense status and links to the owning domain.
4. Preserve historical phase-wide evidence rather than extending it as current feature documentation.
5. Keep real production approval separate from repository/product acceptance.
6. Follow the [documentation standard](documentation-standard.md) and protected architecture/link checks.

There is no Phase 7 in the current baseline. Accepted post-program increments are domain-owned evidence, not continuation of the historical phase numbering.
