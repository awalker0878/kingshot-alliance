# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster-history, migration, roster-intelligence, transfer-planning, and approved alliance-intelligence/diplomacy work.

Accepted product increments:

- [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md); and
- [`KINGDOMS-002` — Transfer planning](../../../docs/product/kingdoms-transfer-planning-increment.md), with evidence in the [KINGDOMS-002 exit report](../../../docs/product/kingdoms-transfer-planning-exit-report.md).

Approved in-progress product increment:

- [`KINGDOMS-003` — Kingdom/alliance intelligence and diplomacy](../../../docs/product/kingdoms-alliance-intelligence-increment.md), with `K3-P0` locked and Slice A / `K3-P1` currently implementing neutral game-side alliance identity plus tenant tracking.

## Accepted runtime ownership

The accepted K1/K2 runtime owns:

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

## `KINGDOMS-003` Slice A candidate ownership

Slice A introduces only:

- global neutral `KingdomAlliance` reference identity scoped to one Kingdom;
- tenant-owned `TrackedKingdomAlliance` relationships with captured Kingdom context;
- active/archived tracking lifecycle and private manager notes;
- stable game alliance ID as the only automatic neutral identity resolution key;
- safe member tracking presentation and manager tracking controls; and
- attributable internal audit/outbox evidence.

A platform `Alliance` remains the tenant/authorization principal. `KingdomAlliance` never grants authentication, membership, role or permission.

Name/tag collisions never auto-merge identity. Without a stable game alliance ID, tracking deliberately creates a distinct unresolved neutral reference. Stable ID assignment is explicit and assign-once; a conflict fails closed instead of merging references.

If the platform Alliance changes Kingdom, historical tracking remains readable, ordinary tracking edits fail closed, and archive remains the safe stale-context recovery action. Captured Kingdom context is never rewritten automatically.

Slice A contains no observations, power/member facts, diplomacy/NAP state, contacts, derived intelligence, ranking/scoring, automated ingestion, cross-tenant sharing or public Kingdoms API/webhook contract.

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

Global Kingdom/player/game-alliance identity is never an authorization boundary. Alliance-owned roster, observations, imports, metrics, transfer plans, transfer details, K3 tracking state and private notes remain tenant-scoped even when two alliances share neutral references.

Internal Kingdoms durability events are not external webhook contracts. `alliance.kingdom_updated` and all `kingdoms.*` event families remain excluded from generic webhook fan-out until a separately approved integration contract exposes them.

No public Kingdoms API route/scope is part of the accepted or current K3 Slice A runtime.

## Living contracts

- [`docs/domains/kingdoms.md`](../../../docs/domains/kingdoms.md)
- [`docs/domains/kingdoms-roster.md`](../../../docs/domains/kingdoms-roster.md)
- [`docs/domains/kingdoms-snapshots.md`](../../../docs/domains/kingdoms-snapshots.md)
- [`docs/domains/kingdoms-intelligence.md`](../../../docs/domains/kingdoms-intelligence.md)
- [`docs/domains/kingdoms-csv-migration.md`](../../../docs/domains/kingdoms-csv-migration.md)
- [`docs/domains/kingdoms-transfer-planning.md`](../../../docs/domains/kingdoms-transfer-planning.md)
- [`docs/domains/kingdoms-alliance-intelligence.md`](../../../docs/domains/kingdoms-alliance-intelligence.md)
- [`docs/operations/kingdoms-roster-intelligence.md`](../../../docs/operations/kingdoms-roster-intelligence.md)
- [`docs/operations/kingdoms-transfer-planning.md`](../../../docs/operations/kingdoms-transfer-planning.md)
- [`docs/operations/kingdoms-alliance-intelligence.md`](../../../docs/operations/kingdoms-alliance-intelligence.md)
- [`docs/security/kingdoms-roster-intelligence-security-review.md`](../../../docs/security/kingdoms-roster-intelligence-security-review.md)
- [`docs/security/kingdoms-transfer-planning-security-review.md`](../../../docs/security/kingdoms-transfer-planning-security-review.md)
- [`docs/security/kingdoms-alliance-intelligence-p0-security-review.md`](../../../docs/security/kingdoms-alliance-intelligence-p0-security-review.md)
- [`docs/security/kingdoms-alliance-tracking-security-review.md`](../../../docs/security/kingdoms-alliance-tracking-security-review.md)

Slice-specific Kingdoms security/validation records remain implementation evidence; whole-increment K3 acceptance is deferred to `K3-P6`.

## Explicit boundaries

Accepted `KINGDOMS-002` does **not** implement transfer-resource/pass optimization, inferred eligibility/readiness, automated stay/leave decisions, player/destination rankings, bulk completion, automated in-game transfer execution, marketplace/public advertising, diplomacy/NAP intelligence, public Kingdoms API/webhook schemas, cross-alliance transfer visibility/rankings, automated scoring/recommendations, or automated game-data ingestion.

`KINGDOMS-003` Slice A does not weaken those boundaries. It adds neutral game-side alliance identity/tracking only; later K3 slices and future K4/K5 scopes remain explicitly separate.

`KINGDOMS-001` and `KINGDOMS-002` are **Accepted** repository/product capabilities. `KINGDOMS-003` is **In progress** on this implementation branch and is not whole-increment Accepted until `K3-P6`. A real production cutover remains separately **not yet approved**.
