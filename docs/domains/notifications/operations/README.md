# Notifications operations profile

[← Notifications domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary operational boundary:** recurring reminder/report coordination with deterministic durable identities and transactional-outbox handoff

## 1. Operational purpose and runtime shape

Notifications owns recurring coordination for Event reminders and scheduled Contribution reports. Current work is primarily scheduler-driven and PostgreSQL/outbox-backed rather than a general external notification queue.

## 2. Persistent state and ownership

Durable state includes Player-specific reminder delivery records and scheduled-report coordination/run state owned by Notifications. Events supplies occurrence/registration truth; Contributions supplies report source/provenance; Platform owns the transactional outbox.

## 3. Configuration and runtime dependencies

Notifications depends on PostgreSQL, scheduler continuity and the shared outbox publisher. Redis/Horizon may affect downstream consumers but the current reminder/report coordination commands themselves run in the scheduler process.

## 4. Normal flow and background processing

Every minute the scheduler runs:

- `events:queue-reminders --limit=100` to queue due reminders through the outbox; and
- `contributions:queue-reports --limit=50` to resolve/queue due scheduled report work.

All use `onOneServer` and overlap protection; durable logical identities prevent routine duplicates.

## 5. Health, observability and diagnostics

Inspect scheduler process/list, eligible Event Players/occurrences or due Contribution schedules, reminder/report row status, deterministic due/version identity, related outbox row, audit/request correlation where applicable, and oldest pending/due age.

## 6. Failure modes and diagnosis

Typical failures are scheduler stoppage, PostgreSQL failure/lock pressure, source Event/Contribution state no longer eligible, missing delivery row, pending row without outbox handoff, repeatedly failing outbox consumer or stale source configuration.

## 7. Recovery, replay and reconciliation

Restore scheduler/database/outbox health and rerun the single bounded owning command. Deterministic identities cause existing logical reminder/report runs to be reused instead of duplicated. Do not recreate source registrations/contributions simply to trigger background work.

## 8. Backup, restore, migration and rollback

Notifications state is PostgreSQL-backed and must be restored consistently with Events, Contributions and Platform outbox state. After recovery compare source eligibility/due state with reminder/report/outbox rows before catch-up. Application rollback does not undo already emitted external side effects.

## 9. Capacity, query and performance boundaries

Default catch-up limits are 100/50 for Event reminders and Contribution reports. Raise only within implemented bounds after checking database/outbox capacity. Repository fixtures are regression evidence, not production notification throughput commitments.

## 10. External-service degradation

Notifications does not itself define a generic external transport. Downstream webhook/email/integration behavior belongs to Integrations or configured mail paths. Source coordination should remain durable while downstream dependencies recover.

## 11. Safe operator actions and stop conditions

Safe actions are inspect source/durable state, restore scheduler/database, run one bounded catch-up command and verify exact logical rows/outbox intent. Stop if recovery would require deleting rows to defeat idempotency, fabricating sent state, changing Event/Contribution history solely to retrigger, or replaying downstream external side effects without owner review.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, command/cadence/limit, source Event/Contribution IDs, reminder/report/outbox IDs, due/version identity, backlog counts before/after and validation outcome. See [Scheduled delivery](scheduled-delivery.md), [background processing](../../../operations/background-processing.md), [Events operations](../../events/operations/README.md), [Contributions operations](../../contributions/operations/README.md), and the [Notifications security profile](../security/README.md).
