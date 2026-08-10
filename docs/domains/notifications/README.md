# Notifications domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Notifications`  
**Primary authorization boundary:** inherited active-Alliance/member eligibility from source workflow; management configuration remains in owning feature domains

## 1. Purpose and ownership

Notifications owns durable notification-delivery coordination that should not live inside the feature domain originating the message.

The current implementation covers:

- in-app Event reminder materialization and delivery state; and
- scheduled Contribution-report request coordination through the transactional outbox.

Events remains authoritative for Event schedules, occurrences, registrations, and attendance. Contributions remains authoritative for report schedules, report versions, and report-run semantics. Notifications owns when those facts produce a durable delivery/request and how repeated scheduler execution remains safe.

The current implementation does **not** define a generic email, SMS, push, or third-party messaging provider.

## 2. Scope

### In scope

- `EventReminderRule` and `EventReminderDelivery` state;
- deterministic Event reminder materialization;
- due reminder queueing and eligibility recheck;
- scheduled Contribution report due-time coordination;
- scheduler commands/limits/locking relevant to those workflows;
- idempotency and catch-up/recovery; and
- interaction with the shared transactional outbox.

### Out of scope

- Event schedule/registration/attendance ownership;
- Contribution report semantics/schedule persistence ownership;
- generic email/SMS/push transport;
- webhook transport, which belongs to Integrations; and
- external-provider delivery claims.

## 3. Domain model

### Event reminder rules and deliveries

Notifications owns `EventReminderRule` and `EventReminderDelivery`. Events owns the occurrence and registration facts used to determine eligibility.

A reminder delivery is unique for the combination of:

- Alliance;
- occurrence;
- reminder rule; and
- membership.

`due_at` equals occurrence start minus the rule's configured `minutes_before_start`.

Delivery states are:

| State | Meaning |
| --- | --- |
| `pending` | Materialized and waiting for `due_at`. |
| `queued` | A durable `event.reminder.requested` outbox message exists. |
| `sent` | The outbox message was published and the in-app reminder is considered delivered. |
| `cancelled` | The member is no longer eligible when the reminder becomes due. |

A `sent` reminder means durable in-app/outbox delivery completed; it does not claim delivery through an external email/SMS/push provider.

### Scheduled Contribution report coordination

Contribution schedules/runs belong to Contributions. Notifications coordinates the due occurrence and deterministic request identity derived from schedule ID, due timestamp, and report version.

## 4. Core invariants

1. Reminder materialization is deterministic per Alliance/occurrence/rule/membership.
2. Reminder outbox creation is deterministic per delivery.
3. Contribution report-run/request creation is deterministic per schedule/due-time/report-version.
4. Scheduler execution is at-least-once and must remain safe to rerun.
5. Outbox publication is at-least-once; consumers remain idempotent.
6. Reminder eligibility is rechecked before due delivery is queued.
7. A cancelled/ineligible registration never receives a newly queued reminder merely because an earlier delivery record exists.
8. Tenant identity is explicit in delivery/request/outbox state and is never inferred from process-global state.
9. The `integrations` queue is not the Notifications delivery mechanism.

## 5. Lifecycles and workflows

### Materialize Event reminders

`events:sync-reminders` scans future scheduled occurrences. For each enabled rule and eligible registration in `registered` or `waitlisted`, Notifications creates at most one deterministic delivery.

Repeated materialization does not create duplicate deliveries.

### Queue due reminders

`events:queue-reminders` claims due `pending` deliveries in due-time order. PostgreSQL uses `FOR UPDATE SKIP LOCKED` so concurrent scheduler workers do not claim the same row.

Before queueing, Notifications rechecks the Events registration. If no longer `registered`/`waitlisted`, the delivery becomes `cancelled` and no reminder outbox message is created.

For an eligible delivery, Notifications creates `event.reminder.requested` with a deterministic key and moves the delivery to `queued`.

When Platform publishes that outbox event, `MarkEventReminderPublished` moves the queued delivery to `sent` and records `sent_at`.

### Queue scheduled Contribution reports

`contributions:queue-reports` selects enabled schedules whose `next_due_at` has arrived. PostgreSQL uses `FOR UPDATE SKIP LOCKED` so concurrent workers do not advance the same occurrence.

For each due occurrence, Notifications:

1. derives deterministic SHA-256 identity from schedule ID, due timestamp, and report version;
2. creates/reuses the corresponding `ContributionReportRun` in `queued` state;
3. creates/reuses `contribution.report.requested` outbox state;
4. records recipient membership, report version, and `as_of`; and
5. advances `next_due_at` in the schedule's configured time zone.

Supported cadences are `daily`, `weekly`, and `monthly`; monthly advancement uses no-overflow calendar arithmetic.

