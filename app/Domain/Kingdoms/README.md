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

## KINGDOMS-002 Slice A candidate

`K2-P0` decisions and Slice A / `K2-P1` add the candidate transfer-cycle foundation without claiming whole-increment acceptance:

- alliance-owned `TransferPlan` records with captured home-Kingdom context;
- `draft → open → locked → closed` lifecycle plus safe cancellation;
- one-open-plan concurrency protection;
- member-safe current-cycle view under `alliance.view`;
- management lifecycle surface under `kingdoms.manage` plus recent password confirmation; and
- audit/internal-outbox evidence for material lifecycle changes.

The locked decisions are recorded in [`KINGDOMS-002 Slice A decisions`](../../../docs/product/kingdoms-transfer-planning-slice-a-decisions.md), with living behavior in [`docs/domains/kingdoms-transfer-planning.md`](../../../docs/domains/kingdoms-transfer-planning.md).

Global Kingdom/player identity is never an authorization boundary. Alliance-owned roster, observations, imports, metrics, and transfer plans remain tenant-scoped even when two alliances share the same Kingdom/player references.

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

## Explicit boundaries

Slice A does **not** implement transfer participants, incoming/outgoing/staying direction, destination planning, groups/coordinators, readiness/blockers, completion/roster handoff, transfer marketplace/public advertising, diplomacy/NAP intelligence, public Kingdoms API/webhook schemas, cross-alliance rankings, automated scoring/recommendations, or automated game-data ingestion.

`KINGDOMS-002` is not Accepted until its whole-increment gate passes. A real production cutover remains separately **not approved** until the production-launch record has the required external infrastructure/operator evidence.
