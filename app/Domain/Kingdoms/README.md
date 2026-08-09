# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster-history, migration, roster-intelligence, and approved transfer-planning capabilities.

The accepted baseline is [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with accepted evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md). The approved follow-on is [`KINGDOMS-002` — Transfer planning](../../../docs/product/kingdoms-transfer-planning-increment.md), sequenced by the [KINGDOMS-002 implementation plan](../../../docs/product/kingdoms-transfer-planning-implementation-plan.md).

## Accepted runtime ownership

The complete `KINGDOMS-001` implementation is **Accepted**. The domain owns:

- global first-class `Kingdom` references and lifecycle state;
- canonical Alliance→Kingdom resolution/association while Alliances retains ownership of the Alliance aggregate;
- global neutral `KingdomPlayer` identity scoped to a Kingdom;
- alliance-owned `AllianceRosterEntry` state, optional same-alliance membership linkage and private manager notes;
- append-only alliance-scoped `PlayerSnapshot` history with manual/CSV provenance;
- current/stale/missing latest-observation projection;
- exact roster totals/average/median, linkage/movement quality indicators and bounded 7/30-day trends;
- strict dry-run/confirm `kingdoms-roster.v1` CSV migration with explicit ambiguity resolution and idempotent confirmation; and
- member/management roster exports with private-field gating and spreadsheet-formula neutralization.

`kingdoms.manage` protects roster/snapshot/import management and manager-only data; built-in Owner, Leader and Officer roles receive it. Ordinary roster/history/aggregate intelligence reads use `alliance.view`. Alliance→Kingdom association remains an `alliance.manage` operation. Privileged mutations require recent password confirmation.

## KINGDOMS-002 delivery state

Slice A / `K2-P1` transfer-cycle foundation is validated at `e939a09d107ee12bd19ce8b2b8c27d5bba5f0e6c`.

Slice B / `K2-P2` participant direction and destination is validated at `03f6b3009551f526b6c54f8d59749e640e636b4a`, with evidence in [`kingdoms-transfer-planning-slice-b-validation.md`](../../../docs/product/kingdoms-transfer-planning-slice-b-validation.md).

Slice C1 / `K2-P3` transfer groups and coordinators is validated at `9d2f70056db901203d8811ba3d5d19d40727accf`, with evidence in [`kingdoms-transfer-planning-slice-c1-validation.md`](../../../docs/product/kingdoms-transfer-planning-slice-c1-validation.md).

Slice C2 / `K2-P4` readiness and blockers is validated at `e3f411b2cb775639f68976601ee03e2a76cc6876`, with evidence in [`kingdoms-transfer-planning-slice-c2-validation.md`](../../../docs/product/kingdoms-transfer-planning-slice-c2-validation.md).

Slice D / `K2-P5` is the current candidate and adds explicit real-world completion and roster handoff:

- one alliance/plan/participant-scoped `TransferCompletion` per participant as the idempotency boundary;
- completion only after the plan is `locked`, participant readiness is `confirmed`, and captured home-Kingdom context still matches the Alliance;
- incoming completion through accepted `SaveRosterEntry` create/update behavior;
- explicit existing-roster selection for incoming handoff, never display-name-only matching;
- outgoing completion through accepted `MarkRosterEntryLeft` behavior;
- staying completion as a recorded outcome with no roster lifecycle mutation;
- preservation of neutral player identity and snapshot history, with no fabricated snapshot during completion;
- attributable completion audit/internal-outbox evidence;
- manager-only completion actor/roster-result provenance, with only safe completion time exposed to ordinary members;
- a dedicated manager Completion workspace; and
- a close invariant requiring every non-withdrawn participant to be explicitly completed before a locked plan can become closed.

Readiness remains manual planning state. `confirmed` alone does not mutate the roster. Completion is a separate explicit manager action and there is no bulk-complete path.

Coordinator assignment remains workflow responsibility only. It never grants `kingdoms.manage`, bypasses policy authorization, or changes the permissions attached to the coordinator's alliance membership.

The living transfer contract is [`docs/domains/kingdoms-transfer-planning.md`](../../../docs/domains/kingdoms-transfer-planning.md).

Global Kingdom/player identity is never an authorization boundary. Alliance-owned roster, observations, imports, metrics, transfer plans, participant intent, groups, coordinator assignments, readiness, blockers, completion records, notes, and destinations remain tenant-scoped even when two alliances share the same Kingdom/player references.

Internal Kingdoms durability events are not external webhook contracts. `alliance.kingdom_updated` and `kingdoms.*` events remain excluded from generic webhook fan-out until a separately approved integration contract exposes them.

## Living contracts

- [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md)
- [`docs/domains/kingdoms-roster.md`](../../../docs/domains/kingdoms-roster.md)
- [`docs/domains/kingdoms-snapshots.md`](../../../docs/domains/kingdoms-snapshots.md)
- [`docs/domains/kingdoms-intelligence.md`](../../../docs/domains/kingdoms-intelligence.md)
- [`docs/domains/kingdoms-csv-migration.md`](../../../docs/domains/kingdoms-csv-migration.md)
- [`docs/domains/kingdoms-transfer-planning.md`](../../../docs/domains/kingdoms-transfer-planning.md)
- [`docs/operations/kingdoms-roster-intelligence.md`](../../../docs/operations/kingdoms-roster-intelligence.md)
- [`docs/operations/kingdoms-transfer-planning.md`](../../../docs/operations/kingdoms-transfer-planning.md)
- [`docs/security/kingdoms-roster-intelligence-security-review.md`](../../../docs/security/kingdoms-roster-intelligence-security-review.md)
- [`docs/security/kingdoms-transfer-planning-foundation-security-review.md`](../../../docs/security/kingdoms-transfer-planning-foundation-security-review.md)
- [`docs/security/kingdoms-transfer-participant-security-review.md`](../../../docs/security/kingdoms-transfer-participant-security-review.md)
- [`docs/security/kingdoms-transfer-group-security-review.md`](../../../docs/security/kingdoms-transfer-group-security-review.md)
- [`docs/security/kingdoms-transfer-readiness-security-review.md`](../../../docs/security/kingdoms-transfer-readiness-security-review.md)
- [`docs/security/kingdoms-transfer-completion-security-review.md`](../../../docs/security/kingdoms-transfer-completion-security-review.md)

## Explicit boundaries

Slice D does **not** implement transfer-resource/pass optimization, inferred eligibility/readiness, automated stay/leave decisions, bulk completion, automated in-game transfer execution, marketplace/public advertising, diplomacy/NAP intelligence, public Kingdoms API/webhook schemas, cross-alliance transfer visibility/rankings, automated scoring/recommendations, or automated game-data ingestion.

`KINGDOMS-002` remains **In progress** until whole-increment hardening and acceptance / `K2-P6` passes. A real production cutover remains separately **not approved** until the production-launch record has the required external infrastructure/operator evidence.
