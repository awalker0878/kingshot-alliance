# Kingdoms alliance intelligence and diplomacy

[← Domain documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice C1 / `K3-P3` candidate — validated tracking/observations plus explicit diplomacy/NAP lifecycle and transition history

## Purpose

`KINGDOMS-003` extends the Kingdoms domain with alliance-owned intelligence and diplomacy workflows for other game-side alliances.

- Slice A / `K3-P1` established neutral game-side alliance identity and tenant-owned tracking.
- Slice B / `K3-P2` added validated append-oriented factual observation history.
- Slice C1 / `K3-P3` adds explicit manager-maintained diplomacy state and append-oriented transition history.

Diplomacy contacts, threat/ranking/scoring, automated recommendations, automated game-data ingestion, cross-tenant intelligence sharing and public Kingdoms API/webhook contracts remain outside the current runtime slice.

## Identity and tenancy

`Alliance` is the platform tenant and authorization principal.

`KingdomAlliance` is a global neutral game-side alliance reference belonging to one `Kingdom`. It is not a tenant, user, membership, role or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship between one platform Alliance and one neutral `KingdomAlliance`. It captures Kingdom context and owns manager-private tracking notes.

`KingdomAllianceObservation`, `KingdomAllianceDiplomacy`, and `KingdomAllianceDiplomacyTransition` are tenant-owned. Sharing a neutral `KingdomAlliance` does not share observation history, diplomacy state/history, private terms/rationale, actor provenance, or later contact data.

The only automatic neutral identity key remains an approved stable `game_alliance_id` scoped to one Kingdom. Name/tag never auto-merge identity.

## Observation contract

Slice B remains authoritative for factual observations:

- observed name/tag;
- optional power/member count;
- capture time;
- manual source and actor provenance;
- deterministic exact-retry idempotency;
- correction by append plus original invalidation; and
- current/stale/missing projection using the accepted 30-day threshold.

Observations are facts only. They never infer or automatically change diplomacy state.

## Diplomacy state vocabulary

The Slice C1 vocabulary is fixed to exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

No additional state is introduced by this slice.

The member-safe default is `unknown` when no explicit relationship row exists. A manager may explicitly record any of the six states, including `unknown` when they need to record review metadata or private rationale without asserting another relationship state.

## Current relationship and transition history

`kingdom_alliance_diplomacy_relationships` stores one current relationship per Alliance + tracked alliance. It contains:

- current state;
- effective time;
- optional review time;
- optional expiry time;
- manager-private terms;
- manager-private rationale; and
- last transition actor attribution.

`kingdom_alliance_diplomacy_transitions` is append-oriented history. Each material change snapshots:

- prior state;
- new state;
- effective/review/expiry times;
- terms/rationale at that transition;
- actor; and
- recorded time.

Historical transition rows are not edited when the current relationship changes.

## Explicit-only transitions and idempotency

Diplomacy changes only through the explicit manager transition action.

Any current state may be explicitly changed to any other locked state. There is no inferred transition matrix based on power, attacks, transfer state, observations, dates, or contacts.

An exact repeat of the current state plus the same effective/review/expiry dates and normalized terms/rationale is idempotent: the current relationship is returned without appending a transition or duplicating audit/outbox evidence.

A same-state request with changed metadata is material and appends a new transition. This preserves the prior terms/rationale/date snapshot instead of silently overwriting history.

## Effective, review and expiry times

Effective time records the human-maintained effective meaning of the current relationship.

Review and expiry times are advisory planning metadata only:

- review/expiry may not precede effective time;
- when both exist, review may not be later than expiry;
- reaching either review or expiry time does not mutate current state; and
- `needs_review` is derived at read time when either due time has arrived.

No scheduler or background transition process exists for diplomacy.

## Read and privacy surfaces

The ordinary tracked-alliance list remains `alliance.view` and exposes only member-safe diplomacy data:

- current diplomacy label; and
- review-due indicator.

Members do not receive:

- diplomacy relationship IDs;
- transition IDs/history;
- effective/review/expiry timestamps from the manager workflow;
- actor attribution;
- private terms; or
- private rationale.

The dedicated diplomacy workspace is `kingdoms.manage` only. It shows current relationship metadata and bounded transition history capped at the latest **250** rows.

## Kingdom drift and tracking lifecycle

Tracking captures `kingdom_id` when created. If the platform Alliance later changes Kingdom:

- tracking, observation history, and diplomacy history remain readable to authorized users;
- normal diplomacy mutation fails closed;
- current/history rows are never silently retargeted to the new Kingdom; and
- Slice A archival remains the stale-context recovery action.

Archiving a tracking record also preserves its diplomacy relationship/history. Archived tracking is read-only for new diplomacy transitions.

## Authorization

- safe tracked-alliance list/history reads: `alliance.view`;
- diplomacy workspace read: `kingdoms.manage`;
- diplomacy transition: `kingdoms.manage` + recent password confirmation;
- submitted tracking IDs are re-resolved under the active Alliance;
- neutral reference and captured Kingdom context are revalidated under row locks before mutation; and
- no role-name controller checks or contact/coordinator-derived permissions are introduced.

## Audit and outbox

Every material transition emits attributable internal durability evidence using:

- `kingdoms.diplomacy_transitioned`.

Event metadata may contain relationship/transition/tracking/reference IDs, state changes, and non-private dates.

Private terms and rationale are deliberately excluded from audit/outbox metadata. Existing Integration rules keep all `kingdoms.*` events out of generic external webhook fan-out.

Idempotent exact repeats emit no duplicate event.

## Persistence and indexes

Current K3 tables are:

- `kingdom_alliances`;
- `tracked_kingdom_alliances`;
- `kingdom_alliance_observations`;
- `kingdom_alliance_diplomacy_relationships`; and
- `kingdom_alliance_diplomacy_transitions`.

The current relationship has one Alliance + tracking unique constraint. Tenant-first state/review/expiry indexes support future bounded descriptive queries without creating a ranking or scheduler contract. Transition history uses tenant/tracking and relationship/time indexes.

Slice C1 adds no contact, `KingdomPlayer` contact link, threat/rank/score, recommendation, ingestion, scraping/OCR/bot, public API, or webhook schema fields.

## Explicit non-behavior

Slice C1 does not:

- infer relationship state from observations, attacks, power, member count, transfer state, or contact data;
- auto-transition on review/expiry;
- rank alliances or calculate threat/desirability scores;
- predict combat outcomes;
- recommend diplomacy actions;
- create/send negotiation messages;
- change transfer destination/readiness/completion;
- add diplomacy contacts or public contact data;
- ingest game data automatically; or
- expose K3 data through public API/webhooks.

## Deferred slices

- `K3-P4` — manager-private diplomacy contacts;
- `K3-P5` — descriptive intelligence views/trends;
- `K3-P6` — whole-increment hardening and acceptance.

No later-slice schema or runtime behavior is introduced by `K3-P3`.
