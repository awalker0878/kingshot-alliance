# Notifications

[← Domain documentation](README.md)

## Purpose

Notifications owns durable notification-delivery coordination that should not live inside the feature domain that originates the message. The current implementation covers two workflows:

- in-app event reminder materialization and delivery state; and
- scheduled contribution-report request coordination through the transactional outbox.

Events remains authoritative for event schedules, occurrences, registrations, and attendance. Contributions remains authoritative for report schedules, report versions, and report-run records. Notifications coordinates when those domain facts should produce a delivery/request and makes repeated scheduler execution safe.

The current implementation does **not** define a generic email, SMS, push, or third-party messaging provider. A `sent` event reminder means its durable reminder request was successfully published through the application outbox and is available to the in-app reminder experience; it does not claim delivery through an external channel.

## Event reminder ownership

Notifications owns `EventReminderRule` and `EventReminderDelivery` state. Events owns the occurrence and registration facts used to decide who is eligible.

### Materialization

`events:sync-reminders` scans future scheduled event occurrences. For each enabled reminder rule and each eligible registration in `registered` or `waitlisted` state, Notifications creates at most one delivery for the combination of:

- alliance;
- occurrence;
- reminder rule; and
- membership.

The idempotency key is deterministic from those identifiers. Re-running materialization therefore does not create duplicate reminder deliveries for the same rule/member/occurrence.

`due_at` is calculated from the occurrence start minus the rule's configured `minutes_before_start`.

### Delivery state

Event reminder delivery states are:

| State | Meaning |
| --- | --- |
| `pending` | Materialized and waiting for `due_at`. |
| `queued` | A durable `event.reminder.requested` outbox message exists for the delivery. |
| `sent` | The corresponding outbox message was published and the in-app reminder is considered delivered. |
| `cancelled` | The member is no longer eligible when the reminder becomes due. |

### Queueing due reminders

`events:queue-reminders` claims due `pending` deliveries in due-time order. PostgreSQL uses `FOR UPDATE SKIP LOCKED` so concurrent scheduler workers do not claim the same row.

Before queueing, Notifications rechecks the Events-domain registration. Only members who are still `registered` or `waitlisted` remain eligible. If the registration is no longer active, the delivery becomes `cancelled` and no reminder request is published.

For an eligible delivery, Notifications creates an outbox message with event type `event.reminder.requested` and a deterministic key derived from the delivery idempotency key, then marks the delivery `queued`.

When the platform outbox publishes that event, `MarkEventReminderPublished` moves the matching queued delivery to `sent` and records `sent_at`.

## Scheduled contribution-report requests

Contribution report schedules and report runs belong to Contributions. Notifications owns the due-time coordination that converts a schedule occurrence into a durable report request.

`contributions:queue-reports` selects enabled schedules whose `next_due_at` has arrived. PostgreSQL uses `FOR UPDATE SKIP LOCKED` so the same due occurrence is not concurrently advanced by multiple workers.

For each due occurrence, the action derives a deterministic SHA-256 idempotency key from:

- schedule ID;
- due timestamp; and
- report version.

It then:

1. creates or reuses the corresponding `ContributionReportRun` in `queued` state;
2. creates or reuses a `contribution.report.requested` outbox message;
3. records the recipient membership, report version, and `as_of` timestamp; and
4. advances `next_due_at` in the schedule's configured time zone.

Supported current cadences are `daily`, `weekly`, and `monthly`. Monthly advancement uses no-overflow calendar arithmetic.

This boundary means repeated scheduler execution can safely observe the same due occurrence without producing multiple logical report runs or outbox requests.

## Scheduler contract

The current scheduler runs these notification-owned coordination commands every minute:

| Command | Purpose | Scheduler protection |
| --- | --- | --- |
| `events:sync-reminders --limit=250` | Materialize deliveries for future scheduled occurrences. | `onOneServer()`, `withoutOverlapping(10)` |
| `events:queue-reminders --limit=100` | Queue due event reminders through the outbox. | `onOneServer()`, `withoutOverlapping(10)` |
| `contributions:queue-reports --limit=50` | Queue due scheduled contribution-report requests. | `onOneServer()`, `withoutOverlapping(10)` |

`outbox:publish --limit=100` also runs every minute and is required for queued notification requests to progress through the shared transactional-outbox boundary.

Command-level limits are bounded in code even when a larger value is supplied. Operators should use the documented defaults unless deliberately draining a backlog.

## Idempotency and retry semantics

Notification coordination assumes at-least-once scheduler and outbox execution.

The important invariants are:

- reminder materialization is deterministic per alliance/occurrence/rule/membership;
- reminder outbox creation is deterministic per delivery;
- contribution report-run creation is deterministic per schedule/due-time/report-version;
- contribution report outbox creation is deterministic per report run occurrence;
- scheduler work is lock-protected and safe to rerun; and
- outbox consumers must remain idempotent because publication is at-least-once rather than exactly-once.

Do not manually duplicate a delivery or report run to recover from an asynchronous failure. Repair the scheduler/outbox condition and rerun the bounded command so the existing idempotency records can resume safely.

## Failure and recovery behavior

### Reminder is still `pending`

Verify that `due_at` has passed, the member remains registered/waitlisted, and `events:queue-reminders` is running. If the member is no longer eligible, cancellation is expected.

### Reminder is `queued` but not `sent`

Inspect the matching `event.reminder.requested` outbox message. A queued reminder becomes sent only after the platform publisher emits the matching `OutboxPublished` event.

### Scheduled report did not queue

Verify that the schedule is enabled, `next_due_at` is due, the cadence/time zone is valid, and `contributions:queue-reports` is running. Check whether an existing report run already has the deterministic idempotency key for that schedule occurrence before attempting recovery.

### Scheduler interruption

The workflows are designed for catch-up. After scheduler service is restored, rerun the appropriate bounded command. Due records remain persisted; the idempotency constraints prevent routine duplicate materialization.

### Outbox backlog

A notification-domain business request may already be durably recorded even while publication is delayed. Repair the outbox publisher rather than replaying the originating event, registration, or contribution action solely to force delivery.

## Tenant and privacy boundary

Every reminder delivery and scheduled report request carries the owning alliance identifier. Event reminders also carry the membership and occurrence identity needed to render only the active member's reminder data.

Notifications must not infer tenant identity from global process state. Cross-domain source rows are queried with their alliance context, and outbox messages preserve `alliance_id` for downstream processing.

Reminder/report payloads should contain the minimum identifiers needed for downstream work. Sensitive candidate data, secrets, or unrelated member data do not belong in notification payloads or routine logs.

## Relationship to other domains

- **Events** defines occurrences, registrations, attendance, and coordinator-facing reminder configuration. Notifications owns durable reminder rules/deliveries and due-time coordination.
- **Contributions** defines report schedules, report versions, and report-run semantics. Notifications determines when due schedules produce report-request outbox messages.
- **Platform** owns the transactional outbox and publisher used by both workflows.
- **Integrations** may independently fan published tenant outbox events into webhook deliveries. Webhook transport is not owned by Notifications.

See [Events and rallies](events-and-rallies.md), [Contributions and reporting](contributions-and-reporting.md), [Integrations](integrations.md), and the [security baseline](../security/security-baseline.md) for the surrounding contracts.
