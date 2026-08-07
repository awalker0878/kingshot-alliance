# Events and Rallies

Phase 3 provides alliance-scoped scheduling, registration, reminders, formation guidance, rally coordination, attendance, participation tracking, and exports.

The detailed user and coordinator guide is [docs/EVENTS_AND_RALLIES.md](../EVENTS_AND_RALLIES.md).

## Member workflow

Members open **Alliance → Events** to see upcoming alliance activities. Event surfaces show both user-local and alliance-local time, capacity when configured, registration state, and event details.

### Registration

- Join while the registration window is open.
- If capacity remains, the member becomes registered.
- If the event is full, the member is waitlisted rather than overbooked.
- Repeated requests are idempotent and do not create duplicate registrations.
- Cancelling a registered place promotes the oldest eligible waitlisted member.

### Reminders

Coordinators may configure reminder offsets before an event begins. Eligible deliveries flow through the durable reminder/outbox pipeline. A member's eligibility is rechecked before a due reminder is queued so cancelled registrations do not receive stale reminders.

### Saved formations

Members can save named formations containing heroes, troop percentages, notes, and a default flag. Infantry + cavalry + archer percentages must total exactly 100%.

## Coordinator workflow

Members with `events.manage` can use the coordination workspace. Read-only coordination views remain available under normal authorization; privileged coordinator mutations also require recent password confirmation.

Coordinators can:

- create one-time or recurring events;
- define capacity and registration windows;
- create event templates;
- configure reminders;
- create effective-dated rally guidance;
- create occurrence-specific recommended formations;
- create rally groups and lead/joiner assignments;
- use standby when joiner capacity is reached;
- record event attendance and rally participation.

## Time-zone model

- Coordinators author schedules in the alliance time zone.
- Recurrence arithmetic stays in the alliance time zone so wall-clock schedules survive daylight-saving changes.
- Occurrences are persisted in UTC.
- Members see user-local and alliance-local values.
- CSV and iCalendar exports use explicit UTC timestamps.

## Rally guidance

Game-specific advice is configuration, not controller/UI code. Guidance can record troop ratios, hero recommendations, lead/joiner rules, notes, effective dates, source, and rationale.

This keeps changing game strategy replaceable without changing core application behavior.

## Exports

Phase 3 provides authenticated alliance-scoped CSV and `.ics` output for upcoming occurrences. The calendar foundation is authenticated; it does not create a long-lived public subscription token.

## Security boundary

Events, registrations, reminders, formations, rally guidance, groups, assignments, attendance, participation, and exports are alliance-scoped. Privileged submitted IDs are re-resolved inside the active alliance before mutation.

See [Security and Tenancy](Security-and-Tenancy.md), [Phase 3 threat model](../PHASE_3_THREAT_MODEL.md), and [Phase 3 operations](../PHASE_3_OPERATIONS.md).
