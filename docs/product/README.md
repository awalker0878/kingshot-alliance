# Product and program documentation

[← Documentation home](../README.md)

This directory owns program scope, phase acceptance, accessibility evidence, alignment audits, and current production-readiness/approval state.

## Authoritative current records

- [Implementation plan](IMPLEMENTATION_PLAN.md) — approved program baseline, phase boundaries, cross-cutting requirements, canonical repository structure, and program definition of done.
- [Definition of done](DEFINITION_OF_DONE.md) — repository-level completion expectations used with the implementation plan.
- [Production hardening exit report](PRODUCTION_HARDENING_EXIT_REPORT.md) — **Accepted** repository-controlled post-Phase-6 hardening evidence.
- [Production launch approval](PRODUCTION_LAUNCH_APPROVAL.md) — **Not yet approved** for a real production cutover until the required deployment and external-control evidence is recorded.
- [Phase 6 launch readiness](PHASE_6_LAUNCH_READINESS.md) — Phase 6 launch-control expectations that feed the post-phase hardening/approval process.

## Phase acceptance history

The implementation plan ends at Phase 6. Accepted delivery evidence is retained in:

- [Phase 0 exit report](PHASE_0_EXIT_REPORT.md)
- [Phase 1 exit report](PHASE_1_EXIT_REPORT.md)
- [Phase 2 exit report](PHASE_2_EXIT_REPORT.md)
- [Phase 3 exit report](PHASE_3_EXIT_REPORT.md)
- [Phase 4 exit report](PHASE_4_EXIT_REPORT.md)
- [Phase 5 exit report](PHASE_5_EXIT_REPORT.md)
- [Phase 6 exit report](PHASE_6_EXIT_REPORT.md)

These are historical acceptance records. They should preserve the evidence and context of their phase even when later phases have subsequently shipped. Do not infer the current roadmap from old “next phase” wording.

## Supporting product evidence

This directory also contains phase scope records, accessibility reviews, and cross-phase alignment audits such as:

- [Phase 3 scope](PHASE_3_SCOPE.md)
- [Phases 1–4 alignment audit](PHASES_1_4_ALIGNMENT_AUDIT.md)
- phase accessibility reviews/evidence, including the Phase 1 review and Phase 2–6 accessibility records

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
