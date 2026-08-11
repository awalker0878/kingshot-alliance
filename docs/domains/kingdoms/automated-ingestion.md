# Automated game-data ingestion

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-004` through `K4-P4` / Slice D validated when the containing evidence head is protected-green; `K4-P5` is next  
**Owning domain:** Kingdoms

## 1. Purpose

Automated game-data ingestion provides a tenant-scoped path for approved machine-readable Kingshot facts without bypassing accepted Kingdoms identity, tenancy, append-history, privacy or human-decision boundaries.

K4-P1 established the generic subscription/batch/candidate control plane. K4-P2 added existing-roster player-snapshot promotion. K4-P3 added existing-active-tracking game-Alliance observation promotion. K4-P4 adds generic scheduled acquisition, cursor/retry/concurrency mechanics and controlled replay around those accepted contracts.

## 2. Scope and non-scope

Current scope includes code/config allowlisted adapters, Alliance/current-Kingdom subscriptions, bounded acquisition pages, batches, normalized candidates, quarantine/rejection, deterministic identities, delegated player/game-Alliance promotion, scheduler claiming, dedicated queue execution, opaque cursor advancement, bounded retry/circuit state, manager health presentation and password-confirmed replay.

Still out of scope: a concrete production source, production endpoint/credential, scraping/OCR/browser/game-client automation, arbitrary manager network configuration, automatic roster/tracking creation/reactivation, machine correction/invalidation, transfer/diplomacy/contact mutation, scoring/ranking/recommendations, cross-Alliance sharing, and public Kingdoms API/webhook exposure.

## 3. Model and state

`KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` are Alliance-owned operational state with captured Kingdom and adapter/version context.

Subscriptions additionally hold opaque source cursor, next-run/claim/success/failure/circuit state and bounded failure codes. Batches capture source-window identity plus the next cursor returned for that window. Candidates preserve deterministic source/identity/payload hashes and safe promoted-record identity.

Promoted `PlayerSnapshot` and `KingdomAllianceObservation` rows store bounded immutable machine provenance without FK dependence on operational K4 rows.

## 4. Invariants

1. Alliance is the tenant/authorization boundary.
2. Captured Kingdom context is never silently retargeted after drift.
3. Stable game IDs are the only automatic identity keys; names/tags/handles never match targets.
4. Player promotion requires one existing owning-Alliance roster entry; game-Alliance promotion requires one existing active owning-Alliance tracking relation.
5. Unknown, ambiguous, inactive, stale-context, revoked-source or invalid candidates fail closed/quarantine before business mutation.
6. Promotion never creates/reactivates roster/tracking/membership/transfer/diplomacy/contact state.
7. Machine game-Alliance observations are append-only; correction/invalidation remains human-only.
8. Machine promotion delegates to accepted K1/K3 record actions and uses no fabricated User actor.
9. Scheduler/queue identity is never authorization; each run re-resolves tenant/current-Kingdom/source context.
10. Cursor advances only after a completed/partial source window and never on failed/blocked work.
11. Exact source-window/candidate/promoted-record retry is idempotent; later distinct capture remains append-oriented history.
12. Failure state is bounded; raw source exception/response/secret material is not persisted as scheduler diagnostics.
13. Production adapter configuration remains empty until separate source approval.
14. All `kingdoms.*` ingestion events remain internal/public-webhook ineligible.

## 5. Workflows

Managers may manage approved subscriptions and reject/replay quarantined candidates under the K4 control surface; human mutations require recent password confirmation.

The shared scheduler invokes `kingdoms:queue-ingestion` every minute. Due active subscriptions are claimed transactionally before a unique, overlap-protected job is dispatched to the dedicated `kingdoms-ingestion` queue.

An acquisition-capable adapter receives the subscription's opaque cursor and a maximum page size of 250. The returned source window is passed to `StartKingdomIngestionBatch`; each record is normalized/staged through `StageKingdomIngestionCandidate` and Pending candidates delegate to the accepted P2/P3 promotion actions. The batch becomes Completed or Partial, then the cursor advances under locks.

Exact replay of a completed/partial source window accepts only the same stored next cursor. Manager replay resets only a quarantined candidate to Pending, records bounded human audit/outbox evidence, and re-runs the existing promotion path.

## 6. Authorization, tenancy and privacy

Human management/replay remains `kingdoms.manage` plus recent password confirmation. Machine work derives authority only from already-owned subscription/candidate context after re-resolving current Alliance/Kingdom and adapter version; neutral/source/queue identity never grants tenant access.

The manager UI may expose bounded adapter/subscription/batch/candidate scheduling, cursor, counts, timing and reason codes. It does not serialize normalized candidate payloads, source secrets, headers/cookies or arbitrary raw responses.

## 7. Persistence and query semantics

P4 adds scheduler fields to subscriptions (`next_run_at`, `last_claimed_at`, bounded consecutive failure/circuit/failure-code state) and `next_source_cursor` to batches.

Source-window uniqueness and deterministic candidate identities remain authoritative. Promoted-history source identity remains independently unique within the owning Alliance. Operational cursor/failure state does not replace canonical business provenance.

## 8. Events/integrations/background processing

`kingdoms:queue-ingestion --limit=100` runs every minute with `onOneServer()` and `withoutOverlapping(10)`. Due work dispatches `RunKingdomIngestionSubscriptionJob` to dedicated Horizon queue `kingdoms-ingestion`.

Jobs are unique per subscription, overlap-protected, timeout at 120 seconds, try at most five times and use bounded 60/300/900/3,600-second queue backoff. Repeated acquisition failures use bounded subscription backoff/circuit state; exhausted jobs finalize a still-pending batch as `failed/retry_exhausted`.

Production has no concrete acquisition adapter, so the generic scheduler has no real source/network dependency in the default production configuration.

## 9. Failure, idempotency and concurrency

Due claims, context checks and cursor advancement use database row locks. `next_run_at` advances before dispatch so duplicate scheduler ticks cannot repeatedly dispatch the same due subscription; queue uniqueness/overlap provides an additional, non-authoritative guard.

Adapter removal/version drift, Kingdom drift, circuit-open state, source-window conflict, unsupported payload, unknown/inactive target and business validation fail closed. Failure diagnostics store bounded codes such as `acquisition_failed`, `source_contract_invalid`, `processing_validation_failed`, `source_unapproved`, context codes and `retry_exhausted`, never raw exception text.

## 10. Operations and observability

Operators can correlate safe Alliance/Kingdom/subscription/batch/candidate/promoted-record IDs, adapter key/version, source-window/record IDs, opaque cursor, hashes, next-run/claim/success/failure/circuit timing, state/reason codes and internal audit/outbox IDs.

The dedicated queue keeps external acquisition pressure isolated from core/default/integration queues. See [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

Final P4 runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed Dependency Review `31545866277`, CodeQL `31545866288`, and CI `31545866249`: Pint 523 files, PHPStan/Larastan 371/371 with zero errors, 423 tests / 9,697 assertions, frontend/build, clean PostgreSQL migrations, immutable image, ephemeral staging, backup/restore and image scan.

Focused P4 tests prove one-time due claims, mixed player/game-Alliance processing, exact completed-window replay idempotency, bounded failure/circuit state, retry-exhaustion finalization, password-confirmed manager replay and migration round-trip.

See [Slice D validation](product/kingdoms-automated-ingestion-slice-d-validation.md).

## 12. Related documentation

- [KINGDOMS-004 implementation plan](product/kingdoms-automated-ingestion-implementation-plan.md)
- [Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice B validation](product/kingdoms-automated-ingestion-slice-b-validation.md)
- [Slice C validation](product/kingdoms-automated-ingestion-slice-c-validation.md)
- [Slice D validation](product/kingdoms-automated-ingestion-slice-d-validation.md)
- [Slice D security review](security/kingdoms-automated-ingestion-scheduler-security-review.md)
- [Player snapshots](snapshots.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Automated ingestion operations](operations/kingdoms-automated-ingestion.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
