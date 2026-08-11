# Events domain

## Purpose

Owns Alliance Event schedules/templates/occurrences, recurrence, registration/waitlisting/cancellation, Event attendance, Event instructions, and authenticated calendar/export behavior.

## Owned code

Runtime code in this module owns Event/occurrence/registration/attendance persistence, recurrence/capacity workflows, Event queries, and first-party member/coordinator Event surfaces.

## Public contracts

- Event occurrence/registration facts consumed by Notifications reminder coordination.
- Event attendance facts consumed by Contributions reconciliation.
- Event occurrence identity/context consumed by Rallies.
- `alliance.view` member reads and `events.manage` coordination mutations.

## Dependencies

- `Alliances` — active tenant/time-zone context.
- `Memberships` / `Authorization` — member identity and Event permissions.
- `Notifications` — durable reminder delivery state/coordination.
- `Rallies` — Rally-specific Event coordination detail.
- `Audit` / Platform outbox — privileged/durable evidence.

## Canonical documentation

- [`docs/domains/events/`](../../../docs/domains/events/README.md)
- [Rallies domain](../../../docs/domains/rallies/README.md)
- [Notifications domain](../../../docs/domains/notifications/README.md)
