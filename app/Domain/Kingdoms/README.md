# Kingdoms domain

`Kingdoms` is the canonical owner for approved Kingshot game-world reference, roster history/intelligence, transfer planning, and the in-progress game-side alliance intelligence/diplomacy capability.

## Product status

Accepted product increments:

- [`KINGDOMS-001` — Kingdoms roster intelligence](../../../docs/product/kingdoms-roster-intelligence-increment.md), with evidence in the [KINGDOMS-001 exit report](../../../docs/product/kingdoms-roster-intelligence-exit-report.md); and
- [`KINGDOMS-002` — Transfer planning](../../../docs/product/kingdoms-transfer-planning-increment.md), with evidence in the [KINGDOMS-002 exit report](../../../docs/product/kingdoms-transfer-planning-exit-report.md).

Approved in-progress product increment:

- [`KINGDOMS-003` — Kingdom/alliance intelligence and diplomacy](../../../docs/product/kingdoms-alliance-intelligence-increment.md).

`KINGDOMS-003` delivery status:

- `K3-P0` — design/security contract lock: **Complete**;
- Slice A / `K3-P1` — neutral game-side alliance identity + tenant tracking: **Validated**;
- Slice B / `K3-P2` — append-oriented alliance observations: **Validated**;
- Slice C1 / `K3-P3` — explicit diplomacy/NAP lifecycle: **Validated**;
- Slice C2 / `K3-P4` — manager-private diplomacy contacts: **Validated**;
- Slice D / `K3-P5` — descriptive alliance intelligence dashboard/trends: **Validated**; and
- `K3-P6` — whole-increment hardening/acceptance: **Remaining**.

Slice D validated runtime: `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75`. `KINGDOMS-003` is still **In progress**, not whole-increment Accepted, and does not approve production cutover.

## Domain identity and tenancy

A platform `Alliance` remains the tenant and authorization principal.

Global neutral references include:

- `Kingdom` — game-world Kingdom reference;
- `KingdomPlayer` — neutral player identity scoped to a Kingdom; and
- `KingdomAlliance` — neutral game-side alliance identity scoped to a Kingdom.

Global neutral references never grant tenant access. Alliance-owned roster history, transfer plans, game-side alliance tracking, observations, diplomacy, contacts, private notes and derived intelligence remain scoped to the active platform Alliance.

Stable game identifiers are the only automatic neutral identity-resolution keys. Player/alliance display names, tags and diplomacy-contact handles never auto-merge or auto-link identity.

## Accepted K1/K2 ownership

The accepted `KINGDOMS-001` / `KINGDOMS-002` runtime owns:

- global Kingdom and KingdomPlayer references;
- canonical Alliance→Kingdom association;
- alliance-owned roster entries and append-only snapshots;
- current/stale/missing roster observation projection;
- exact roster totals/average/median and bounded 7/30-day roster trends;
- strict dry-run/confirm CSV roster migration;
- member/manager exports with private-field gating and spreadsheet-formula neutralization;
- alliance-owned transfer plans, participants and groups;
- manual readiness and blocker history; and
- explicit idempotent per-participant transfer completion with accepted roster handoff.

Coordinator assignment remains workflow responsibility only and never grants authorization.

## `KINGDOMS-003` runtime ownership through Slice D

### Tracking foundation

`KingdomAlliance` is a global neutral game-side alliance reference. `TrackedKingdomAlliance` is an Alliance-owned relationship to that neutral reference and captures Kingdom context.

Tracking is active/archive lifecycle only. A stable game alliance ID is the only automatic resolution key. Name/tag collisions never auto-merge references. Alliance-Kingdom drift preserves historical reads while normal mutations fail closed.

### Observation history

`KingdomAllianceObservation` is Alliance-owned factual history containing observed name/tag, optional power/member count, capture time, provenance, deterministic retry idempotency, correction linkage and invalidation evidence.

Missing values remain distinct from zero. Invalidated rows remain historical but are excluded from accepted current/trend projections. Observations never infer diplomacy.

### Explicit diplomacy

