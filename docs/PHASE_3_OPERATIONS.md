# Phase 3 Operations — Events and Rallies

## Purpose

This runbook covers the operational behavior introduced by Phase 3: event scheduling, registration/waitlists, reminders, attendance, rally guidance, formations, assignments, exports, and calendar feeds.

## Runtime components

Phase 3 reuses the platform services established in earlier phases:

- PostgreSQL is the source of truth for event, registration, reminder, formation, and rally state.
- Redis provides scheduler locks, queue transport, cache infrastructure, and Horizon visibility.
- The transactional outbox remains the reliability boundary for asynchronous business events.
- The scheduler container runs recurring application commands.
- The worker container processes queued work and is monitored through the existing Horizon/health foundation.

No external Kingshot data collector or unapproved game interface is introduced.

## Scheduled commands

The application scheduler runs these Phase 3 commands every minute with `onOneServer()` and `withoutOverlapping()` protection:

- `php artisan events:sync-reminders` — materializes deterministic reminder deliveries for eligible upcoming registrations.
- `php artisan events:queue-reminders` — rechecks registration state and queues due deliveries into the transactional outbox.
- `php artisan outbox:publish --limit=100` — publishes eligible outbox messages using the existing lease/retry behavior.

The existing content scheduler and queue-pruning commands continue to run independently.

### Scheduler smoke check

1. Confirm the scheduler container is running the immutable release image.
2. Run `php artisan schedule:list` in the application image and verify the reminder and outbox commands are present.
3. Confirm no overlapping scheduler execution is accumulating.
4. Check `event_reminder_deliveries` for due `pending` rows and `outbox_messages` for unpublished reminder events if reminders appear delayed.

## Reminder delivery lifecycle

Reminder delivery intentionally uses two durable records rather than an ephemeral timer:

1. **Materialized** — `event_reminder_deliveries.status = pending`. A SHA-256 idempotency key uniquely identifies `(occurrence, rule, membership)`.
2. **Queued** — a still-valid registration is rechecked before a reminder is transitioned to `queued` and an `event.reminder.requested` outbox message is created.
3. **Published** — `PublishOutboxBatch` emits the outbox event. `MarkEventReminderPublished` marks the matching delivery `sent`.
4. **Visible** — recent sent reminders are read from the same delivery ledger and displayed to the active member on the alliance Events page.
5. **Cancelled** — if the registration is no longer eligible before queueing, the delivery becomes `cancelled` and no reminder outbox message is produced.

Retries do not create another delivery because the reminder row and outbox idempotency key are deterministic/unique.

## Retry and failure behavior

`PublishOutboxBatch` leases one unpublished message at a time, increments `attempts`, and on failure records `last_error` and moves `available_at` forward using bounded exponential backoff. A successfully published message receives `published_at` and is not selected again.

Operational triage fields:

- `event_reminder_deliveries.status`
- `event_reminder_deliveries.due_at`
- `event_reminder_deliveries.attempts`
- `event_reminder_deliveries.last_error`
- `event_reminder_deliveries.queued_at`
- `event_reminder_deliveries.sent_at`
- `outbox_messages.attempts`
- `outbox_messages.available_at`
- `outbox_messages.last_error`
- `outbox_messages.published_at`

A reminder that is `queued` with no corresponding successfully published outbox message should be treated as an asynchronous-delivery incident.

## Registration and waitlist concurrency

Capacity decisions are serialized by locking the event occurrence row inside the registration transaction. A unique `(occurrence_id, membership_id)` constraint prevents duplicate registration. When a registered member cancels, the oldest eligible waitlisted member is promoted inside the same transaction.

If capacity incidents are reported:

1. Inspect the occurrence capacity.
2. Count registration states for that occurrence.
3. Confirm there is only one registration row per membership.
4. Review audit/outbox entries around the affected transaction.
5. Do not manually bypass the constraints unless executing a documented recovery procedure.

