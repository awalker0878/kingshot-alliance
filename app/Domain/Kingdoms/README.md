# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster-history, migration, roster-intelligence, and transfer-planning capabilities.

The accepted product increments are:

- [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md); and
- [`KINGDOMS-002` — Transfer planning](../../../docs/product/kingdoms-transfer-planning-increment.md), with evidence in the [KINGDOMS-002 exit report](../../../docs/product/kingdoms-transfer-planning-exit-report.md).

## Accepted runtime ownership

The domain owns:

- global first-class `Kingdom` references and lifecycle state;
- canonical Alliance→Kingdom resolution/association while Alliances retains ownership of the Alliance aggregate;
- global neutral `KingdomPlayer` identity scoped to a Kingdom;
- alliance-owned `AllianceRosterEntry` state, optional same-alliance membership linkage and private manager notes;
- append-only alliance-scoped `PlayerSnapshot` history with manual/CSV provenance;
- current/stale/missing latest-observation projection;
- exact roster totals/average/median, linkage/movement quality indicators and bounded 7/30-day trends;
- strict dry-run/confirm `kingdoms-roster.v1` CSV migration with explicit ambiguity resolution and idempotent confirmation;
- member/management roster exports with private-field gating and spreadsheet-formula neutralization;
- alliance-owned transfer plans with captured home-Kingdom lifecycle;
- incoming/outgoing/staying transfer participants and destination intent;
- alliance-owned transfer groups and same-alliance coordinator references;
- manual readiness transitions, blocker history and coordination summaries; and
- explicit idempotent transfer completion with accepted roster handoff.

`kingdoms.manage` protects roster/snapshot/import/transfer management and manager-only data; built-in Owner, Leader and Officer roles receive it. Ordinary roster/history/intelligence/transfer reads use `alliance.view`. Alliance→Kingdom association remains an `alliance.manage` operation. Privileged mutations require recent password confirmation under the accepted Kingdoms controls.

## `KINGDOMS-002` accepted transfer contract

Transfer planning is alliance-owned even when participants reference global Kingdom/KingdomPlayer identity.

- A plan captures the Alliance home Kingdom and supports `draft`, `open`, `locked`, `closed`, `cancelled` lifecycle states.
- Participant direction is explicit: `incoming`, `outgoing`, `staying`.
- Incoming destination is the captured plan home Kingdom; outgoing may target another active Kingdom; staying has no transfer destination.
- Transfer groups are coordination cohorts; coordinator assignment never grants authorization.
- Readiness is manual and explainable. `confirmed` remains planning-only until a separate completion action is taken.
- Private notes/blocker detail/richer handoff provenance are manager-only.
- Completion is per participant, explicit and idempotent, and occurs only in the locked-plan phase.
- Incoming completion reuses accepted roster create/link behavior; existing roster selection is explicit and never display-name-only.
- Outgoing completion reuses accepted `MarkRosterEntryLeft` behavior.
- Staying completion records the outcome without changing roster lifecycle state.
- Completion preserves neutral identity and snapshot history and never fabricates a player observation.
- A locked plan cannot close until every non-withdrawn participant has explicit completion.
- Home-Kingdom drift and cross-tenant submitted identifiers fail closed.

Coordinator assignment remains workflow responsibility only. It never grants `kingdoms.manage`, bypasses policy authorization, or changes membership permissions.

## Tenant and integration boundaries

Global Kingdom/player identity is never an authorization boundary. Alliance-owned roster, observations, imports, metrics, transfer plans, participant intent, groups, coordinator assignments, readiness, blockers, completion records, notes, and destinations remain tenant-scoped even when two alliances share Kingdom/player references.

Internal Kingdoms durability events are not external webhook contracts. `alliance.kingdom_updated` and `kingdoms.*`, including `kingdoms.transfer_*`, remain excluded from generic webhook fan-out until a separately approved integration contract exposes them.

No public Transfer/Kingdoms API route/scope is part of the accepted runtime.

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
- [`docs/security/kingdoms-transfer-planning-security-review.md`](../../../docs/security/kingdoms-transfer-planning-security-review.md)
- [`docs/product/kingdoms-transfer-planning-accessibility.md`](../../../docs/product/kingdoms-transfer-planning-accessibility.md)

Slice-specific Kingdoms security/validation records remain historical implementation evidence.

## Explicit boundaries

Accepted `KINGDOMS-002` does **not** implement transfer-resource/pass optimization, inferred eligibility/readiness, automated stay/leave decisions, player/destination rankings, bulk completion, automated in-game transfer execution, marketplace/public advertising, diplomacy/NAP intelligence, public Kingdoms API/webhook schemas, cross-alliance transfer visibility/rankings, automated scoring/recommendations, or automated game-data ingestion.

`KINGDOMS-001` and `KINGDOMS-002` are **Accepted** repository/product capabilities. A real production cutover remains separately **not yet approved** until the production-launch record has the required external infrastructure/operator evidence.
