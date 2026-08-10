# Events domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Events`  
**Primary authorization boundary:** `alliance.view` for member Event access; `events.manage` for coordination/mutation

## 1. Purpose and ownership

Events owns Alliance-scoped scheduling, Event templates, occurrences, recurrence, registration/waitlisting/cancellation, attendance, Event instructions, calendar/export behavior, and the Event-side coordination boundary used by Notifications and Rallies.

Rally guidance/formations/groups/assignments/participation are owned by Rallies. Durable reminder rule/delivery state and due-time coordination are owned by Notifications.

## 2. Scope

### In scope

- one-time and recurring Events;
- Event templates;
- UTC occurrence persistence with Alliance-local recurrence arithmetic;
- registration/waitlist/cancellation/capacity;
- Event attendance/no-show state;
- Event instructions and member/coordinator Event views;
- reminder configuration integration with Notifications;
- Event CSV export and authenticated iCalendar response; and
- Event-side links to Rallies formations/groups/assignments.

### Out of scope

- durable reminder-delivery state, owned by Notifications;
- Rally guidance, formations, groups, assignments, and rally participation, owned by Rallies;
- Contribution records derived from attendance, owned by Contributions; and
- long-lived public calendar subscription tokens.

## 3. Domain model

### Event and occurrence

An Event defines Alliance-owned scheduling/instruction configuration. Occurrences persist concrete UTC start/end timestamps generated from the Alliance-local schedule.

A creation workflow may specify:

- title;
- first start time in the Alliance time zone;
- duration;
- optional capacity;
- optional registration-opening offset;
- registration-closing offset;
- recurrence (`none`, `daily`, or `weekly`) and interval;
- optional recurrence end; and
- instructions.

### Event template

A template stores reusable Event configuration. Scheduling from a template selects the first Alliance-local start time plus optional recurrence end/title override. Template provenance is persisted in the same transaction as the Event and generated occurrences.

### Registration

A membership's current Event participation registration is registered/waitlisted/cancelled according to capacity and workflow state. Repeated registration requests do not create duplicate registrations.

### Attendance

Coordinators record Event attendance as `attended` or `no-show`. Events remains authoritative for this fact even when Contributions later derives contribution records from it.

## 4. Core invariants

1. Every Event/occurrence/registration/attendance record is Alliance-scoped.
2. Recurrence arithmetic preserves the Alliance-local wall-clock time across daylight-saving transitions.
3. Generated occurrences are stored in UTC.
4. Members see both user-local and Alliance-local timing so scheduling context is explicit.
5. Capacity is enforced transactionally; full Events waitlist rather than overbook.
6. Cancelling a registered place promotes the oldest eligible waitlisted registration.
7. Repeated join requests do not create duplicate registrations.
8. Attendance truth remains Events-owned even when another domain consumes it.
9. Event reminder delivery is re-evaluated by Notifications; cancelling registration can make a materialized reminder ineligible.
10. Authenticated CSV/ICS output is scoped to the active Alliance.

## 5. Lifecycles and workflows

### Member Event list

Members open **Alliance → Events** to see scheduled activities. Each Event shows:

- start in the User's configured time zone;
- the same start in the Alliance time zone;
- capacity where configured;
- current registration state; and
- direct Event-detail link.

### Join an Event

Use **Join event** while registration is open. If capacity is available, the member becomes registered; otherwise the member is waitlisted.

### Cancel registration

Cancellation removes the member's active place. If a registered spot is freed and eligible waitlisted members exist, the oldest eligible waitlisted registration is promoted automatically.

### Event details

The Event detail page presents:

- full instructions;
- local and Alliance times;
- registration/waitlist counts;
- Rallies-owned recommended formations/guidance where configured;
- Rally groups/current assignments; and
- the member's saved Rallies-owned formations.

### Coordinate Events

Members with `events.manage` can open **Coordinate events**. Read-only coordination views do not force password reconfirmation; privileged mutations require recent password confirmation.

### Create recurring Event

Coordinators supply the Event fields above. Recurrence generation preserves Alliance-local wall-clock time across DST and stores concrete occurrences as UTC.

### Schedule from template

A coordinator chooses the template, first Alliance-local start time, optional recurrence end, and optional title override. Event/occurrence/template provenance persists transactionally.

### Configure reminder timing

Coordinators configure reminder minutes-before-start through the Event workflow. Notifications owns materialization, due-time delivery state, cancellation recheck, and scheduler/outbox recovery.

Recent sent reminders appear on the Events page with Event name, local start, delivery time, and **Open event** link.

### Record attendance

Coordinators record `attended`/`no-show` for Event attendance. Privileged mutation uses the same tenant/authorization/password-confirmation boundary as other Event management.

### CSV export

The Events page provides an authenticated CSV export of the active Alliance's upcoming occurrences with explicit UTC start/end fields and Alliance time zone.

