# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster-history, migration and roster-intelligence capabilities.

The governing increment is [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with delivery sequencing in the [KINGDOMS-001 implementation plan](../../../docs/product/kingdoms-roster-intelligence-implementation-plan.md) and accepted evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md).

## Current runtime ownership

The complete `KINGDOMS-001` implementation is **Accepted**. `K1-P1` through `K1-P5` delivered the implementation slices and `K1-P6` closed whole-increment hardening and acceptance.

The domain owns:

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

Global Kingdom/player identity is never an authorization boundary. Alliance-owned roster, notes, snapshots, imports, exports and metrics remain tenant-scoped even when two alliances share the same Kingdom/player references.

Internal Kingdoms durability events are audited/outboxed but are not external webhook contracts. `alliance.kingdom_updated` and `kingdoms.*` events are excluded from generic webhook fan-out until a separately approved integration contract exposes them.

The living contracts are:

- [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md)
- [`docs/domains/kingdoms-roster.md`](../../../docs/domains/kingdoms-roster.md)
- [`docs/domains/kingdoms-snapshots.md`](../../../docs/domains/kingdoms-snapshots.md)
- [`docs/domains/kingdoms-intelligence.md`](../../../docs/domains/kingdoms-intelligence.md)
- [`docs/domains/kingdoms-csv-migration.md`](../../../docs/domains/kingdoms-csv-migration.md)
- [`docs/operations/kingdoms-roster-intelligence.md`](../../../docs/operations/kingdoms-roster-intelligence.md)
- [`docs/security/kingdoms-roster-intelligence-security-review.md`](../../../docs/security/kingdoms-roster-intelligence-security-review.md)

## Explicit boundaries

Transfer planning, diplomacy/NAP intelligence, public Kingdoms roster/intelligence API or webhook schemas, cross-alliance rankings, automated scoring/recommendations and automated game-data ingestion remain unapproved follow-on scope.

`KINGDOMS-001` acceptance is a repository/product decision. A real production cutover remains separately **not approved** until the production-launch record has the required external infrastructure/operator evidence.
