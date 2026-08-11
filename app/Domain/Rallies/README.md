# Rallies domain

## Purpose

Owns Alliance Rally guidance, member saved formations, Event-specific recommended formations, Rally groups, lead/joiner/standby assignments, and Rally participation state.

## Owned code

Runtime code in this module owns guidance/formation/group/assignment/participation persistence and first-party Rally coordination workflows linked to Events occurrences.

## Public contracts

- effective-dated Rally guidance and recommended formations shown in Event detail;
- member saved formations;
- Rally group/assignment coordination; and
- Rally participation state.

Coordinator/assignment responsibility never grants authorization.

## Dependencies

- `Events` — occurrence identity/context; Event scheduling/registration/attendance remain Events-owned.
- `Alliances` — active tenant context.
- `Memberships` — active same-Alliance participants.
- `Authorization` — `alliance.view` / `events.manage`.
- `Audit` / Platform outbox — privileged/durable evidence.

## Canonical documentation

- [`docs/domains/rallies/`](../../../docs/domains/rallies/README.md)
- [Events domain](../../../docs/domains/events/README.md)