The response is private/non-shared-cacheable, and another Alliance's Events are excluded by the active-Alliance query boundary.

### iCalendar foundation

The Events page also provides an authenticated `.ics` response for upcoming occurrences. Timestamps are emitted in UTC and Event text is escaped for iCalendar output.

The current response is authenticated download/feed foundation only; no long-lived public subscription token exists.

## 6. Authorization and tenancy

Member Event access requires authenticated/verified active-Alliance context plus `alliance.view`.

Event coordination/mutation requires `events.manage`; privileged mutations additionally require recent password confirmation.

Submitted Event/occurrence/registration identifiers are re-resolved beneath the active Alliance before read/mutation.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active tenant/time-zone context.
- **Memberships/Authorization** — active member identity plus `alliance.view`/`events.manage`.
- **Notifications** — reminder rule/delivery materialization and due-time coordination.
- **Rallies** — Event-linked guidance, formations, groups, assignments, and Rally participation presentation.
- **Audit/Platform** — privileged evidence/outbox infrastructure.

### Exposes

- Event/occurrence/registration/attendance facts;
- attended registrations consumed by Contributions reconciliation;
- Event occurrence/registration facts consumed by Notifications reminder eligibility; and
- Event occurrence identity consumed by Rallies coordination.

## 8. Persistence and data ownership

Events owns Event schedules, templates, occurrences, registrations, capacity/waitlist state, attendance, and Event instructions.

Notifications owns durable reminder rules/deliveries. Rallies owns guidance/formations/groups/assignments/participation. Contributions owns derived contribution records.

## 9. Events, outbox and integrations

Event changes/attendance/reminder-triggering state use the shared audit/outbox foundation where required. Notifications publishes `event.reminder.requested` through the shared outbox.

The external read-only Events API is owned by Integrations and exposes a bounded tenant-safe representation; it does not transfer Event persistence ownership.

## 10. HTTP, UI and API surfaces

Primary first-party surfaces:

- **Alliance → Events** member list;
- Event detail;
- **Coordinate events** management workspace;
- authenticated upcoming-Event CSV export; and
- authenticated `.ics` response.

The external read-only `/api/v1/events` contract is documented by Integrations.

## 11. Background processing

Events recurrence materialization and Notifications reminder coordination use the documented scheduler. Current reminder commands are owned operationally by Notifications:

- `events:sync-reminders --limit=250`;
- `events:queue-reminders --limit=100`.

See [Notifications](../notifications/README.md) for exact state/recovery semantics.

## 12. Failure, idempotency and concurrency

- Capacity is enforced transactionally.
- Duplicate registration requests do not multiply registrations.
- Cancelling a registered place promotes the oldest eligible waiter.
- Another tenant's Event identifiers fail closed.
- Unexpected Event time should be diagnosed by comparing stored UTC, Alliance time zone, and User time zone before editing the schedule.
- Missing reminder delivery is diagnosed through Notifications state rather than manually duplicating Event registration/reminder rows.

## 13. Security and privacy

All Event pages, registrations, attendance, exports, and cross-domain coordination are Alliance scoped. Submitted IDs are re-resolved under the active Alliance.

CSV/ICS responses are authenticated and tenant-bound; the current iCalendar foundation deliberately does not issue a long-lived public token.

## 14. Observability and operations

For reminder issues verify, in order:

1. the member remains eligible (`registered`/`waitlisted`);
2. the reminder configuration is enabled;
3. a delivery exists for occurrence/rule/membership;
4. `due_at` has passed;
5. scheduler commands are running;
6. delivery progressed `pending` → `queued` → `sent`; and
7. the corresponding outbox message is published without unresolved error.

See [Notifications](../notifications/README.md) and [Operations](../../operations/README.md).

## 15. Testing and architecture enforcement

Tests should protect:

- recurrence/DST behavior;
- registration-window/capacity/waitlist concurrency;
- duplicate registration idempotency;
- Event attendance authority;
- Alliance isolation across calendar/feed/export/queries;
- authenticated CSV/ICS output; and
- the ownership boundary with Rallies/Notifications/Contributions.

## 16. Explicit non-capabilities

Events does not:

- own durable reminder-delivery state;
- own Rally guidance/formations/groups/participation;
- independently create Contribution records except through the supported Contributions reconciliation boundary; or
- provide a public long-lived iCalendar subscription token.

## 17. Capability documents

No separate Events capability files are required at present. Event scheduling/registration/attendance remain coherent in this root. Rally-specific behavior is documented by the separate [Rallies domain](../rallies/README.md).

## 18. Related documentation

- [Rallies domain](../rallies/README.md)
- [Notifications domain](../notifications/README.md)
- [Contributions domain](../contributions/README.md)
- [Alliances domain](../alliances/README.md)
- [Authorization domain](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Events/README.md`](../../../app/Domain/Events/README.md)
