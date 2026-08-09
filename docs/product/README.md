# Product and program documentation

[← Documentation home](../README.md)

This directory owns the completed Phase 0–6 baseline, approved post-program product increments, current capability/status navigation, increment/phase acceptance, accessibility evidence, alignment audits, and production-readiness/approval state.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved Phase 0–6 program baseline, phase boundaries, cross-cutting requirements, canonical repository structure, and program definition of done.
- [Kingdoms roster intelligence increment](kingdoms-roster-intelligence-increment.md) — **Approved scope / Accepted implementation** for first-class Kingdoms, neutral game-player identity, alliance rosters, snapshots, imports/exports, and roster intelligence.
- [KINGDOMS-001 implementation plan](kingdoms-roster-intelligence-implementation-plan.md) — gated sequence `K1-P0` through `K1-P6`; implementation slices and whole-increment hardening are complete.
- [KINGDOMS-001 exit report](kingdoms-roster-intelligence-exit-report.md) — **Accepted** whole-increment domain/security/accessibility/migration/query/operations/API-webhook review and protected evidence record.
- [KINGDOMS-001 accessibility review](kingdoms-roster-intelligence-accessibility.md) — accepted repository accessibility evidence for the complete first-party workflow.
- [Kingdoms transfer planning increment](kingdoms-transfer-planning-increment.md) — **Approved scope / Accepted implementation** for alliance-owned transfer cycles, incoming/outgoing/staying participants, destinations, groups, coordinators, readiness/blockers, and explicit roster handoff.
- [KINGDOMS-002 implementation plan](kingdoms-transfer-planning-implementation-plan.md) — completed gated sequence `K2-P0` through `K2-P6`.
- [KINGDOMS-002 exit report](kingdoms-transfer-planning-exit-report.md) — **Accepted** whole-increment domain/security/accessibility/migration/query/operations/API-webhook review and protected evidence record.
- [KINGDOMS-002 accessibility review](kingdoms-transfer-planning-accessibility.md) — accepted repository/source-level accessibility evidence for the complete transfer-planning workflow.
- [Kingdoms alliance intelligence and diplomacy increment](kingdoms-alliance-intelligence-increment.md) — **Approved scope / In-progress implementation** for neutral game-side alliance references, alliance-owned observations, diplomacy/NAP history, private contacts, and descriptive intelligence.
- [KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md) — gated sequence `K3-P0` through `K3-P6`; `K3-P0` is complete and Slice A / `K3-P1` plus Slice B / `K3-P2` are validated.
- [KINGDOMS-003 K3-P0 decisions](kingdoms-alliance-intelligence-p0-decisions.md) — locked identity, tenancy, diplomacy-state, observation-correction, privacy/history, event and migration decisions governing later slices.
- [KINGDOMS-003 Slice A validation](kingdoms-alliance-intelligence-slice-a-validation.md) — **Validated** neutral `KingdomAlliance` identity and alliance-owned tracking foundation, with exact protected/runtime evidence.
- [KINGDOMS-003 Slice B validation](kingdoms-alliance-intelligence-slice-b-validation.md) — **Validated** append-oriented factual observations, correction/invalidation history and current/stale/missing projection, with exact protected/runtime evidence.
- [Current capability matrix](current-capability-matrix.md) — present-tense navigation across implemented capabilities, accepted increments, approved/in-progress increments, living contracts, and explicit non-capabilities/boundaries.
- [Definition of done](definition-of-done.md) — repository-level completion expectations used with the baseline plan and approved product increments.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled post-Phase-6 hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for a real production cutover until required deployment/external-control evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical Phase 6 launch-control expectations feeding the post-phase hardening/approval process.

Use the capability matrix to answer what exists now. Use the implementation plan for the completed Phase 0–6 baseline and an approved increment scope for post-program product work. Use increment implementation plans for gated delivery and increment exit records for final repository/product acceptance evidence. Use production launch approval to answer whether a real production cutover is authorized.

## Approved post-program roadmap increments

The Phase 0–6 program remains closed. New product work is approved as a named product increment with its own stable scope ID, acceptance criteria, implementation plan, and exit record rather than extending the historical phase sequence.

| Scope ID | Increment | Status | Outcome |
| --- | --- | --- | --- |
| `KINGDOMS-001` | [Kingdoms roster intelligence](kingdoms-roster-intelligence-increment.md) | **Accepted** | First-class Kingdom/game-player model, alliance roster management, historical snapshots, controlled CSV migration/export, and roster intelligence. |
| `KINGDOMS-002` | [Kingdoms transfer planning](kingdoms-transfer-planning-increment.md) | **Accepted** | Alliance-owned transfer cycles with incoming/outgoing/staying intent, destinations, groups/coordinators, manual readiness/blockers, and explicit roster handoff. |
| `KINGDOMS-003` | [Kingdom/alliance intelligence and diplomacy](kingdoms-alliance-intelligence-increment.md) | **Approved / In progress (`K3-P1` + `K3-P2` validated)** | Neutral game-side alliance references, tenant-owned tracking and append-oriented factual observations are validated; explicit diplomacy/NAP history, manager-private contacts and descriptive alliance intelligence remain sequenced follow-on slices. |

