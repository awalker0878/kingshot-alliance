# Product and program documentation

[← Documentation home](../README.md)

This directory owns the completed Phase 0–6 baseline, approved post-program product increments, current capability/status navigation, phase acceptance, accessibility evidence, alignment audits, and production-readiness/approval state.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved Phase 0–6 program baseline, phase boundaries, cross-cutting requirements, canonical repository structure, and program definition of done.
- [Kingdoms roster intelligence increment](kingdoms-roster-intelligence-increment.md) — **Approved / implementation in progress** for first-class kingdoms, game-player identity, alliance rosters, snapshots, imports/exports, and roster intelligence.
- [KINGDOMS-001 implementation plan](kingdoms-roster-intelligence-implementation-plan.md) — gated implementation sequence `K1-P0` through `K1-P6` for delivering the approved Kingdoms increment without extending the historical program phase numbering.
- [Current capability matrix](current-capability-matrix.md) — present-tense navigation across implemented capabilities, approved remaining scope, ownership, living contracts, and explicit non-capabilities/boundaries.
- [Definition of done](definition-of-done.md) — repository-level completion expectations used with the baseline plan and approved product increments.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled post-Phase-6 hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for a real production cutover until the required deployment and external-control evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — Phase 6 launch-control expectations that feed the post-phase hardening/approval process.

Use the capability matrix to answer what exists now. Use the implementation plan for the completed Phase 0–6 baseline and an approved increment scope for product work added after that baseline. Use an increment implementation plan for its gated delivery sequence. Use the production launch approval record to answer whether real production cutover is authorized.

## Approved post-program roadmap increments

The Phase 0–6 program remains closed. New product work is approved as a named product increment with its own stable scope ID, acceptance criteria, implementation plan, and eventual exit record rather than extending the historical phase sequence.

| Scope ID | Increment | Status | Outcome |
| --- | --- | --- | --- |
| `KINGDOMS-001` | [Kingdoms roster intelligence](kingdoms-roster-intelligence-increment.md) | **In progress — Slice A Kingdom foundation implemented; later slices remain** | First-class kingdom/game-player model, alliance roster management, historical snapshots, CSV migration workflow, and roster intelligence. |

Implementation sequence: [KINGDOMS-001 implementation plan](kingdoms-roster-intelligence-implementation-plan.md). Current runtime detail: [Kingdoms domain guide](../domains/kingdoms.md).

An approved increment scope is an explicit post-program plan addendum. It may extend product scope without reopening or renumbering the completed Phase 0–6 program. Candidate follow-on increments listed inside an approved scope are not themselves approved until they receive their own scope record.

## Phase acceptance history

The baseline implementation plan ends at Phase 6. Accepted delivery evidence is retained in:

- [Phase 0 exit report](phase-0-exit-report.md)
- [Phase 1 exit report](phase-1-exit-report.md)
- [Phase 2 exit report](phase-2-exit-report.md)
- [Phase 3 exit report](phase-3-exit-report.md)
- [Phase 4 exit report](phase-4-exit-report.md)
- [Phase 5 exit report](phase-5-exit-report.md)
- [Phase 6 exit report](phase-6-exit-report.md)

These are historical acceptance records. They should preserve the evidence and context of their phase even when later product increments are approved or implemented. Do not infer the current roadmap from old “next phase” wording, and do not repurpose phase exit reports as release notes or a user-facing changelog.

## Supporting product evidence

- [Phase 3 scope](phase-3-scope.md)
- [Phases 1–4 alignment audit](phases-1-4-alignment-audit.md)
- [Phase 1 accessibility review](phase-1-accessibility-review.md)
- [Phase 2 accessibility](phase-2-accessibility.md)
- [Phase 3 accessibility](phase-3-accessibility.md)
- [Phase 4 accessibility](phase-4-accessibility.md)
- [Phase 5 accessibility](phase-5-accessibility.md)
- [Phase 6 accessibility](phase-6-accessibility.md)

Use the current capability matrix and current acceptance/launch records for present-tense status; use the implementation plan plus approved increment scopes for approved product scope; use increment implementation plans for delivery sequencing; use the supporting phase documents to understand why a historical gate was accepted.

## Status vocabulary

Use status terms consistently:

- **Planned** — approved scope exists but implementation has not started.
- **In progress** — implementation/evidence is being produced.
- **Candidate** — implementation is complete enough for final protected validation but has not passed the gate.
- **Accepted** — the defined repository/product gate passed and its evidence was recorded.
- **Approved** — an accountable owner explicitly approved a scope or external/production decision; approval does not imply implementation is complete.
- **Not yet approved / Pending** — required evidence or accountable approval is still outstanding.

Do not use **Accepted** and **Approved** interchangeably. `KINGDOMS-001` is Approved scope and In progress, but the complete increment is not Accepted. Repository production hardening is Accepted while real production launch remains Not yet approved.

## Updating program state

When a post-program increment is proposed or delivered:

1. Create or update a named product increment scope with a stable ID, ownership, boundaries, dependencies, security/operational requirements, acceptance criteria, and explicit deferrals.
2. Create a gated implementation plan when the increment is large enough to require multiple independently reviewable delivery stages.
3. Do not create a new numbered program phase unless the baseline implementation plan itself is deliberately reopened and re-approved.
4. Update the capability matrix and indexes so implemented slices, approved remaining work, and unapproved candidates are clearly distinguished.
5. When implementation closes, create an increment-specific exit/acceptance record with the exact validated head and protected-check evidence.
6. Preserve historical phase reports rather than rewriting old evidence to sound current.
7. Record deferred work explicitly without partially implementing or documenting it as present capability.

There is no Phase 7 in the current baseline. `KINGDOMS-001` is an approved post-program product increment, not a continuation of phase numbering.
