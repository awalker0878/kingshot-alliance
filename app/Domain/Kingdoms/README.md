# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster-history, migration, roster-intelligence, transfer-planning, and approved alliance-intelligence/diplomacy work.

Accepted product increments:

- [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md); and
- [`KINGDOMS-002` — Transfer planning](../../../docs/product/kingdoms-transfer-planning-increment.md), with evidence in the [KINGDOMS-002 exit report](../../../docs/product/kingdoms-transfer-planning-exit-report.md).

Approved in-progress product increment:

- [`KINGDOMS-003` — Kingdom/alliance intelligence and diplomacy](../../../docs/product/kingdoms-alliance-intelligence-increment.md), with `K3-P0` locked, Slice A / `K3-P1` validated, Slice B / `K3-P2` validated, Slice C1 / `K3-P3` fully protected-green, and Slice C2 / `K3-P4` currently adding manager-private diplomacy contacts.

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

## `KINGDOMS-003` current sliced ownership

### Slice A / `K3-P1` — validated

Slice A introduced global neutral `KingdomAlliance` reference identity, tenant-owned `TrackedKingdomAlliance`, captured Kingdom context, active/archive tracking lifecycle, stable-game-ID-only automatic resolution, member/manager tracking views and attributable internal durability evidence.

A platform `Alliance` remains the tenant/authorization principal. `KingdomAlliance` never grants authentication, membership, role or permission. Name/tag collisions never auto-merge identity.

### Slice B / `K3-P2` — validated

Slice B introduced tenant-owned `KingdomAllianceObservation` factual history with:

- observed name/tag;
- optional power/member count;
- capture time;
- manual source and actor provenance;
- deterministic exact-retry idempotency;
- correction by append plus original invalidation; and
- current/stale/missing projection using the existing 30-day Kingdoms threshold.

Missing values remain distinct from zero. Invalidated rows remain history but are excluded from member/latest projection. Private correction/invalidation reasons are manager-only and excluded from audit/outbox metadata.

Observations remain facts only and never infer diplomacy.

### Slice C1 / `K3-P3` — protected-green dependency

Slice C1 adds one alliance-owned `KingdomAllianceDiplomacy` current relationship per tracked game-side alliance plus append-oriented `KingdomAllianceDiplomacyTransition` history.

The state vocabulary is fixed to exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Diplomacy changes only through an explicit manager action. Any current state may be explicitly changed to any other locked state. A same-state request with changed effective/review/expiry/terms/rationale metadata is still material and appends history; an exact repeat of the current normalized meaning is idempotent.

Current relationship state stores effective time, optional review/expiry times, manager-private terms/rationale and last transition attribution. Every material change snapshots those values into append-oriented transition history.

Review/expiry dates are advisory only. Passing them sets a derived review-due indicator at read time and never mutates relationship state. There is no diplomacy scheduler or observation/transfer/combat hook that changes diplomacy automatically.

Ordinary members receive only the current diplomacy label and review-due indicator on the tracked-alliance list. The dedicated diplomacy workspace, actor/history data, terms and rationale require `kingdoms.manage`. Transition mutations also require recent password confirmation.

Private terms/rationale are excluded from audit/outbox metadata. Material changes emit only internal `kingdoms.diplomacy_transitioned` evidence.

If Alliance Kingdom context drifts or tracking is archived, diplomacy history remains manager-readable but new transitions fail closed. History is never silently retargeted.

### Slice C2 / `K3-P4` — candidate

Slice C2 adds tenant-owned `KingdomAllianceDiplomacyContact` coordination records for an Alliance's tracked game-side alliance.

Contact data is intentionally minimal and manager-private:

- display name;
- optional game-side role/title;
- handle-based channel type (`in_game`, `discord`, or explicitly labelled other handle/channel);
- handle/identifier;
- active/inactive state;
- optional last-verified time;
- manager-private notes; and
- actor/lifecycle provenance.

Contacts do not link to `KingdomPlayer`, `User`, `AllianceMembership`, roles or permissions. Display names and handles have no uniqueness constraint and never auto-merge or auto-link identity. Contact assignment cannot grant authorization.

The contact workspace requires `kingdoms.manage`; create/update/deactivate additionally require recent password confirmation. Submitted tracking/contact IDs are re-resolved under the active Alliance and current Kingdom context before mutation.

Normal lifecycle preserves history: active contacts may be edited, exact active-update retries are idempotent, deactivation is idempotent, and inactive contacts remain readable rather than being destructively deleted or rewritten.

Ordinary member payloads contain no contact IDs, names, handles, notes, verification data or actor/lifecycle provenance. Private contact text is excluded from audit/outbox payloads. Contact changes never infer or transition diplomacy state.

Material changes emit only internal `kingdoms.diplomacy_contact_saved` and `kingdoms.diplomacy_contact_deactivated` evidence.

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

Global Kingdom/player/game-alliance identity is never an authorization boundary. Alliance-owned roster history, K3 tracking relationships, observation history, diplomacy current/history/private terms/rationale, diplomacy contacts, imports, metrics, transfer plans and private notes remain tenant-scoped even when two platform Alliances share neutral references.

Internal Kingdoms durability events are not external webhook contracts. `alliance.kingdom_updated` and all `kingdoms.*` event families remain excluded from generic webhook fan-out until a separately approved integration contract exposes them.

No public Kingdoms API route/scope is part of the accepted or current K3 sliced runtime.

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
- [`docs/security/kingdoms-alliance-observation-security-review.md`](../../../docs/security/kingdoms-alliance-observation-security-review.md)
- [`docs/security/kingdoms-alliance-diplomacy-security-review.md`](../../../docs/security/kingdoms-alliance-diplomacy-security-review.md)
- [`docs/security/kingdoms-alliance-diplomacy-contact-security-review.md`](../../../docs/security/kingdoms-alliance-diplomacy-contact-security-review.md)

Slice-specific Kingdoms security/validation records remain implementation evidence; whole-increment K3 acceptance is deferred to `K3-P6`.

## Explicit boundaries

Accepted `KINGDOMS-002` does **not** implement transfer-resource/pass optimization, inferred eligibility/readiness, automated stay/leave decisions, player/destination rankings, bulk completion, automated in-game transfer execution, marketplace/public advertising, diplomacy/NAP intelligence, public Kingdoms API/webhook schemas, cross-alliance transfer visibility/rankings, automated scoring/recommendations, or automated game-data ingestion.

`KINGDOMS-003` through Slice C2 adds neutral game-side alliance tracking, factual observation history, explicit human-maintained diplomacy state/history, and manager-private handle-based diplomacy contacts only. It does **not** add player/contact identity linkage, phone/address/credential storage, threat/ranking/scoring, combat prediction, automated recommendations, automated negotiation, automatic expiry transitions, game-data scraping/OCR/bots, cross-tenant shared intelligence, public Kingdoms API/webhooks, or automatic transfer behavior.

`KINGDOMS-001` and `KINGDOMS-002` are **Accepted** repository/product capabilities. `KINGDOMS-003` is **In progress** and is not whole-increment Accepted until `K3-P6`. A real production cutover remains separately **not yet approved**.
