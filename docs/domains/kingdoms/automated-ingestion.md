# Automated game-data ingestion

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-004` through `K4-P3` / Slice C complete when the containing evidence head is protected-green; `K4-P4` is next  
**Owning domain:** Kingdoms

## 1. Purpose

Automated game-data ingestion provides a tenant-scoped path for approved machine-readable Kingshot facts without bypassing accepted Kingdoms identity, tenancy, append-history, privacy, or human-decision boundaries.

K4-P1 established the generic subscription/batch/candidate control plane. K4-P2 added existing-roster player-snapshot promotion. K4-P3 adds existing-active-tracking game-Alliance observation promotion.

## 2. Scope and non-scope

Current scope includes code/config allowlisted adapters, Alliance/current-Kingdom subscriptions, batches, bounded normalized candidates, quarantine/rejection, deterministic candidate identity, manager status/control, and delegated promotion of factual player snapshots and game-Alliance observations.

Still out of scope: a concrete production source, network acquisition, source credentials, scheduler/worker, automatic roster/tracking creation/reactivation, machine correction/invalidation, transfer/diplomacy/contact mutation, scoring/ranking/recommendations, cross-Alliance sharing, and public Kingdoms API/webhook exposure.

## 3. Model and state

`KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` are Alliance-owned operational state with captured Kingdom and adapter/version context.

Successful promotion records a safe promoted record type/ULID/time on the candidate. Promoted `PlayerSnapshot` and `KingdomAllianceObservation` rows store bounded immutable machine provenance: subscription ID, batch ID, adapter key/version, optional source record ID, source identity hash, and payload hash. Canonical history does not foreign-key back to operational candidate rows.

## 4. Invariants

1. Alliance is the tenant/authorization boundary.
2. Captured Kingdom context is never silently retargeted after drift.
3. Stable game IDs are the only automatic identity keys; names/tags/handles never match targets.
4. Player promotion requires one existing neutral player and one existing owning-Alliance roster entry.
5. Game-Alliance promotion requires one active neutral game Alliance and one existing active owning-Alliance tracking relationship.
6. Unknown, ambiguous, inactive, stale-context, revoked-source, or invalid candidates quarantine before business mutation.
7. Promotion never creates or reactivates roster/tracking/membership/transfer/diplomacy/contact state.
8. Machine game-Alliance observations are append-only; correction/invalidation remains human-only.
9. Machine promotion delegates to accepted K1/K3 record actions and uses no fabricated User actor.
10. Exact candidate retry returns the existing promoted record; later distinct capture remains append-oriented history.
11. Production adapter configuration remains empty.
12. All `kingdoms.*` ingestion events remain internal and public-webhook ineligible.

## 5. Workflows

Managers may manage approved subscriptions and reject quarantined candidates under the K4-P1 control surface.

Player promotion resolves stable game-player identity and existing owning-Alliance roster relationship before delegating to `RecordPlayerSnapshot`.

Game-Alliance promotion resolves stable game-Alliance identity and an existing active `TrackedKingdomAlliance` before delegating to `RecordKingdomAllianceObservation`. It cannot supply correction linkage or correction reason, and it never creates/reactivates tracking.

If a candidate is already promoted, retry resolves the recorded canonical record. A later capture may append another factual observation even when the source record identifier is unchanged.

## 6. Authorization, tenancy and privacy

Human management remains `kingdoms.manage` plus recent password confirmation. Machine promotion derives authority only from already-owned subscription/candidate context; neutral identity or source identity never grants tenant access.

Ordinary members continue to receive factual observation fields/capture/source only. Manager history may include bounded source provenance. Normalized candidate bodies, source secrets/raw responses, diplomacy/contact private data, and unrelated private manager data are not disclosed.

## 7. Persistence and query semantics

K4-P2 extends player snapshots with nullable machine provenance and null actor for machine origin. K4-P3 similarly extends game-Alliance observations with bounded machine provenance while preserving the accepted K3 correction/invalidation columns for manual governance only.

`kingdom_ingestion_candidates` stores only safe promoted record type/ULID/timestamp. Exact machine identity is additionally unique per Alliance promoted-history table through `source_identity_hash`.

## 8. Events/integrations/background processing

Machine player snapshots reuse internal `kingdoms.player_snapshot_recorded`; machine game-Alliance observations reuse internal `kingdoms.alliance_intelligence_observation_recorded`; candidate promotion emits internal `kingdoms.ingestion_candidate_promoted`.

There is still no K4 scheduler, source poller, crawler, scraper, OCR worker, bot, cursor loop, retry worker, replay worker, or public machine contract.

## 9. Failure, idempotency and concurrency

Promotion fails closed/quarantines on context mismatch, Kingdom drift, source-version revocation, missing/unknown/ambiguous stable identity, missing/inactive/ambiguous tenant relationship, or shared business-record validation failure.

Transactional locking and uniqueness protect operational state; promoted-history source identity protects exact retry. Operators must not rewrite hashes/context, create tenant relationships, or invalidate accepted history to force recovery.

## 10. Operations and observability

Operators can correlate safe Alliance/Kingdom/subscription/batch/candidate/promoted-record IDs, adapter key/version, source record ID, hashes, state/reason, capture time, and internal audit/outbox IDs. Source secrets/raw responses/private text must not enter logs or evidence.

See [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed Dependency Review `31541291512`, CodeQL `31541291470`, and CI `31541291501`: Pint 515 files, PHPStan/Larastan 365/365 with zero errors, 417 tests / 9,628 assertions, frontend/build, PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, scan, and cleanup.

Focused P3 tests prove existing-active-tracking promotion, null machine actor/bounded provenance, exact retry, later append-history capture, manager provenance disclosure, cross-tenant no-auto-tracking, inactive-tracking quarantine, unknown-reference quarantine, source revocation, and migration round-trip.

See [Slice C validation](product/kingdoms-automated-ingestion-slice-c-validation.md).

## 12. Related documentation

- [KINGDOMS-004 implementation plan](product/kingdoms-automated-ingestion-implementation-plan.md)
- [Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice B validation](product/kingdoms-automated-ingestion-slice-b-validation.md)
- [Slice C validation](product/kingdoms-automated-ingestion-slice-c-validation.md)
- [Slice C security review](security/kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [Player snapshots](snapshots.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Automated ingestion operations](operations/kingdoms-automated-ingestion.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
