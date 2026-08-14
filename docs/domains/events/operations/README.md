# Events operations profile

[← Events domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Events  
**Code owner:** `app/Domain/Events`  
**Primary operational boundary:** event/occurrence recurrence plus registration, waitlist and attendance integrity used by reminders and contribution reconciliation

## 1. Operational purpose and runtime shape

Events owns synchronous event/occurrence/scoped scheduling, occurrence, participation, phase/poll, roster, objective, and result workflows. Event reminders are background-coordinated by Notifications; Events supplies authoritative occurrence/registration state.

## 2. Persistent state and ownership

Durable PostgreSQL state includes the Event Type catalogue, Events, occurrences, Player participation, phases/polls, rosters, objectives, and results. Contributions may derive contribution facts from accepted Event state; Notifications owns reminder delivery coordination.

## 3. Configuration and runtime dependencies

Core Events depends on PostgreSQL and tenant context. Reminder behavior additionally depends on the shared scheduler/outbox through Notifications. Authenticated CSV/ICS export remains request-path behavior rather than a separate external service.

## 4. Normal flow and background processing

Authorized managers create/update Event and occurrence configuration; eligible Players respond, register, vote, confirm assignments, and participate through durable `player_id`. Capacity/waitlist transitions are concurrency-safe under supported transactions. Notifications periodically materializes/queues reminders from eligible Event state; Events itself has no dedicated queue worker.

## 5. Health, observability and diagnostics

Inspect Event/occurrence lifecycle, exact scope target, active Player context when relevant, capacity, participation state, operational assignments, and audit/request correlation. For reminder issues also inspect the Notifications delivery row and scheduler/outbox state rather than treating the Event row as the delivery record.

## 6. Failure modes and diagnosis

Typical issues are stale recurrence/occurrence assumptions, full capacity/waitlist contention, invalid attendance transition, cross-scope or cross-target identifier use, missing eligible reminder source state, or PostgreSQL failure.

## 7. Recovery, replay and reconciliation

Retry supported registration/attendance operations only after current occurrence/capacity state is re-read. Do not manually promote waitlisted Players or rewrite attendance to force a downstream report/reminder. Reminder catch-up belongs to Notifications; contribution reconciliation belongs to Contributions.

## 8. Backup, restore, migration and rollback

Events state is PostgreSQL-backed and restored with dependent registration/attendance/reminder/contribution records. After recovery verify representative future occurrences, capacity totals, registrations/waitlist ordering and attendance before running downstream reconciliation/catch-up.

## 9. Capacity, query and performance boundaries

Capacity is a business invariant separate from infrastructure capacity. Queries must remain scope/target/occurrence bounded and batch Player facts rather than query per Player or per occurrence. Concurrency tests protect correctness; repository volumes are not production attendance/registration load claims.

## 10. External-service degradation

Events has no direct external provider dependency. Scheduler/Redis/outbox degradation can delay reminders without changing Event source truth. Export clients may fail independently without requiring Event-state mutation.

## 11. Safe operator actions and stop conditions

Safe actions are inspect/retry supported workflows, restore PostgreSQL, and invoke owning downstream recovery procedures. Stop if recovery requires bypassing capacity, reordering waitlists by direct SQL, crossing tenants, or rewriting attendance/history solely to trigger downstream work.

## 12. Evidence, focused runbooks and related documentation

Retain event/occurrence/registration IDs, capacity/status values, timestamps, request/trace IDs and release SHA. See [Notifications scheduled delivery](../../notifications/operations/scheduled-delivery.md), [background processing](../../../operations/background-processing.md), and the [Events security profile](../security/README.md).
