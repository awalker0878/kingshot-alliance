# Product and program documentation

[← Documentation home](../README.md)

This directory owns program scope, phase acceptance, accessibility evidence, alignment audits, and current production-readiness/approval state.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved program baseline, phase boundaries, cross-cutting requirements, canonical repository structure, and program definition of done.
- [Definition of done](definition-of-done.md) — repository-level completion expectations used with the implementation plan.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled post-Phase-6 hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for a real production cutover until the required deployment and external-control evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — Phase 6 launch-control expectations that feed the post-phase hardening/approval process.

## Phase acceptance history

The implementation plan ends at Phase 6. Accepted delivery evidence is retained in:

- [Phase 0 exit report](phase-0-exit-report.md)
- [Phase 1 exit report](phase-1-exit-report.md)
- [Phase 2 exit report](phase-2-exit-report.md)
- [Phase 3 exit report](phase-3-exit-report.md)
- [Phase 4 exit report](phase-4-exit-report.md)
- [Phase 5 exit report](phase-5-exit-report.md)
- [Phase 6 exit report](phase-6-exit-report.md)

These are historical acceptance records. They should preserve the evidence and context of their phase even when later phases have subsequently shipped. Do not infer the current roadmap from old “next phase” wording.

## Supporting product evidence

- [Phase 3 scope](phase-3-scope.md)
- [Phases 1–4 alignment audit](phases-1-4-alignment-audit.md)
- [Phase 1 accessibility review](phase-1-accessibility-review.md)
- [Phase 2 accessibility](phase-2-accessibility.md)
- [Phase 3 accessibility](phase-3-accessibility.md)
- [Phase 4 accessibility](phase-4-accessibility.md)
- [Phase 5 accessibility](phase-5-accessibility.md)
- [Phase 6 accessibility](phase-6-accessibility.md)

Use the current implementation plan and current acceptance/launch records for present-tense status; use the supporting phase documents to understand why a historical gate was accepted.

## Status vocabulary

Use status terms consistently:

- **Planned** — approved scope exists but implementation has not started.
- **In progress** — implementation/evidence is being produced.
- **Candidate** — implementation is complete enough for final protected validation but has not passed the gate.
- **Accepted** — the defined repository/product gate passed and its evidence was recorded.
- **Approved** — an accountable owner explicitly approved an external or production decision that cannot be inferred from repository validation alone.
- **Not yet approved / Pending** — required evidence or accountable approval is still outstanding.

Do not use **Accepted** and **Approved** interchangeably. In particular, repository production hardening is Accepted while real production launch remains Not yet approved.

## Updating program state

When a phase or hardening stage closes:

1. Record the exact validated head and protected-check evidence in its exit record.
2. Keep the implementation plan's scope intact unless an explicit plan change is approved.
3. Update current-state indexes/approval records so readers do not need to reconstruct status from PR history.
4. Preserve historical phase reports rather than rewriting old evidence to sound current.
5. Record deferred work explicitly without partially implementing or documenting it as present capability.

There is no Phase 7 in the current implementation plan. New product scope beyond Phase 6 requires an explicit plan revision rather than an implied continuation of phase numbering.
