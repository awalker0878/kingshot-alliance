# Automated game-data ingestion

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-004` Accepted through `K4-P6`  
**Owning domain:** Kingdoms

## 1. Purpose

Automated game-data ingestion provides a tenant-scoped path for approved machine-readable Kingshot facts without bypassing accepted Kingdoms identity, tenancy, append-history, privacy or human-decision boundaries.

K4-P1 established the generic subscription/batch/candidate control plane. K4-P2 added existing-roster player-snapshot promotion. K4-P3 added existing-active-tracking game-Alliance observation promotion. K4-P4 added generic scheduled acquisition, cursor/retry/concurrency mechanics and controlled replay. K4-P5 added source-revocation reconciliation, bounded operational retention, aggregate health/alert signals and capacity evidence. K4-P6 completed whole-increment acceptance across those seams.

## 2. Scope and non-scope

Current scope includes code/config allowlisted adapters, Alliance/current-Kingdom subscriptions, bounded acquisition pages, batches, normalized candidates, quarantine/rejection, deterministic identities, delegated player/game-Alliance promotion, scheduler claiming, dedicated queue execution, opaque cursor advancement, bounded retry/circuit state, manager replay, source-revocation reconciliation, retention/pruning and payload-free operational health.

Still out of scope: a concrete production source, production endpoint/credential, scraping/OCR/browser/game-client automation, arbitrary manager network configuration, automatic roster/tracking creation/reactivation, machine correction/invalidation, transfer/diplomacy/contact mutation, scoring/ranking/recommendations, cross-Alliance sharing, and public Kingdoms API/webhook exposure.

## 3. Model and state

`KingdomIngestionSubscription`, `KingdomIngestionBatch`, and `KingdomIngestionCandidate` are Alliance-owned operational state with captured Kingdom and adapter/version context.

Subscriptions additionally hold opaque source cursor, next-run/claim/success/failure/circuit state and bounded failure codes. Batches capture source-window identity plus the next cursor returned for that window. Candidates preserve deterministic source/identity/payload hashes and safe promoted-record identity.

Promoted `PlayerSnapshot` and `KingdomAllianceObservation` rows store bounded immutable machine provenance without FK dependence on operational K4 rows. Operational retention may therefore prune K4 scaffolding without deleting or rewriting canonical promoted history.

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
13. Adapter removal/version drift disables future acquisition rather than substituting or silently continuing.
14. K4 operational retention never deletes promoted K1/K3 canonical history or rewrites its machine provenance.
15. Production adapter configuration remains empty until separate source approval.
16. All `kingdoms.*` ingestion events remain internal/public-webhook ineligible.

## 5. Workflows

Managers may manage approved subscriptions and reject/replay quarantined candidates under the K4 control surface; human mutations require recent password confirmation.

The shared scheduler invokes `kingdoms:queue-ingestion` every minute. Due active subscriptions are claimed transactionally before a unique, overlap-protected job is dispatched to the dedicated `kingdoms-ingestion` queue.

An acquisition-capable adapter receives the subscription's opaque cursor and a maximum page size of 250. The returned source window is passed to `StartKingdomIngestionBatch`; each record is normalized/staged through `StageKingdomIngestionCandidate` and pending candidates delegate to the accepted P2/P3 promotion actions. The batch becomes Completed or Partial, then the cursor advances under locks.

Every five minutes `kingdoms:reconcile-ingestion-sources` rechecks active/paused subscriptions against the currently approved adapter registry and disables a subscription with bounded `source_unapproved` state when approval/version disappears.

Daily `kingdoms:enforce-ingestion-retention` redacts/prunes age-qualified operational state while preserving promoted canonical history. Exact manager replay remains limited to quarantined candidates and re-runs the existing promotion path.

## 6. Authorization, tenancy and privacy

Human management/replay remains `kingdoms.manage` plus recent password confirmation. Machine work derives authority only from already-owned subscription/candidate context after re-resolving current Alliance/Kingdom and adapter version; neutral/source/queue identity never grants tenant access.

The manager UI and operator health command expose bounded adapter/subscription/batch/candidate scheduling, cursor, counts, timing and reason information. They do not serialize normalized candidate payloads, source secrets, headers/cookies or arbitrary raw responses.

Retention shortens normalized operational payload lifetime without changing canonical-history visibility contracts.

## 7. Persistence and query semantics

Repository-controlled retention defaults are:

- terminal promoted/rejected candidate payload redaction after 30 days;
- terminal promoted/rejected candidate-row purge after 90 days;
- quarantined candidate-row purge after 180 days;
- terminal batch purge after 90 days only when no candidates remain; and
- disabled-subscription scheduling/failure compaction after 30 days while preserving the subscription itself.

Source-window uniqueness and deterministic candidate identities remain authoritative. Promoted-history source identity remains independently unique within the owning Alliance. Operational cursor/failure/retention state does not replace canonical business provenance.

Operational-health query semantics are aggregate and bounded; the accepted capacity fixture requires no more than eight SELECT queries for 250 subscriptions, 40 failed batches and 110 candidates.

## 8. Events, commands and background processing

`kingdoms:queue-ingestion --limit=100` runs every minute with `onOneServer()` and `withoutOverlapping(10)`. Due work dispatches `RunKingdomIngestionSubscriptionJob` to dedicated Horizon queue `kingdoms-ingestion`.

`kingdoms:reconcile-ingestion-sources --limit=1000` runs every five minutes with single-server/overlap protection. `kingdoms:enforce-ingestion-retention` runs daily at 04:15 with single-server/overlap protection. `kingdoms:ingestion-health --json` is an operator/monitoring command and returns non-zero when bounded aggregate signals require attention.

Jobs are unique per subscription, overlap-protected, timeout at 120 seconds, try at most five times and use bounded 60/300/900/3,600-second queue backoff. Production has no concrete acquisition adapter, so the generic scheduler has no real source/network dependency in default production configuration.

## 9. Failure, idempotency and concurrency

Due claims, context checks, source reconciliation and cursor advancement use database row locks where concurrent mutation matters. Queue uniqueness/overlap controls reduce duplicate execution, but source-window/candidate/promoted-record idempotency remains authoritative.

Adapter removal/version drift, Kingdom drift, circuit-open state, source-window conflict, unsupported payload, unknown/inactive target and business validation fail closed. Failure diagnostics store bounded codes such as `acquisition_failed`, `source_contract_invalid`, `processing_validation_failed`, `source_unapproved`, context codes and `retry_exhausted`, never raw exception text.

## 10. Operations and observability

Operational health aggregates active subscriptions, source-revoked subscriptions, overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches into a payload-free snapshot plus `attentionRequired`.

Repository defaults are five minutes overdue, fifteen minutes stale-pending, twenty-five quarantined candidates and a sixty-minute recent-failure window. The accepted capacity gate remains operations evidence rather than a real-source throughput or availability SLO.

See [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

Whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`: Pint 529 files, PHPStan/Larastan 374/374 with zero errors, 429 tests / 9,799 assertions, frontend/build, clean PostgreSQL migrations, immutable image, ephemeral staging, backup/restore and image scan.

