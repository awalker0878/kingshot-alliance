# Event reminders

[← Notifications domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Notifications

## 1. Purpose

Defines deterministic Event reminder materialization, due-time queueing, eligibility recheck, and durable delivery state.

Events owns Event/occurrence/registration facts. Notifications owns when those facts produce a durable reminder request and how repeated scheduler execution remains safe.

## 2. Scope and non-scope

In scope:

- Event reminder rules/deliveries;
- deterministic materialization from eligible registrations;
- `pending`/`queued`/`sent`/`cancelled` state;
- due-time queueing;
- current-registration eligibility recheck;
- outbox publication handoff; and
- scheduler catch-up/idempotency.

Out of scope:

- Event schedule/registration ownership;
- generic email/SMS/push transport;
- webhook delivery; and
- external-provider delivery guarantees.

## 3. Model and state

A reminder delivery is unique for Alliance + occurrence + reminder rule + membership.

`due_at` is occurrence start minus the rule's configured minutes-before-start.

States:

- `pending` — materialized and awaiting due time;
- `queued` — durable reminder request exists in the outbox;
- `sent` — the matching outbox message was published and the in-app reminder is considered delivered; and
- `cancelled` — member eligibility was no longer valid at due time.

## 4. Invariants

1. Materialization is deterministic per Alliance/occurrence/rule/membership.
2. Repeated scheduler execution does not duplicate deliveries.
3. Due queueing rechecks Events-owned registration eligibility.
4. Only registered/waitlisted eligible members are queued.
5. Cancellation/ineligibility prevents a new reminder request.
6. Outbox identity is deterministic per delivery.
7. Tenant identity is explicit in delivery/outbox state.
8. `sent` means durable in-app/outbox completion, not external email/SMS/push delivery.

## 5. Workflows

### Materialize

`events:sync-reminders` scans bounded future occurrences/rules/eligible registrations and creates missing deterministic deliveries.

### Queue due

`events:queue-reminders` claims due pending deliveries in order. Concurrent PostgreSQL workers use row-lock/skip-locked semantics so one delivery is not claimed twice.

Before queueing, the current Events registration is rechecked. Ineligible deliveries become cancelled. Eligible deliveries create/reuse the deterministic `event.reminder.requested` outbox message and move to queued.

### Mark sent

When Platform publishes the matching outbox message, the Notifications listener advances the queued delivery to sent and records completion time.

### Catch up after interruption

Restore scheduler/outbox execution and rerun the bounded commands. Persisted due state plus deterministic identities make catch-up safe.

## 6. Authorization, tenancy and privacy

Source Event configuration/registration authorization remains Events-owned. Reminder state is always Alliance/member scoped.

Member-facing reminder reads must resolve under active Alliance/membership context. Payloads contain only the information needed for the reminder; unrelated private member data is excluded.

## 7. Persistence and query semantics

Notifications owns reminder rule/delivery state. Events owns occurrences/registrations. Platform owns generic outbox infrastructure.

Due queries are bounded and concurrency-safe. A persisted delivery is not proof of current eligibility; due queueing rechecks source state.

## 8. Events, integrations and background processing

Scheduler commands materialize and queue reminders. Platform outbox publication completes the durable handoff.

Webhook transport remains Integrations-owned. An internal reminder event does not automatically create an external messaging contract.

## 9. Failure, idempotency and concurrency

- Duplicate sync runs reuse delivery identity.
- Concurrent queue runs use row locking/skip-locked behavior.
- Eligibility changes cancel rather than send stale reminders.
- Queued-but-not-sent state is diagnosed through outbox publication.
- Scheduler interruption is recoverable by rerun without source-action replay.

## 10. Operations and observability

Inspect delivery state, `due_at`, registration eligibility, scheduler execution, matching outbox state, and `sent_at`.

Repair scheduler/outbox dependencies rather than recreating Event registrations to force reminders.

## 11. Tests and validation

Tests should cover:

- deterministic materialization;
- duplicate scheduler execution;
- due eligibility recheck/cancellation;
- concurrent claiming;
- deterministic outbox identity;
- publisher listener transition to sent;
- catch-up after interruption; and
- tenant isolation.

## 12. Related documentation

- [Notifications domain](README.md)
- [Events](../events/README.md)
- [Event registration and attendance](../events/registration-and-attendance.md)
- [Platform transactional outbox](../platform/transactional-outbox.md)
- [Background processing](../../operations/background-processing.md)
