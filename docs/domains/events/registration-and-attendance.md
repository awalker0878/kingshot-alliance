# Event registration and attendance

[← Events domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Events

## 1. Purpose

Defines the member registration, capacity/waitlist, cancellation/promotion, and Event attendance lifecycle for scheduled Event occurrences.

This capability is distinct from Event schedule/template/recurrence configuration because it has its own membership state, concurrency rules, and downstream contracts.

## 2. Scope and non-scope

In scope:

- registration-window eligibility;
- registered/waitlisted/cancelled state;
- capacity enforcement and oldest-waiter promotion;
- duplicate join idempotency;
- coordinator attendance recording as `attended`/`no-show`; and
- facts consumed by Notifications and Contributions.

Out of scope:

- Event recurrence/template generation;
- Rally-specific participation;
- notification delivery state; and
- Contribution record ownership.

## 3. Model and state

A registration associates one active same-Alliance membership with an Event/occurrence participation state.

Current participation states are:

- registered;
- waitlisted; and
- cancelled.

Event attendance is a separate Events-owned fact recorded as `attended` or `no-show` for the relevant registration/occurrence workflow.

## 4. Invariants

1. Registrations and attendance are Alliance scoped.
2. A membership does not receive duplicate active registrations for the same logical Event participation.
3. Capacity is enforced transactionally.
4. A full Event waitlists rather than overbooks.
5. Cancelling a registered place promotes the oldest eligible waiter when capacity becomes available.
6. Attendance remains Events-owned.
7. Rally participation does not replace Event attendance.
8. Notifications rechecks current registration eligibility before due reminder queueing.
9. Contributions may consume attended facts but may not mutate them.

## 5. Workflows

### Join Event

An eligible active member requests registration while the registration window is open. If capacity is available, the registration becomes registered; otherwise it becomes waitlisted.

A repeated equivalent join request does not create another registration.

### Cancel

The member cancels the active registration. If a registered place is freed, the oldest eligible waitlisted registration is promoted within the supported transactional workflow.

### Record attendance

An authorized coordinator records `attended` or `no-show`. Attendance mutation is tenant bound, permission protected, and attributable.

### Downstream consumption

Notifications reads registered/waitlisted eligibility for reminder materialization and rechecks it before queueing. Contributions reads attended facts for deterministic reconciliation.

## 6. Authorization, tenancy and privacy

Member registration/cancellation requires authenticated, verified active-Alliance context and ordinary Event access. Attendance/coordination mutations require `events.manage` and recent password confirmation where required by the HTTP boundary.

Submitted Event/occurrence/registration/membership identifiers are re-resolved beneath the active Alliance.

## 7. Persistence and query semantics

Events owns registration, waitlist/cancellation, capacity-related state, and attendance facts.

Queries for a member or coordinator begin from the active Alliance and Event/occurrence context. Attendance consumers in other domains use supported Events facts rather than reaching through to redefine the lifecycle.

## 8. Events, integrations and background processing

Registration and attendance transitions may create audit/outbox evidence where required.

Notifications owns reminder delivery state; Contributions owns materialized Contribution records; Integrations may expose bounded Event occurrence representation but does not own registrations/attendance.

## 9. Failure, idempotency and concurrency

- Duplicate join requests are idempotent.
- Capacity mutation is transactional and must not oversubscribe a bounded Event.
- Concurrent cancellation/promotion must not promote multiple waiters into one released place.
- Closed registration windows fail eligibility checks.
- Cross-tenant IDs fail closed.
- Recording attendance does not fabricate a registration belonging to another tenant.

## 10. Operations and observability

Useful diagnosis dimensions include registration state, capacity, waitlist ordering, registration window, attendance state, active membership, and downstream reminder/reconciliation state.

Do not fix waitlist or attendance problems by direct database editing; repair the source workflow and rerun supported downstream reconciliation where applicable.

## 11. Tests and validation

Tests should cover:

- registration-window rules;
- duplicate join idempotency;
- capacity and waitlisting;
- concurrent capacity protection;
- cancellation and oldest-waiter promotion;
- attendance authorization/state;
- tenant isolation; and
- Notifications/Contributions ownership boundaries.

## 12. Related documentation

- [Events domain](README.md)
- [Notifications](../notifications/README.md)
- [Contributions](../contributions/README.md)
- [Rallies](../rallies/README.md)
- [Alliances](../alliances/README.md)
