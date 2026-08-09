# Kingdoms alliance intelligence and diplomacy

[← Domain documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice B / `K3-P2` candidate — neutral alliance tracking plus append-oriented factual observations

## Purpose

`KINGDOMS-003` extends the Kingdoms domain with alliance-owned intelligence and diplomacy workflows for other game-side alliances. Slice A established neutral game-side alliance identity and tenant-owned tracking. Slice B adds append-oriented factual observation history only.

Diplomacy/NAP state, contacts, threat/ranking/scoring, automated recommendations, automated game-data ingestion, cross-tenant intelligence sharing and public Kingdoms API/webhook contracts remain outside the current runtime slice.

## Identity and tenancy

`Alliance` is the platform tenant and authorization principal.

`KingdomAlliance` is a global neutral game-side alliance reference belonging to one `Kingdom`. It is not a tenant, user, membership, role or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship between one platform Alliance and one neutral `KingdomAlliance`. It captures Kingdom context and owns manager-private tracking notes.

`KingdomAllianceObservation` is tenant-owned factual history. Every observation stores both the owning Alliance and tracked/neutral references so reads and mutations can be re-resolved under the active tenant. Sharing a neutral `KingdomAlliance` does not share observation history, actor provenance, invalidation detail or manager-private correction reasons.

The only automatic neutral identity key remains an approved stable `game_alliance_id` scoped to one Kingdom. Name/tag never auto-merge identity.

## Observation contract

An observation records:

- Alliance ownership;
- tracked and neutral game-side alliance references;
- accepting actor provenance;
- observed name and optional tag;
- optional power;
- optional member count;
- capture timestamp;
- source (`manual` in `K3-P2`);
- deterministic SHA-256 idempotency key;
- optional correction link to an earlier observation;
- explicit invalidation time/actor; and
- optional manager-private invalidation/correction reason.

Power is optional, non-negative and bounded to the signed 64-bit range. It is serialized to browser clients as a decimal string to avoid JavaScript precision loss. Member count is optional, non-negative and bounded by the first-party validation contract. Missing power/member count remains distinct from zero.

Capture time may not be more than five minutes in the future.

## Append-oriented history and idempotency

Normal observation recording never edits or deletes historical observations.

The deterministic idempotency key covers the Alliance, tracking/reference identity, observed fields, canonical power/member values, canonical capture time, source and optional corrected-observation ID. An exact retry returns the existing observation and emits no duplicate audit/outbox event.

Changing capture time or factual values creates a legitimate new observation.

A correction is explicit:

1. append the replacement observation;
2. link it to the accepted observation being corrected;
3. invalidate the original in the same transaction; and
4. preserve the original row, actor, values and capture time.

Standalone invalidation also preserves the row. Repeating invalidation is idempotent.

Private correction/invalidation reasons are not copied into member payloads, audit metadata or outbox payloads.

## Latest accepted projection

Historical observations remain the source of truth.

The latest accepted observation is selected by:

1. greatest `captured_at`; then
2. greatest observation ULID as deterministic tie-breaker.

Insertion order therefore does not override capture time. Invalidated observations are excluded from latest/freshness projections.

Accepted observed name/tag may update the neutral reference current display identity only through the observation action. The action reprojects from the latest accepted observation for that neutral reference so an older observation inserted later does not overwrite newer factual identity.

## Freshness

Slice B intentionally reuses the accepted Kingdoms snapshot threshold: **30 days**.

- **current** — latest accepted observation was captured within the last 30 days;
- **stale** — accepted history exists but the latest accepted observation is older than 30 days;
- **missing** — no accepted observation exists.

Freshness is a data-quality indicator only. It is not a strength, risk, desirability or diplomacy score.

## Read and privacy surfaces

The tracked-alliance list projects only the latest accepted observation per tracking row. The history page is bounded to the latest **250** observations ordered by capture time.

Members with `alliance.view` receive:

- safe tracked-alliance identity/context;
- current/stale/missing freshness;
- latest accepted observed name/tag/power/member count/capture/source; and
- accepted historical factual rows.

Members do not receive observation IDs, actor identity, invalidation metadata or private reasons.

Managers with `kingdoms.manage` receive the additional IDs/provenance/invalidation detail needed for correction and invalidation workflows.

## Kingdom drift

Tracking captures `kingdom_id` when created. If the platform Alliance later changes Kingdom:

- tracking and observation history remain readable;
- recording/correcting/invalidating observations fails closed;
- captured Kingdom/history is never silently retargeted; and
- Slice A archival remains the stale-context recovery action.

## Authorization

- safe list/history reads: `alliance.view`;
- record/correct/invalidate observation: `kingdoms.manage`;
- all observation mutations require recent password confirmation;
- submitted tracking/observation IDs are re-resolved under the active Alliance;
- no role-name controller checks or coordinator-derived permissions are introduced.

## Audit and outbox

Material observation changes emit attributable internal durability evidence:

- `kingdoms.alliance_intelligence_observation_recorded`;
- `kingdoms.alliance_intelligence_observation_corrected`; and
- `kingdoms.alliance_intelligence_observation_invalidated`.

Exact retries and repeated invalidation do not emit duplicate events. Existing Integration rules keep all `kingdoms.*` events out of generic external webhook fan-out.

## Persistence and indexes

`kingdom_alliance_observations` is tenant-owned and uses tenant-first indexes for tracking history/latest selection plus a unique Alliance + idempotency-key constraint. Neutral-reference/capture indexing supports deterministic current display projection without moving tenant history onto the global reference.

The Slice A tables remain unchanged and deliberately contain no observation columns.

## Deferred slices

- `K3-P3` — explicit diplomacy/NAP lifecycle;
- `K3-P4` — manager-private diplomacy contacts;
- `K3-P5` — descriptive intelligence views/trends;
- `K3-P6` — whole-increment hardening and acceptance.

No later-slice schema or runtime behavior is introduced by `K3-P2`.