## Time zones and recurrence

- Event authoring uses the alliance time zone.
- Recurrence arithmetic is performed in the alliance time zone so local wall-clock time survives daylight-saving transitions.
- Occurrences are persisted as UTC timestamps.
- Member pages display the event in both the user's configured time zone and the alliance time zone.
- CSV exports use explicit UTC columns; the iCalendar feed emits UTC `DTSTART`/`DTEND` values.

When diagnosing a reported time mismatch, record the alliance zone, user zone, stored UTC timestamp, and the expected local wall-clock time.

## Exports and iCalendar

Phase 3 exposes authenticated, active-alliance-scoped CSV and iCalendar endpoints. Responses are marked `private, no-store`. The iCalendar endpoint is a feed foundation for authenticated use/download; Phase 3 does not introduce a long-lived public subscription token.

Do not make these endpoints public or cache them in a shared cache without a new tenant-isolation/security review.

## Audit and attribution

Privileged Phase 3 actions use the existing `AuditRecorder` and transactional outbox. Event creation, templates, reminder rules, guidance, event formations, rally groups, assignments, attendance, and participation changes are attributable to the acting user/alliance.

For incident reconstruction, correlate:

- request correlation/log context from the existing platform middleware,
- `audit_events` actor/alliance/subject fields,
- the corresponding outbox aggregate/idempotency key,
- the Phase 3 domain row timestamps.

## Health, metrics, and alert implications

The existing `/up` and `/health/ready` checks remain the deployment readiness probes. Phase 3 adds no new external dependency, but it increases reliance on scheduler/worker/outbox freshness.

Recommended operational thresholds using existing database/queue/Pulse/Horizon visibility:

- **Reminder lag:** alert when due `pending` reminder deliveries remain overdue for more than 5 minutes.
- **Queued reminder lag:** alert when `queued` reminders remain unsent for more than 10 minutes.
- **Outbox retry pressure:** alert on repeated `event.reminder.requested` failures or rapidly increasing attempts.
- **Scheduler freshness:** alert if the reminder sync/queue commands have not executed successfully within the expected minute cadence.
- **Queue health:** alert on sustained failed jobs or Horizon worker unavailability.
- **Registration integrity:** investigate any occurrence where active registrations exceed configured capacity; the row-lock/constraint tests make this an invariant violation.

These signals should retain alliance/correlation identifiers where available without exposing sensitive member data in metrics labels.

## Backup and recovery

All Phase 3 state is stored in PostgreSQL and is therefore included in the existing database backup/restore procedure. The CI staging job demonstrates backup creation, manifest/hash validation, restore, service restart, and readiness recovery using the immutable release image.

After a restore, smoke-check:

1. `/health/ready` succeeds.
2. An alliance Events page loads only its own occurrences.
3. Existing registrations/attendance/rally assignments remain present.
4. Reminder deliveries and outbox rows retain their statuses/idempotency keys.
5. A pending reminder can progress through queue/publish once without duplication.

## Migration rollback

`tests/Feature/Events/EventMigrationRollbackTest.php` exercises the Phase 3 migration's `down()` and `up()` methods against the test database and verifies that all Phase 3 tables are removed and restored cleanly.

Production rollback remains release-controlled: application compatibility, data-loss implications, and the database backup taken before rollback must be reviewed before destructive migration rollback. The migration test demonstrates schema reversibility; it is not authorization for an operator to discard production Phase 3 data.

## Incident checklist

For a Phase 3 incident, capture:

- release SHA/image ID,
- alliance ID and occurrence ID,
- UTC and local event times,
- affected registration/reminder/assignment IDs,
- relevant audit/outbox rows,
- scheduler/worker health,
- queue/outbox retry state,
- whether the issue reproduces after a read-only refresh.

Prefer repairing state through the normal application actions. Direct database edits require an incident record, a verified backup, and explicit approval because they can bypass waitlist, audit, and idempotency invariants.
