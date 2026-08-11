# Events interfaces

[← Events domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Events  
**Code owner:** `app/Domain/Events`  
**Primary boundary:** Alliance event calendar/detail/registration/coordination, authenticated calendar exports, and Event facts consumed by other domains  
**P4 inventory decision:** Focused contract added — `calendar-exports.md`; existing `../registration-and-attendance.md` reused

## 1. Boundary purpose and ownership

Events owns the Alliance Event schedule/occurrence/registration/attendance contract and the Event-side facts consumed by Notifications, Contributions, Rallies, and Integrations. It exposes first-party member/coordinator workspaces plus authenticated CSV/iCalendar output.

Notifications owns durable reminder-delivery coordination, Rallies owns Rally-specific guidance/groups/participation, Contributions owns derived contribution records, and Integrations owns the external `/api/v1/events` representation.

## 2. Surface inventory

Material first-party surfaces in `routes/web.php` include:

- Event calendar index and occurrence detail;
- Event management workspace;
- member registration/cancellation;
- member saved-formation adapter into Rallies;
- manager Event/template/reminder creation;
- manager Rally guidance/recommended-formation/group/assignment/participation adapter routes; and
- `GET /alliance/events/export.csv` and `GET /alliance/events/feed.ics`.

The compatibility-sensitive file outputs are documented in [Calendar exports](calendar-exports.md). Registration/capacity/waitlist/attendance semantics remain in [Event registration and attendance](../registration-and-attendance.md).

## 3. Callers, authorization and tenancy

Member Event reads/registration require authenticated, verified active-Alliance context and applicable `alliance.view` semantics. Event coordination/mutation requires `events.manage` plus recent password confirmation in the privileged route group.

Rally-specific manager actions are reached through Event controller adapters but delegate to Rallies-owned actions and invariants. Submitted Event/occurrence/registration/Rally identifiers are re-resolved beneath the active Alliance.

## 4. Input and validation contracts

Event/template inputs validate schedule/timezone/recurrence/registration/coordination rules through owning controllers/actions. Registration/cancellation inputs are intentionally small because membership/occurrence context comes from the active request and persisted Event state.

Member saved-formation inputs validate name, up to five hero labels, 0–100 troop percentages, optional notes/default state; Rallies enforces formation composition invariants.

CSV/ICS exports take no caller-selected Alliance and use a fixed upcoming horizon defined in [Calendar exports](calendar-exports.md).

## 5. Output and disclosure contracts

Member Event payloads expose tenant-safe occurrence timing, registration state, approved instructions and Rally presentation data as applicable. Coordinator workspaces may expose management details according to permission.

Authenticated file outputs contain upcoming Event schedule fields only; they do not expose registration lists, attendance identities, private Rally assignments, or Contribution records. See [Calendar exports](calendar-exports.md).

`GET /api/v1/events` is a separate Integrations-owned bounded JSON projection.

## 6. Internal actions, queries and services

Supported Events contracts include schedule/occurrence queries, Event/template/reminder configuration actions, and the [Event registration and attendance](../registration-and-attendance.md) action set.

Events exposes occurrence/registration/attendance source facts to Notifications, Contributions, and Rallies. Those consumers must preserve Events ownership rather than updating Event persistence directly.

Rally-specific HTTP adapter methods invoke Rallies actions; route/controller placement does not transfer Rally semantic ownership into Events.

## 7. Events, outbox and cross-domain consumers

Material Event transitions may create audit/outbox evidence. Notifications consumes Event facts to materialize/queue reminders; Contributions consumes attendance through its explicit reconciliation contract; Integrations may serialize selected Event occurrence fields via the API and externally eligible webhook events under its own contract.

Internal outbox publication does not automatically make all Event payload fields a public webhook schema.

## 8. Commands, jobs and scheduled work

The current recurring reminder commands are operationally owned by Notifications while consuming Events facts:

- `events:sync-reminders {--limit=250}` — scheduled every minute with `--limit=250`;
- `events:queue-reminders {--limit=100}` — scheduled every minute with `--limit=100`.

Events itself has no separate queued Event-ingestion worker. Calendar exports are synchronous reads.

## 9. Files, imports, exports and external dependencies

Events owns two authenticated first-party file outputs:

- upcoming-occurrence CSV; and
- iCalendar (`.ics`) response.

Their exact schemas/metadata/compatibility are defined in [Calendar exports](calendar-exports.md). There is no current Event import format or long-lived public calendar token.

Event scheduling depends on Alliance timezone and PostgreSQL; reminder delivery additionally depends on Notifications/Platform scheduler/outbox runtime.

## 10. Failure, idempotency, versioning and compatibility

Cross-tenant or inaccessible Event/occurrence identifiers fail closed. Registration/cancellation/attendance concurrency behavior follows [Event registration and attendance](../registration-and-attendance.md).

Calendar output has no numeric schema version, so its documented field/UID/PRODID/time semantics form the current compatibility contract. Material changes require coordinated documentation/tests even without a version parameter.

## 11. Explicit non-capabilities

Events does not:

- provide anonymous or bearer-token calendar subscriptions;
- own Notifications reminder-delivery state;
- own Rally guidance/assignment/participation persistence;
- own Contributions records derived from attendance;
- expose registration/attendance/private Rally data in calendar exports; or
- own the external `/api/v1/events` authentication/schema boundary.

## 12. Focused contracts, evidence and related documentation

Focused contracts:

- [Calendar exports](calendar-exports.md) — new P4 CSV/iCalendar compatibility contract.
- [Event registration and attendance](../registration-and-attendance.md) — accepted concurrency-sensitive Event lifecycle reused by P4.

Related documentation:

- [Events domain](../README.md)
- [Events security](../security/README.md)
- [Events operations](../operations/README.md)
- [Notifications interfaces](../../notifications/interfaces/README.md)
- [Rallies](../../rallies/README.md)
- [Contributions event reconciliation](../../contributions/event-reconciliation.md)
- [Integrations API](../../integrations/api.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
