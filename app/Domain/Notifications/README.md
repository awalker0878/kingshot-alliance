# Notifications domain

## Purpose

Owns durable Event-reminder delivery state and scheduled Contribution-report due-time coordination. Source feature domains remain authoritative for the Event/report facts that trigger notification work.

## Owned code

- reminder rule/delivery persistence for Event reminders;
- actions/commands that materialize and queue due reminder work;
- due-time coordination for scheduled Contribution report requests; and
- listeners that advance durable delivery state after shared outbox publication.

## Public contracts

Intentional contracts include:

- deterministic Event reminder materialization from Events-owned occurrence/registration facts;
- durable reminder state (`pending`, `queued`, `sent`, `cancelled`);
- deterministic scheduled Contribution-report request coordination; and
- idempotent scheduler/outbox recovery behavior.

Notifications does not own a generic email, SMS, push, or webhook transport.

## Dependencies

- `Events` — authoritative occurrences/registrations and reminder configuration context.
- `Contributions` — authoritative report schedules/versions/runs.
- `Platform` — transactional outbox/publisher and scheduler infrastructure.
- `Alliances` / `Memberships` — explicit tenant/member identity for delivery state.

## Canonical documentation

- [`docs/domains/notifications/`](../../../docs/domains/notifications/README.md)
- [Background processing](../../../docs/operations/background-processing.md)
