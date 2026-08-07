# Events and Rallies

Phase 3 adds alliance-scoped scheduling, registration, reminders, formation guidance, rally coordination, attendance, and exports.

## Member workflow

Open **Alliance → Events** to see scheduled alliance activities.

Each event shows:

- the event time in your configured user time zone;
- the same time in the alliance time zone;
- capacity when configured;
- your current registration state;
- a direct link to event details.

### Join an event

Use **Join event** while the registration window is open. If capacity is available you are registered. If the event is full, the application places you on the waitlist rather than overbooking the event.

Repeated registration requests do not create duplicate registrations.

### Cancel a registration

Use **Cancel registration** from the event list or event detail page. If you held a registered place and the waitlist has members, the oldest eligible waitlisted registration is promoted automatically.

### In-app reminders

Coordinators can configure reminders a number of minutes before an event starts. Eligible reminders are delivered through the application's durable reminder/outbox pipeline.

Recent delivered reminders appear at the top of the **Events** page with:

- event name;
- event start in your local time;
- reminder delivery time;
- a direct **Open event** link.

A cancelled registration is rechecked before a due reminder is queued, so a materialized reminder is suppressed if you are no longer eligible.

### Event details and formations

Open an event to see:

- full instructions;
- local and alliance times;
- registration/waitlist counts;
- event-specific recommended formations;
- troop percentages and hero recommendations;
- guidance source/rationale and effective dates when provided;
- rally groups and current assignments;
- your saved formations.

### Save your own formation

From an event detail page, create a named formation with:

- optional hero names;
- infantry percentage;
- cavalry percentage;
- archer percentage;
- optional notes;
- optional default status.

The three troop percentages must total exactly 100%.

## Coordinator workflow

Members with the `events.manage` permission can open **Coordinate events** from the alliance home or Events page. Coordinator mutations require recent password confirmation; read-only coordination views remain available without forcing reconfirmation.

### Create an event

Provide:

- title;
- first start time in the alliance time zone;
- duration;
- optional capacity;
- optional registration opening offset;
- registration closing offset;
- recurrence (`none`, `daily`, or `weekly`) and interval;
- optional recurrence end;
- instructions.

Recurring schedules preserve the alliance-local wall-clock time across daylight-saving transitions. Generated occurrences are stored in UTC.

### Event templates

Create a template for repeatable alliance activities, then schedule a new event from that template by choosing the first local start time and optional recurrence end/title override.

Template provenance is persisted in the same transaction as the event and its generated occurrences.

### Reminder rules

Choose an event and set the number of minutes before start. The scheduler materializes one deterministic delivery per occurrence/rule/member and queues due reminders through the transactional outbox.

### Rally guidance

Guidance is configuration data, not hard-coded game logic. A guidance record can contain:

- troop ratio;
- hero recommendations;
- lead requirements;
- joiner guidance;
- notes;
- effective-from date;
- optional effective-until date;
- source;
- rationale.

Use effective dates/source/rationale when recommendations change so members can understand why the current advice is shown.

### Event recommended formations

For an occurrence, create a named formation for a role such as lead or joiner. It may link to an effective-dated guidance rule and contains its own troop ratio/heroes/notes.

### Rally groups and assignments

Create one or more rally groups for an event occurrence. A group may have a maximum number of joiners and a recommended formation.

Assign active alliance members as lead/joiner roles and optional numbered slots. When a group has reached its configured joiner capacity, additional joiners are recorded as **standby** rather than silently overbooked.

### Readiness, attendance, and participation

The coordinator dashboard lists registrations and rally assignments for each occurrence. Coordinators can record:

- event attendance (`attended` or `no-show`);
- rally participation (`participated` or `no-show`).

These changes require recent password confirmation and use the same tenant-scoped authorization/audit boundary as other privileged Phase 3 mutations.

## CSV export

The Events page provides an authenticated CSV export of the active alliance's upcoming event occurrences. It includes explicit UTC start/end fields and the alliance time zone.

The response is private and non-cacheable by shared caches. Another alliance's events are excluded by the active-alliance query boundary and regression tests.

## iCalendar foundation

The Events page also provides an authenticated `.ics` response containing the active alliance's upcoming occurrences. Event timestamps are emitted in UTC and event text is escaped for iCalendar output.

Phase 3 provides the authenticated feed/download foundation; it does not issue a long-lived public calendar subscription token. A future public/tokenized feed requires a separate revocation and security design.

## Time-zone model

- Alliance coordinators author event times in the alliance time zone.
- Recurrence arithmetic stays in the alliance time zone.
- Occurrences persist as UTC timestamps.
- Members see both user-local and alliance-local representations.
- Exports use explicit UTC values.

If an event appears at an unexpected time, compare the stored UTC timestamp, alliance time zone, and user's configured time zone before editing the schedule.

## Troubleshooting

### A member expected a reminder but did not see one

Check, in order:

1. the member is still actively registered/waitlisted as eligible;
2. the reminder rule is enabled;
3. a delivery exists for the occurrence/rule/membership;
4. its `due_at` has passed;
5. scheduler commands are running;
6. the delivery progressed from `pending` → `queued` → `sent`;
7. the associated outbox message has `published_at` and no unresolved `last_error`.

See `docs/PHASE_3_OPERATIONS.md` for the operational runbook.

### An event is full

Capacity is enforced transactionally. New members waitlist. Cancelling a registered place automatically promotes the oldest eligible waitlisted member.

### A formation will not save

Confirm infantry + cavalry + archer percentages total exactly 100.

## Security boundary

All member/coordinator event pages, exports, reminder inbox records, formations, registrations, guidance, rally groups, and assignments are alliance-scoped. Submitted object IDs are re-resolved under the active alliance before privileged mutations. Privileged coordinator mutations also require recent password confirmation.

See `docs/PHASE_3_THREAT_MODEL.md` for the Phase 3 security review and `docs/PHASES_1_4_ALIGNMENT_AUDIT.md` for the integrated Phase 1–4 security boundary.
