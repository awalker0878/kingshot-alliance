# Background Processing

[← Operations documentation](README.md)

## Purpose

This guide is the current operator contract for scheduled work, transactional-outbox publication, and Laravel queue processing. It replaces phase-by-phase reconstruction of the background-processing model.

Runtime sources of truth are `routes/console.php`, `config/horizon.php`, and the domain actions/jobs invoked by those commands. If this guide disagrees with runtime code, treat the mismatch as a documentation defect.

## Runtime processes

Hosted deployments require two distinct long-running background processes:

- **Scheduler** — runs `php artisan schedule:work` and invokes the recurring commands defined in `routes/console.php`.
- **Horizon worker** — runs `php artisan horizon` and consumes Redis queue work according to `config/horizon.php`.

Do not treat these processes as interchangeable. Most current recurring workflows execute synchronously inside the scheduler command and persist durable database/outbox state. Webhook HTTP delivery is the primary current workload that dispatches Laravel queue jobs.

The staging topology runs application, web, worker, and scheduler roles from the same immutable image digest.

## Current schedule

| Command | Cadence | Owning domain | Queue/process ownership | Concurrency / duplicate protection | Primary failure signal | Safe recovery |
| --- | --- | --- | --- | --- | --- | --- |
| `content:publish-scheduled --limit=100` | Every minute | Content | Scheduler | `onOneServer`, `withoutOverlapping(10)`; item is rechecked under row lock while still scheduled/due | Due content remains `scheduled`; scheduler errors/logs | Restore scheduler/database health and rerun the bounded command. Already-published items fail the due-state recheck. |
| `events:sync-reminders --limit=250` | Every minute | Notifications / Events | Scheduler | `onOneServer`, `withoutOverlapping(10)`; deterministic reminder-delivery key | Upcoming eligible registrations lack expected delivery rows | Rerun after scheduler/database recovery; deterministic materialization prevents routine duplicates. |
| `events:queue-reminders --limit=100` | Every minute | Notifications | Scheduler → transactional outbox | `onOneServer`, `withoutOverlapping(10)`; due rows are lock-protected and outbox keys are deterministic | Due reminders remain `pending`, or expected outbox row is absent | Rerun after fixing scheduler/database health. Do not manually duplicate reminder rows. |
| `contributions:queue-reports --limit=50` | Every minute | Notifications / Contributions | Scheduler → transactional outbox | `onOneServer`, `withoutOverlapping(10)`; deterministic schedule/due-time/report-version key | Report schedule stays overdue; expected report run/outbox request absent | Rerun the command. Existing logical runs are reused by idempotency key. |
| `outbox:publish --limit=100` | Every minute | Platform | Scheduler; emits in-process `OutboxPublished` events | `onOneServer`, `withoutOverlapping(10)`; row claiming uses locks; failed publication is delayed and retried | Unpublished outbox backlog, `last_error`, launch-check backlog failure | Fix the failing consumer/dependency and rerun. Do not edit `published_at` manually. |
| `integrations:queue-webhooks --limit=100` | Every minute | Integrations | Scheduler → Redis `integrations` queue | `onOneServer`, `withoutOverlapping(10)`; delivery idempotency + unique queued job | Pending deliveries remain due; Horizon `integrations` queue backlog/failures | Restore Horizon/Redis/egress, then rerun the command. Existing delivery records are reused. |
| `platform:process-account-deletions --limit=100` | Hourly | Platform | Scheduler | `onOneServer`, `withoutOverlapping(30)`; request state controls reprocessing | Eligible requests remain `pending`/`blocked`; command exception | Resolve legal-hold/admin/ownership blockers or infrastructure failure, then rerun. Never bypass blockers by direct database edits. |
| `platform:capture-usage --limit=2000` | Hourly | Platform | Scheduler | `onOneServer`, `withoutOverlapping(30)` | Missing expected usage snapshots; command/log failure | Restore database health and rerun if a fresh snapshot is required. Re-execution creates a new point-in-time snapshot rather than replacing history. |
| `recruitment:purge-expired --limit=250` | Daily 03:15 | Recruitment | Scheduler | `onOneServer`, `withoutOverlapping(30)`; candidate is rechecked and locked before anonymization | Past-due unsuccessful candidates remain unanonymized | Rerun after resolving database/scheduler failure. Already-anonymized candidates are excluded. |
| `platform:enforce-retention` | Daily 03:45 | Platform | Scheduler | `onOneServer`, `withoutOverlapping(60)`; operations target records still beyond retention windows | Old webhook payloads/revoked credentials/usage/export metadata remain beyond policy | Rerun after fixing database health. The redaction/deletion operations are safe to repeat against remaining eligible rows. |
| `queue:prune-batches --hours=48` | Daily | Framework / Platform | Scheduler | Laravel pruning semantics | Stale batch metadata grows unexpectedly | Restore scheduler and rerun the framework command. |
| `queue:prune-failed --hours=168` | Daily | Framework / Platform | Scheduler | Laravel pruning semantics | Failed-job history older than seven days remains | Restore scheduler and rerun only after retaining any incident evidence that is still needed. |

