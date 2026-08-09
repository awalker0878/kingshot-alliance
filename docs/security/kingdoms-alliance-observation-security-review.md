# KINGDOMS-003 Slice B observation security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-003` Slice B / `K3-P2`  
**Status:** Candidate until protected validation passes

## Security objective

Slice B adds tenant-owned, append-oriented factual history for tracked game-side alliances without weakening the neutral-reference, tenancy, privacy, authorization or internal-event boundaries locked in `K3-P0` and implemented in `K3-P1`.

## Tenant isolation

Every observation stores `alliance_id` and is linked to one `TrackedKingdomAlliance`. Reads and writes begin from the active Alliance and re-resolve submitted tracking/observation IDs under that Alliance.

A shared neutral `KingdomAlliance` reference grants no access to another tenant's:

- observation history;
- actor provenance;
- correction/invalidation state;
- manager-private reason text; or
- tracking notes.

Cross-tenant object-ID substitution must fail with no mutation or disclosure.

## Authorization and password assurance

Safe factual history is readable with `alliance.view`.

Recording, correcting or invalidating observations requires `kingdoms.manage` and the existing recent-password-confirmation middleware. No observation actor/contact/coordinator relationship creates or implies authorization.

## Kingdom-context fail-closed rule

Observation mutation requires the tracking row's captured Kingdom to equal the active platform Alliance current Kingdom and the neutral reference Kingdom to match the tracking row.

After Alliance-Kingdom drift:

- history remains readable;
- observation recording/correction/invalidation fails closed;
- no historical row or captured Kingdom is silently retargeted; and
- Slice A archive remains the explicit stale-context recovery path.

## History integrity

Normal observation changes are append-oriented.

- exact retries resolve through a deterministic SHA-256 idempotency key;
- a correction creates a replacement row and invalidates the original in one transaction;
- standalone invalidation marks the row rather than deleting it;
- repeated invalidation is idempotent;
- invalidated rows remain manager-visible historical evidence; and
- latest/freshness/member projections exclude invalidated rows.

There is no normal edit/delete route for observation facts.

## Numeric and time validation

Power is optional, non-negative and bounded to the signed 64-bit range. It is serialized as a decimal string to browser clients to prevent JavaScript integer precision loss.

Member count is optional and bounded by request validation. Missing values remain null rather than being coerced to zero.

Capture time may not be more than five minutes in the future. Latest selection uses capture time plus observation ULID tie-break rather than insertion order.

## Privacy boundary

Member payloads include only game-facing factual fields and capture/source metadata.

Manager-only fields include:

- observation ID;
- accepting actor identity;
- correction linkage;
- invalidation time/actor; and
- invalidation/correction reason.

Private reason text is excluded from audit metadata and transactional-outbox payloads. It must not be copied into logs, metrics labels or public error messages.

## Neutral identity projection

Accepted observed name/tag can refresh the shared neutral `KingdomAlliance` display identity only through the observation action. The action reprojects from the latest accepted observation for that neutral reference so insertion of an older observation does not overwrite a newer factual identity.

This shared neutral display identity remains reference data only; it never exposes tenant actor/provenance/private fields or grants cross-tenant authorization.

## Event boundary

Slice B emits only internal `kingdoms.*` durability events:

- `kingdoms.alliance_intelligence_observation_recorded`;
- `kingdoms.alliance_intelligence_observation_corrected`; and
- `kingdoms.alliance_intelligence_observation_invalidated`.

Existing Integrations policy excludes all `kingdoms.*` events from generic external webhook fan-out. Slice B adds no public API route, webhook subscription contract or external event schema.

## Abuse/non-goals

Slice B records facts only. It does not:

- calculate threat, combat-strength, desirability or composite scores;
- infer diplomacy/NAP state from observations;
- recommend attacks, punishment, recruiting, transfers or diplomatic actions;
- scrape/OCR/bot game data;
- expose a cross-alliance shared intelligence feed; or
- introduce public Kingdoms APIs/webhooks.

## Validation requirements

Protected validation must cover at minimum:

- exact retry idempotency;
- append history and capture-time ordering;
- missing-vs-zero semantics;
- correction and invalidation preservation/idempotency;
- cross-tenant tracking/observation ID tampering;
- Kingdom drift read-only behavior;
- permission/password assurance;
- member/manager payload separation;
- private-reason audit/outbox safety;
- numeric/future-time bounds;
- migration rollback/reapply; and
- architecture guards against later diplomacy/contact/scoring/ingestion/public-integration scope.
