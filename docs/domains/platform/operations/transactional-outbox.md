# Platform transactional outbox operations

[← Platform operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Platform  
**Capability:** Transactional outbox publication  
**Code owner:** `app/Domain/Platform`

## 1. Scope, prerequisites and safety boundary

Use this runbook when unpublished outbox backlog grows, `outbox:publish` repeatedly fails, or downstream consumers are not progressing after committed producer transactions. Identify the oldest affected message, release SHA, scheduler state and failing consumer/dependency before acting.

Never set `published_at` manually, delete failing rows to clear a gate, or replay the originating business mutation merely to recreate asynchronous intent.

## 2. Runtime and persistent state

Outbox rows are written in the same PostgreSQL transaction as accepted producer state. `outbox:publish --limit=100` runs every minute. PostgreSQL claiming uses lock/skip-locked semantics; attempts and `available_at` bound concurrent/retry behavior. Failed publication keeps the message unpublished with bounded `last_error` and delayed reavailability.

## 3. Healthy operating flow

1. Authorized producer transaction commits business state and one safe outbox row atomically.
2. Scheduler invokes the bounded publisher.
3. Publisher claims eligible oldest rows and emits `OutboxPublished` in-process.
4. Every consumer completes idempotently.
5. Publisher marks the row published only after successful consumer completion.

At-least-once delivery is expected; consumer idempotency is mandatory.

## 4. Signals and diagnostics

Inspect scheduler process/list, count/age of unpublished rows, message event/tenant/logical identity, attempts, `available_at`, `last_error`, failed-job/Horizon state for downstream queue work, consumer-specific durable state and launch-check overdue-outbox result.

Correlate producer request/trace/audit IDs where available. Do not log full sensitive payloads during diagnosis.

## 5. Failure modes and triage

- No outbox row after a supposedly accepted transaction: investigate producer atomicity/application defect.
- Backlog with scheduler absent: restore scheduler.
- Repeated same consumer exception: fix that consumer/dependency first.
- Message repeatedly becomes available but stays unpublished: inspect attempts/error and idempotency state.
- Downstream webhook/reminder state not progressing while row is published: switch to the owning consumer runbook; do not republish blindly.

## 6. Recovery, replay and reconciliation

After fixing the cause, run normal publisher catch-up:

`php artisan outbox:publish --limit=100`

Repeat while backlog is known, oldest age decreases and database/consumer capacity remains healthy. Publication retry is the replay mechanism; it must not replay the source business command.

Reconcile published/unpublished state against the owning consumer's durable logical identity before any exceptional replay decision.

## 7. Capacity and dependency degradation

Default batch is 100. A growing backlog can amplify downstream load when recovery begins. Increase limits only with PostgreSQL and consumer capacity evidence. Redis/Horizon may be healthy while an in-process consumer fails, and vice versa.

Launch checks default to zero overdue outbox rows older than the configured grace period; that threshold is a readiness gate, not a full production SLO.

## 8. Backup, migration and rollback

Outbox state is PostgreSQL-backed and restored with producer/consumer state. After restore, historical external side effects may already have occurred even if the restored database shows an older message state. Reconcile incident timing and consumer idempotency before replaying.

Application rollback must preserve compatibility with current outbox rows/event payloads. Database migration reversal that would discard outbox evidence requires explicit data-loss review.

## 9. Stop conditions and prohibited operator actions

Stop and escalate if repeated publication causes non-idempotent consumer side effects, payload/tenant identity appears unsafe, backlog recovery threatens database stability, or the proposed fix requires setting `published_at`, deleting rows/audit evidence, editing payloads in place, or replaying the originating mutation.

## 10. Validation and evidence to retain

Verify oldest unpublished age/backlog decreases, affected messages publish or remain intentionally failed with understood cause, consumer durable state advances exactly once logically, and launch-check/outbox signals return to accepted bounds.

Retain release SHA, message IDs/event types, tenant/logical identifiers, attempts/errors, backlog age/count before/after, command parameters/timestamps, consumer incident IDs and validation result.