Implementation sequences: [KINGDOMS-001](kingdoms-roster-intelligence-implementation-plan.md), [KINGDOMS-002](kingdoms-transfer-planning-implementation-plan.md), and [KINGDOMS-003](kingdoms-alliance-intelligence-implementation-plan.md). Acceptance evidence is recorded in the [KINGDOMS-001 exit report](kingdoms-roster-intelligence-exit-report.md) and [KINGDOMS-002 exit report](kingdoms-transfer-planning-exit-report.md). `KINGDOMS-003` has completed its `K3-P0` contract lock and validated Slice A / `K3-P1` plus Slice B / `K3-P2`; it does not receive an exit report or whole-increment Accepted status until remaining slices and `K3-P6` pass. Current accepted whole-increment runtime detail remains in the [Kingdoms domain guide](../domains/kingdoms.md), while current K3 slice detail is in [Kingdoms alliance intelligence](../domains/kingdoms-alliance-intelligence.md).

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

These are historical acceptance records. They preserve the evidence/context of their phase even when later product increments are implemented. Do not infer the current roadmap from old “next phase” wording, and do not repurpose phase exit reports as release notes or a user-facing changelog.

## Supporting product evidence

- [KINGDOMS-001 accessibility review](kingdoms-roster-intelligence-accessibility.md)
- [KINGDOMS-002 accessibility review](kingdoms-transfer-planning-accessibility.md)
- [KINGDOMS-003 K3-P0 decisions](kingdoms-alliance-intelligence-p0-decisions.md)
- [KINGDOMS-003 Slice A validation](kingdoms-alliance-intelligence-slice-a-validation.md)
- [KINGDOMS-003 Slice B validation](kingdoms-alliance-intelligence-slice-b-validation.md)
- [Phase 3 scope](phase-3-scope.md)
- [Phases 1–4 alignment audit](phases-1-4-alignment-audit.md)
- [Phase 1 accessibility review](phase-1-accessibility-review.md)
- [Phase 2 accessibility](phase-2-accessibility.md)
- [Phase 3 accessibility](phase-3-accessibility.md)
- [Phase 4 accessibility](phase-4-accessibility.md)
- [Phase 5 accessibility](phase-5-accessibility.md)
- [Phase 6 accessibility](phase-6-accessibility.md)

Use the current capability matrix and current acceptance/launch records for present-tense status; use the implementation plan plus approved increment scopes for product scope; use increment implementation plans for delivery sequencing; use supporting documents to understand specific evidence and historical gates.

## Status vocabulary

Use status terms consistently:

- **Planned** — approved scope exists but runtime implementation has not started; pre-runtime design/contract gates may already be complete.
- **In progress** — runtime implementation/evidence is being produced and may contain validated slices without whole-increment acceptance.
- **Candidate** — implementation is complete enough for final protected validation but has not passed the gate.
- **Accepted** — the defined repository/product gate passed and its evidence was recorded.
- **Approved** — an accountable owner explicitly approved a scope or external/production decision; approval does not imply implementation is complete.
- **Not yet approved / Pending** — required evidence or accountable approval is still outstanding.

Do not use **Accepted** and **Approved** interchangeably. `KINGDOMS-001` and `KINGDOMS-002` are Approved scopes with Accepted implementations. `KINGDOMS-003` is Approved scope with implementation In progress: `K3-P0` is complete and `K3-P1`/`K3-P2` are validated, but the whole increment is not Accepted. Repository production hardening is Accepted while real production launch remains Not yet approved.

## Updating program state

When a post-program increment is proposed or delivered:

1. Create or update a named product increment scope with a stable ID, ownership, boundaries, dependencies, security/operational requirements, acceptance criteria, and explicit deferrals.
2. Create a gated implementation plan when the increment is large enough to require multiple independently reviewable delivery stages.
3. Do not create a new numbered program phase unless the baseline implementation plan itself is deliberately reopened and re-approved.
4. Update the capability matrix and indexes so implemented slices, approved remaining work, and unapproved candidates are clearly distinguished.
5. When implementation closes, create an increment-specific exit/acceptance record with the exact validated head and protected-check evidence.
6. Preserve historical phase reports rather than rewriting old evidence to sound current.
7. Record deferred work explicitly without partially implementing or documenting it as present capability.

There is no Phase 7 in the current baseline. `KINGDOMS-001` and `KINGDOMS-002` are accepted post-program product increments; `KINGDOMS-003` is the next approved increment and is now In progress with `K3-P1` and `K3-P2` validated. They are not a continuation of phase numbering. Real production cutover remains separately governed and is not implied by increment acceptance.