`KingdomAllianceDiplomacy` stores one current Alliance-owned relationship per tracked game-side alliance, with append-oriented `KingdomAllianceDiplomacyTransition` history.

The state vocabulary is exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Diplomacy changes only through explicit manager action. Review/expiry timestamps are advisory and only derive a human-review indicator; they never automatically change state. Private terms/rationale and actor history remain manager-private.

### Manager-private diplomacy contacts

`KingdomAllianceDiplomacyContact` stores minimal Alliance-owned coordination data: display name, optional game-side role, approved handle channel, handle, active/inactive lifecycle, optional last-verification time, private notes and actor/lifecycle provenance.

Contacts do not link to `KingdomPlayer`, `User`, `AllianceMembership`, roles or permissions. Duplicate names/handles remain distinct. Contact assignment cannot create an account, membership or authorization. Normal lifecycle deactivates rather than destructively deletes history.

Ordinary member payloads contain no contact IDs/detail. Private contact text is excluded from audit/outbox metadata.

### Descriptive alliance intelligence

`KingdomAllianceIntelligence` is a read-only Alliance-scoped projection over accepted tracking/observation/diplomacy/contact facts. It adds no migration, persistent score, mutation, audit event or outbox event.

It provides:

- active tracked-alliance count;
- current/stale/missing observation counts;
- explicit diplomacy-state counts;
- relationships whose review/expiry time requires human review;
- latest accepted power/member/capture facts;
- immediately-prior factual change;
- bounded 7-day change using a 7–14-day baseline window;
- bounded 30-day change using a 30–60-day baseline window;
- observation age/freshness; and
- manager-only aggregate contact availability/verification diagnostics.

Trend selection never interpolates. If a supported baseline/value is missing, the corresponding trend is missing/insufficient history rather than estimated. Recorded zero remains zero.

Default ordering is neutral name order. Fixed factual sorting may be used for navigation, but Slice D calculates no threat, target, desirability, combat, punitive or composite score and generates no recommendation or automatic diplomacy/transfer action.

The validated realistic-volume gate models 120 tracked alliances, 600 observations, 120 diplomacy relationships and 60 contacts with the manager projection capped at 10 SELECT statements.

## Authorization and privacy

- ordinary safe Kingdoms reads use `alliance.view`;
- Kingdoms mutations and manager-private workspaces use `kingdoms.manage`;
- privileged mutations require recent password confirmation; and
- Slice D's intelligence dashboard is read-only, so `kingdoms.manage` only gates aggregate contact diagnostics/manager links rather than the member-safe dashboard itself.

Policy/permission authorization remains authoritative. Controllers do not use role-name authorization shortcuts.

Global neutral identity is never an authorization boundary, including when multiple tenants reference the same KingdomPlayer or KingdomAlliance.

## Integration boundary

Internal Kingdoms durability events are not external webhook contracts. `alliance.kingdom_updated` and all `kingdoms.*` event families remain excluded from generic webhook fan-out until a separately approved integration contract defines a public schema.

No public Kingdoms API route/scope is part of K1/K2 or the current K3 sliced runtime.

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
- [`docs/security/kingdoms-alliance-intelligence-dashboard-security-review.md`](../../../docs/security/kingdoms-alliance-intelligence-dashboard-security-review.md)
- [`docs/product/kingdoms-alliance-intelligence-slice-d-validation.md`](../../../docs/product/kingdoms-alliance-intelligence-slice-d-validation.md)

## Explicit K3 boundaries

`KINGDOMS-003` through validated Slice D does **not** add player/contact identity linkage, phone/address/credential storage, cross-tenant shared intelligence, automated game-data ingestion, scraping/OCR/bots, public Kingdoms APIs/webhooks, threat/ranking/scoring, combat prediction, punitive/strategic recommendations, automated negotiation, automatic expiry transitions, automated diplomacy, or automatic transfer behavior.

`KINGDOMS-001` and `KINGDOMS-002` are **Accepted** repository/product capabilities. `KINGDOMS-003` remains **In progress** until `K3-P6` whole-increment acceptance. Real production cutover remains separately **not yet approved**.