## 6. Authorization and tenancy

Notifications is primarily a coordination domain: source-domain authorization determines who may configure Events reminders or Contribution report schedules.

Every persisted reminder/report request carries the owning Alliance ID and the membership/source identifiers necessary to perform only the intended tenant-scoped work.

Member-facing reminder reads must resolve under active Alliance/membership context. Cross-domain source rows are queried with their Alliance context.

## 7. Cross-domain contracts

### Consumes

- **Events** — occurrences, registrations, attendance, and reminder configuration context.
- **Contributions** — report schedules, report versions, report-run semantics.
- **Platform** — transactional outbox and publisher.
- **Alliances/Memberships** — tenant/member identity used by delivery state.

### Exposes

- durable reminder delivery state and due-time coordination to Events UI/workflows; and
- durable scheduled-report request coordination to Contributions.

Webhook transport remains owned by Integrations.

## 8. Persistence and data ownership

Notifications owns reminder rule/delivery state. Contributions continues to own Contribution report schedules/runs even when Notifications coordinates due-time requests.

Due records remain persisted across scheduler interruption so catch-up does not depend on recreating source business actions.

## 9. Events, outbox and integrations

Current workflows use scheduler + PostgreSQL + transactional outbox.

- `event.reminder.requested` is the durable Event-reminder request event.
- `contribution.report.requested` is the durable scheduled-report request event.
- `outbox:publish --limit=100` is required for queued requests to progress through the shared durable boundary.

Integrations may independently fan externally eligible tenant outbox events into webhooks, but webhook transport is not owned by Notifications.

## 10. HTTP, UI and API surfaces

Recent sent in-app reminders appear on the Events page with Event name, local start time, delivery time, and direct Event link.

Notifications does not expose a generic public notification API or provider-management surface.

## 11. Background processing

The scheduler runs these current coordination commands every minute:

| Command | Purpose | Scheduler protection |
| --- | --- | --- |
| `events:sync-reminders --limit=250` | Materialize deliveries for future scheduled occurrences. | `onOneServer()`, `withoutOverlapping(10)` |
| `events:queue-reminders --limit=100` | Queue due Event reminders through the outbox. | `onOneServer()`, `withoutOverlapping(10)` |
| `contributions:queue-reports --limit=50` | Queue due Contribution-report requests. | `onOneServer()`, `withoutOverlapping(10)` |

`outbox:publish --limit=100` also runs every minute.

Command-level limits remain bounded in code even if a larger value is supplied. Operators should use documented defaults unless deliberately draining backlog.

## 12. Failure, idempotency and concurrency

### Reminder remains `pending`

Verify `due_at`, registration eligibility, and `events:queue-reminders` execution.

### Reminder is `queued` but not `sent`

Inspect the matching `event.reminder.requested` outbox message. Delivery becomes sent only after the Platform publisher emits the matching publication event.

### Scheduled report did not queue

Verify schedule enabled state, `next_due_at`, cadence/time zone, command execution, and whether the deterministic report-run identity already exists.

### Scheduler interruption

Restore the scheduler and rerun the bounded command. The workflows are designed for catch-up; persisted due state plus idempotency prevents routine duplication.

### Outbox backlog

Repair the outbox publisher rather than replaying the originating Event/registration/Contribution action solely to force delivery.

## 13. Security and privacy

Payloads and routine logs should contain only identifiers/information necessary for downstream work. Sensitive candidate data, secrets, private notes, or unrelated member data do not belong in reminder/report payloads.

Tenant identity must never be inferred from hidden global process state.

## 14. Observability and operations

Operators should be able to inspect:

- delivery state (`pending`/`queued`/`sent`/`cancelled`);
- `due_at`/`sent_at`;
- scheduler command execution;
- outbox publication/error state; and
- Contribution schedule/run identity.

See [Background processing](../../operations/background-processing.md) and [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests should protect:

- deterministic reminder materialization;
- duplicate scheduler execution;
- due eligibility recheck/cancellation;
- PostgreSQL concurrent claiming behavior;
- report-request idempotency and cadence advancement;
- scheduler catch-up; and
- the ownership boundaries with Events, Contributions, Platform, and Integrations.

## 16. Explicit non-capabilities

Notifications does not currently provide:

- generic email delivery;
- SMS delivery;
- push-provider delivery;
- a third-party messaging-provider abstraction; or
- webhook transport.

## 17. Capability documents

No separate Notifications capability files are required at present.

## 18. Related documentation

- [Events domain](../events/README.md)
- [Contributions domain](../contributions/README.md)
- [Platform domain](../platform/README.md)
- [Integrations domain](../integrations/README.md)
- [Background processing](../../operations/background-processing.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Notifications/README.md`](../../../app/Domain/Notifications/README.md)
