# Events testing and evidence

[← Events domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Events  
**Code owner:** `app/Domain/Events`  
**Primary validation boundary:** Recurrence/time-zone semantics, registration/waitlist/attendance concurrency, Event disclosure, and downstream reminder/reporting source facts  
**P5 evidence decision:** Living suite map with Phase 3 accessibility/migration/concurrency evidence reused

## 1. Critical claims and validation ownership

Events validation must prove recurrence/time-zone correctness, capacity-safe registration/waitlisting, cancellation/promotion, attendance/no-show state, member/coordinator disclosure, Event→Notifications/Contributions/Rallies boundaries, and authenticated CSV/iCalendar semantics.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`. Unit evidence is especially material for recurrence/time computations; Integration/Feature evidence owns registration/concurrency and first-party workflows.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Events ownership of schedules/occurrences/registration/attendance while Rallies owns Rally coordination, Notifications owns reminder delivery coordination, Contributions owns derived contribution records, and Integrations owns external machine representation.

P4/P5 documentation guards also protect the Events/Rallies adapter distinction.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers active-Alliance reads, `events.manage` coordinator mutations, recent password confirmation, cross-Alliance occurrence/registration denial, private registration/reminder/Rally data separation and authenticated export boundaries.

[Events security](../security/README.md) defines the security claims that regression evidence must preserve.

## 5. Feature, interface and integration validation

Feature tests cover calendar/detail/registration/cancellation/management and authenticated export behavior. Integration evidence covers Notifications reminder source facts, Contributions attendance reconciliation, Rallies adapters and outbox behavior.

[Calendar exports](../interfaces/calendar-exports.md) and [Event registration and attendance](../registration-and-attendance.md) are the primary interface/lifecycle contracts.

## 6. Idempotency, concurrency and asynchronous validation

Phase 3 acceptance explicitly tested PostgreSQL row-lock capacity enforcement, duplicate registration idempotency, cancellation and oldest-waitlist promotion. Reminder materialization/queueing uses deterministic identity under Notifications ownership.

Event mutations must not be replayed merely because downstream outbox/notification work retries.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 3 exit report](../../../product/phase-3-exit-report.md) records `EventMigrationRollbackTest`, protected staging, backup/restore and migration evidence. Current CI still runs clean forward migrations and database recovery.

Recovery/scheduler detail is in [Events operations](../operations/README.md) and Notifications operations.

## 8. Performance, query and capacity evidence

Performance evidence is applicable to bounded Event/calendar/coordination query behavior and explicit capacity/concurrency constraints. Capacity correctness is primarily a locking/invariant claim, not a latency SLA.

No universal Event request-time or throughput SLA is accepted.

## 9. Accessibility and frontend evidence

[Phase 3 accessibility review](../../../product/phase-3-accessibility.md) and `EventAccessibilityGuardTest` historically protect main landmarks, native/labeled controls, no raw `v-html`, no positive `tabindex`, explicit button types, textual status and responsive/time-zone presentation.

Current `npm run check` is frontend quality evidence, not a replacement for deployment-specific device/screen-reader/branding checks.

## 10. Historical accepted evidence

Primary evidence is [Phase 3 exit report](../../../product/phase-3-exit-report.md), with validated technical head `ad1cbf3228f86dd915dbc82466d441f7aca0c475` and protected DR `31187575970`, CodeQL `31187578967`, CI `31187575503`.

Later P4 calendar-interface documentation is a living contract, not a historical acceptance replacement.

## 11. Evidence identity, retention and supersession

Phase 3 test names/counts/check IDs stay historical. Current Events validation follows current tests and this profile.

Future acceptance evidence follows [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

No anonymous/public long-lived calendar token, Event import, automated game ingestion, or Events-owned reminder-delivery state is accepted. No numeric latency SLA is inferred from current tests.

Related documentation:

- [Events domain](../README.md)
- [Registration and attendance](../registration-and-attendance.md)
- [Events security](../security/README.md)
- [Events operations](../operations/README.md)
- [Events interfaces](../interfaces/README.md)
- [Rallies testing](../../rallies/testing/README.md)
- [Notifications testing](../../notifications/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
