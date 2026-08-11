# Automated game-data ingestion

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-004` `K4-P2` / Slice B complete when the containing evidence head is protected-green; `K4-P3` is the next governed slice  
**Owning domain:** Kingdoms

## 1. Purpose

Automated game-data ingestion provides a tenant-scoped path for approved machine-readable Kingshot facts without bypassing accepted Kingdoms identity, tenancy, append-history, privacy, or human-decision boundaries.

K4-P1 established the generic subscription/batch/candidate control plane. K4-P2 adds only player-snapshot promotion for an existing Alliance roster target resolved by stable game-player ID.

## 2. Scope and non-scope

Current scope includes code/config allowlisted adapters, Alliance/current-Kingdom subscriptions, batches, bounded normalized candidates, quarantine/rejection, deterministic candidate identity, manager status/control, and delegated player-snapshot promotion.

Still out of scope: a concrete production source, network acquisition, source credentials, scheduler/worker, game-Alliance observation promotion, auto roster/tracking creation, transfer/diplomacy mutation, scoring/ranking/recommendations, cross-Alliance sharing, and public Kingdoms API/webhook exposure.

## 3. Model and state

`KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` remain Alliance-owned operational state with captured Kingdom and adapter/version context.

For `player_snapshot` candidates, successful promotion records `promoted_record_type`, `promoted_record_id`, and `promoted_at`. The promoted `PlayerSnapshot` stores bounded immutable machine provenance: subscription ID, batch ID, adapter key/version, optional source record ID, source identity hash, and payload hash. Canonical snapshot history does not foreign-key back to operational candidate rows so later operational pruning cannot delete accepted history.

## 4. Invariants

1. Alliance is the tenant/authorization boundary.
2. Captured Kingdom context is never silently retargeted after drift.
3. Stable game IDs are the only automatic identity keys; names/tags/handles never match targets.
4. Player promotion requires exactly one existing neutral player in the captured Kingdom and exactly one existing roster entry for that player in the owning Alliance.
5. Unknown, ambiguous, stale-context, revoked-source, or invalid candidates quarantine before snapshot mutation.
6. Promotion never creates a roster entry, membership link, tracked game Alliance, transfer state, diplomacy state, score, ranking, or recommendation.
7. Machine promotion delegates to the accepted `RecordPlayerSnapshot` action and uses no fabricated User actor.
8. Exact candidate retry returns the existing promoted snapshot; later distinct capture time remains append-oriented history.
9. Production adapter configuration remains empty.
10. All `kingdoms.*` ingestion events remain internal and public-webhook ineligible.

## 5. Workflows

Managers may manage approved subscriptions and reject quarantined candidates under the existing K4-P1 control surface.

Internal Slice B promotion locks the subscription/candidate/batch context, verifies active state/current Kingdom/approved adapter version, resolves `stable_game_id` to a neutral `KingdomPlayer`, resolves that player to the owning Alliance roster entry, and delegates validated observation fields to `RecordPlayerSnapshot` with `source=ingestion` and machine provenance.

If the candidate is already promoted, retry resolves the recorded snapshot. A later candidate with a later capture time may append another snapshot even when observed values are unchanged.

## 6. Authorization, tenancy and privacy

Human management remains `kingdoms.manage` plus recent password confirmation. Machine promotion derives authority only from the already-owned subscription/candidate context; neutral identity or source identity never grants tenant access.

Ordinary members continue to receive observation fields/capture/source only. Manager history may include bounded source provenance. Normalized candidate bodies, source secrets, arbitrary raw responses, and unrelated private manager data are not disclosed.

## 7. Persistence and query semantics

K4-P2 extends `player_snapshots` with nullable machine provenance and allows `actor_user_id` to be null for machine-origin observations. Manual/CSV observations retain their existing human/import provenance and pre-K4 idempotency identity.

`kingdom_ingestion_candidates` stores only the safe promoted record type/ULID/timestamp. Exact machine identity is additionally unique per Alliance snapshot through `source_identity_hash`.

## 8. Events/integrations/background processing

A newly accepted machine snapshot emits the existing internal `kingdoms.player_snapshot_recorded` audit/outbox evidence with null actor and bounded source provenance. Candidate state transition emits internal `kingdoms.ingestion_candidate_promoted` evidence.

There is still no K4 scheduler, source poller, crawler, scraper, OCR worker, bot, or public machine contract.

## 9. Failure, idempotency and concurrency

Promotion fails closed or quarantines on context mismatch, Kingdom drift, source-version revocation, missing/unknown/ambiguous stable identity, missing/ambiguous Alliance roster target, or shared snapshot validation failure.

Row locking and existing uniqueness constraints protect subscription/candidate state; snapshot machine identity protects repeated promotion. No operator should rewrite hashes/context or delete canonical history to force recovery.

## 10. Operations and observability

Operators can correlate Alliance/Kingdom/subscription/batch/candidate/snapshot IDs, adapter key/version, source record ID, hashes, state/reason, capture time, and internal audit/outbox IDs. Source secrets/raw responses must not be placed in logs or evidence.

See [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

K4-P2 runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed Dependency Review `31538958810`, CodeQL `31538958745`, and CI `31538958920`: Pint 512 files, PHPStan/Larastan 364/364 with zero errors, 412 tests / 9,564 assertions, frontend/build, PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, scan, and cleanup.

Focused tests prove successful existing-roster promotion, null machine actor with bounded provenance, exact retry, later append-history capture, manager provenance disclosure, cross-tenant roster isolation/no auto-enrollment, unknown-player quarantine, Kingdom-drift quarantine, source-revocation quarantine, and migration round-trip.

See [Slice B validation](product/kingdoms-automated-ingestion-slice-b-validation.md).

## 12. Related documentation

- [KINGDOMS-004 implementation plan](product/kingdoms-automated-ingestion-implementation-plan.md)
- [K4-P0 decisions](product/kingdoms-automated-ingestion-p0-decisions.md)
- [Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice B validation](product/kingdoms-automated-ingestion-slice-b-validation.md)
- [Slice B security review](security/kingdoms-automated-ingestion-player-promotion-security-review.md)
- [Player snapshots](snapshots.md)
- [Automated ingestion operations](operations/kingdoms-automated-ingestion.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