The dedicated `KingdomIngestionAcceptanceTest` proves empty-default source approval, both delegated promotion paths, exact source-window retry idempotency, fail-closed source revocation, operational attention, staged retention redaction/pruning and canonical promoted-history/provenance survival after K4 operational deletion. Focused P1–P5 tests remain additive.

See the [KINGDOMS-004 exit report](product/kingdoms-automated-ingestion-exit-report.md).

## 12. Related documentation

Repository acceptance does not approve provider authorization/terms, endpoint/network/DNS/redirect/private-address behavior, TLS/egress, source credentials, rate/timeout limits, schema/version policy, cursor semantics or production cutover. Those remain separate source-enablement prerequisites while the production adapter allowlist is empty.

- [KINGDOMS-004 implementation plan](product/kingdoms-automated-ingestion-implementation-plan.md)
- [KINGDOMS-004 exit report](product/kingdoms-automated-ingestion-exit-report.md)
- [Slice D validation](product/kingdoms-automated-ingestion-slice-d-validation.md)
- [Slice E validation](product/kingdoms-automated-ingestion-slice-e-validation.md)
- [Slice E security/privacy review](security/kingdoms-automated-ingestion-operations-security-review.md)
- [Player snapshots](snapshots.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Automated ingestion operations](operations/kingdoms-automated-ingestion.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