All scheduled application commands have bounded limits in code. Use the documented defaults during normal operation. Higher bounded values may be used to drain a known backlog only when database/queue capacity has been assessed.

## Transactional outbox

The outbox is the durable handoff between a committed business transaction and asynchronous consumers.

`outbox:publish` claims the oldest eligible unpublished message. On PostgreSQL the claim uses `FOR UPDATE SKIP LOCKED`. Claiming increments `attempts` and temporarily moves `available_at` forward so another publisher does not immediately reclaim the same message.

If all `OutboxPublished` consumers complete, the publisher sets `published_at`. If a consumer throws, the message remains unpublished, stores a bounded `last_error`, and becomes available again after exponential delay capped at one hour.

Current in-process consumers include:

- marking queued event reminders as sent;
- advancing recruitment conversion state where applicable; and
- creating matching webhook-delivery records for tenant events.

Because publication is at-least-once, consumers and downstream delivery creation must remain idempotent. An operator must not manufacture a successful publication by setting `published_at` directly.

## Horizon queues

Hosted Horizon configuration separates queue capacity by workload:

| Supervisor | Queues | Production default | Staging default |
| --- | --- | ---: | ---: |
| `core` | `default`, `notifications` | 8 max processes | 3 max processes |
| `integrations` | `integrations` | 4 max processes | 2 max processes |
| `maintenance` | `maintenance` | 2 max processes | 1 fixed process |

Current application code explicitly dispatches webhook delivery jobs to `integrations`. The other queue names are reserved capacity boundaries for present/framework work and future domain jobs; do not infer that every scheduler workflow listed above is processed by a queue worker.

Local development uses one supervisor across `default`, `notifications`, `integrations`, and `maintenance`.

See [Configuration reference](configuration-reference.md) for the supported Horizon process-count variables.

## Webhook retry behavior

Webhook deliveries are durable rows. Matching outbox events create one delivery per subscription/source message. Delivery jobs run on the `integrations` queue.

A webhook job has five attempts with queue-level backoff of 60, 300, 1,800, and 7,200 seconds. The delivery action also records the next `available_at` after a non-success response or transport error. `integrations:queue-webhooks` is the recovery sweep that dispatches due `pending` delivery rows after worker restarts or missed queue submission.

A delivery that exhausts the job retry budget becomes `failed`. Do not reset failed deliveries casually: retain the failure evidence, identify whether the endpoint/egress problem is corrected, and use an approved replay process if one is introduced. The current application provides automatic recovery for due `pending` deliveries, not a generic operator replay command for permanently failed deliveries.

## Scheduler health checks

If recurring work appears stalled, check in this order:

1. Confirm the scheduler process is running (`php artisan schedule:work` in the current container topology).
2. Confirm PostgreSQL and Redis are healthy and `/health/ready` returns `200`.
3. Run `php artisan schedule:list` to verify the application sees the expected schedule.
4. Check recent application/scheduler logs using request/trace IDs where available and command error output for scheduler jobs.
5. Inspect the durable state that should have advanced: content status, reminder deliveries, report runs, outbox rows, webhook deliveries, deletion requests, or retention targets.
6. Run the single bounded command needed to catch up rather than replaying the original user action.
7. Verify the durable state advanced and no unexpected duplicate business record was created.

A restart alone is not proof of recovery. Validate the persisted workflow state after the restart.

## Queue health checks

If queued work appears stalled:

1. Confirm Redis is reachable and the Horizon process is running.
2. Inspect Horizon queue depth, failed jobs, and worker state.
3. Determine which queue owns the work. Current webhook HTTP delivery belongs to `integrations`.
4. Inspect the durable source row before retrying. For webhooks, check delivery `status`, `attempts`, `available_at`, response code, and `last_error`.
5. Fix the dependency first (Redis, egress, endpoint, DNS, credentials, or application defect).
6. Use the owning recovery command when one exists; for pending webhooks use `php artisan integrations:queue-webhooks --limit=100`.
7. Confirm queue depth falls and durable delivery state progresses.

Do not delete failed-job records before incident evidence has been captured.

## Launch and alert thresholds

`php artisan app:launch-check` uses configurable operational thresholds for:

- unpublished outbox messages older than the configured grace period;
- total failed queue jobs; and
- recent permanently failed webhook deliveries.

These are launch/readiness gates, not a complete production alerting system. Production alert destinations, escalation ownership, queue-depth thresholds, latency/error thresholds, database/storage capacity thresholds, and on-call coverage remain deployment-specific external controls.

See [Observability](observability.md) and the [production launch runbook](production-launch-runbook.md).

## Stop conditions

Stop manual recovery and escalate when:

- a command would require bypassing a legal hold, ownership invariant, tenant boundary, or authorization rule;
- repeated outbox publication continues failing with the same application exception;
- webhook failures indicate an unresolved egress/SSRF control problem;
- database locking or load makes a larger catch-up batch unsafe;
- durable state is inconsistent with the expected idempotency key/state machine; or
- recovery would require deleting or rewriting audit/outbox evidence.

Use the [incident response runbook](runbooks/incident-response.md) for incident coordination and evidence handling.