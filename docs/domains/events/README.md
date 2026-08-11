# Events domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Events`  
**Primary authorization boundary:** `alliance.view` for member Event access; `events.manage` for coordination/mutation

## 1. Purpose and ownership

Events owns Alliance Event schedules/templates/occurrences, recurrence, member registration/capacity/waitlist, attendance, Event instructions, calendar/export behavior, and the Event-side contracts consumed by Notifications, Contributions, and Rallies.

Rallies owns Rally-specific guidance/groups/participation; Notifications owns durable reminder state.

## 2. Scope

In scope: one-time/recurring Events, templates, UTC occurrence materialization, registration/capacity/waitlist/cancellation, Event attendance, instructions, authenticated CSV/ICS output, and Event-side cross-domain facts.

Out of scope: reminder delivery state, Rally coordination persistence, Contribution records derived from attendance, and long-lived public calendar subscription tokens.

## 3. Domain model

Event/template configuration defines Alliance-local schedule rules. Concrete occurrences are persisted as UTC timestamps while recurrence arithmetic preserves Alliance-local wall-clock intent across DST.

The concurrency-sensitive registration/attendance lifecycle is documented in [Event registration and attendance](registration-and-attendance.md).

## 4. Core invariants

1. Event-owned records are Alliance scoped.
2. Recurrence preserves Alliance-local wall-clock intent and stores concrete UTC occurrences.
3. Capacity/waitlist/attendance semantics follow [registration-and-attendance.md](registration-and-attendance.md).
4. Events remains authoritative for attendance.
5. Notifications owns reminder delivery state and rechecks registration eligibility.
6. Rallies owns Rally guidance/formations/groups/participation.
7. Authenticated exports are tenant bound.

## 5. Lifecycles and workflows

Coordinators create one-time/recurring Events or schedule from templates, materializing UTC occurrences from Alliance-local rules. Members browse Event details and timing in user/Alliance context.

Registration, cancellation/promotion, and attendance are defined in [Event registration and attendance](registration-and-attendance.md).

CSV exports expose upcoming occurrences for the active Alliance. Authenticated ICS output provides bounded calendar foundation without a public subscription token.

## 6. Authorization and tenancy

Member Event access requires authenticated/verified active-Alliance context plus `alliance.view`. Coordination/mutation requires `events.manage` and recent password confirmation where applicable. Submitted identifiers are re-resolved beneath the active Alliance.

## 7. Cross-domain contracts

Consumes Alliances tenant/time zone, Memberships/Authorization, Notifications reminder coordination, Rallies presentation, and Audit/Platform evidence infrastructure.

Exposes occurrence/registration/attendance facts to Notifications, Contributions, Rallies, and the bounded read-only Integrations representation.

## 8. Persistence and data ownership

Events owns schedules, templates, occurrences, registrations, capacity/waitlist state, attendance, and instructions. Notifications, Rallies, and Contributions retain their own state.

## 9. Events, outbox and integrations

Material Event transitions use audit/outbox evidence where required. Integrations owns the external read-only Event API representation; internal Event events do not automatically become public webhooks.

## 10. HTTP, UI and API surfaces

Primary first-party surfaces are Event list/detail, coordinator workspace, authenticated CSV export, and authenticated ICS response. External `/api/v1/events` is Integrations-owned.

## 11. Background processing

Occurrence/reminder scheduling uses the shared scheduler; reminder materialization/queueing commands are Notifications-owned. Events has no hidden game automation worker.

## 12. Failure, idempotency and concurrency

Recurrence/time issues are diagnosed from stored UTC plus Alliance/User time zones. Registration/attendance concurrency and idempotency are specified in [registration-and-attendance.md](registration-and-attendance.md). Cross-tenant IDs fail closed.

## 13. Security and privacy

Event registration/attendance/export data is Alliance private. CSV/ICS responses are authenticated and tenant bound. The current ICS contract does not issue long-lived public bearer access.

## 14. Observability and operations

Diagnose schedule/occurrence state separately from registration/attendance and Notifications delivery state. See [Operations](../../operations/README.md).

## 15. Testing and architecture enforcement

Tests protect recurrence/DST, occurrence materialization, authenticated exports, tenant isolation, registration/attendance concurrency, and ownership boundaries with Notifications/Rallies/Contributions.

## 16. Explicit non-capabilities

Events does not own reminder delivery, Rally guidance/participation, Contribution records, or public long-lived calendar tokens.

## 17. Capability documents

- [Event registration and attendance](registration-and-attendance.md) — registration windows, capacity/waitlist, cancellation/promotion, attendance, concurrency, and downstream source facts.

## 18. Related documentation

- [Rallies](../rallies/README.md)
- [Notifications](../notifications/README.md)
- [Contributions](../contributions/README.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [`app/Domain/Events/README.md`](../../../app/Domain/Events/README.md)
