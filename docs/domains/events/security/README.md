# Events security profile

[← Events domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Events  
**Code owner:** `app/Domain/Events`  
**Primary security boundary:** authenticated tenant-scoped Event access with privileged coordination and concurrency-safe registration/attendance integrity

## 1. Security purpose and scope

Events protects Alliance-private schedules, registrations, waitlists, attendance, instructions, and authenticated exports while preserving schedule/capacity integrity under concurrent member/coordinator actions.

This profile covers one-time/recurring Events, occurrences, member access, registration/waitlist/attendance, authenticated CSV/ICS output, and Event facts consumed by Notifications, Contributions, Rallies, and Integrations.

## 2. Assets and sensitive data

Assets include Event configuration/instructions, occurrence timing, registration/waitlist state, attendance, member associations, and authenticated export data. These are tenant-private unless a separate approved representation intentionally exposes a subset.

Attendance/registration reveal member participation and must not be exposed through public routes or generic logs/outbox payloads.

## 3. Actors, authentication and authorization

Member Event surfaces require authenticated verified active-Alliance context plus `alliance.view`. Coordination/mutation requires `events.manage` and recent password confirmation where applicable.

Submitted Event/occurrence/registration/membership identifiers are re-resolved beneath the active Alliance.

## 4. Tenant and privacy boundaries

All Event-owned records are Alliance scoped. Authenticated CSV/ICS exports are generated only for the active Alliance; the current ICS contract does not issue long-lived public bearer subscription tokens.

Downstream consumers receive only the Event facts their explicit contract requires. Events remains authoritative for attendance and does not expose unrestricted member history through generic integration behavior.

## 5. Trust boundaries and data flows

Material flows include authenticated member/coordinator browser → Event surfaces, scheduler → occurrence materialization, member registration/cancellation → serialized capacity/waitlist state, coordinator → attendance recording, authenticated request → CSV/ICS generation, and Event facts → Notifications/Contributions/Rallies/Integrations.

## 6. Threats, abuse cases and controls

Threats include cross-Alliance IDOR, unauthorized coordination, over-capacity races, duplicate waitlist promotion, attendance tampering, recurrence/DST corruption, export disclosure, stale member eligibility, and internal Event events becoming public by accident.

Controls include tenant-scoped re-resolution, `events.manage`, row locking/serialization around capacity transitions, explicit attendance ownership, canonical local-time→UTC recurrence handling, authenticated bounded exports, and Integrations-owned external eligibility.

## 7. Integrity, concurrency and idempotency

Registration/cancellation/promotion operations use persistence constraints and locking where necessary so capacity is not silently exceeded and one seat is not promoted multiple times. Repeated actions settle into the documented state rather than duplicating logical registration.

Occurrence materialization preserves Alliance-local wall-clock intent while persisting concrete UTC timestamps. Notifications and Contributions consume source facts idempotently rather than rewriting Events state.

## 8. Secrets and credential handling

Events owns no authentication, API, webhook, or calendar-subscription secret. Current ICS/CSV access depends on authenticated tenant context rather than long-lived bearer URLs.

Logs/audit/outbox should contain safe identifiers/state changes, not private instructions or full registration/attendance payloads unless explicitly required.

## 9. Destructive operations, retention and deletion

Normal cancellation/attendance changes preserve the supported record lifecycle; they should not be implemented as ad hoc destructive database edits. Broader account/Alliance retention/deletion is coordinated by Platform while preserving legitimate Event history/evidence constraints.

No public calendar token revocation lifecycle exists because long-lived public subscriptions are not implemented.

## 10. Auditability, observability and evidence

Privileged Event mutations and relevant transitions create attributable evidence where required. Operators diagnose schedule/occurrence state separately from registration/waitlist/attendance and from Notifications delivery state.

Tests protect recurrence/DST, tenant isolation, member/manager authorization, registration concurrency, attendance integrity, authenticated exports, and cross-domain ownership. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Calendar/export consumers can copy authorized data after download; repository authorization cannot control external handling after disclosure. Operational privacy therefore depends on limiting export scope and audience.

Events does not issue public long-lived calendar tokens, own reminder delivery, expose a public write API, own Rally participation, or allow cross-tenant registration/attendance operations.

## 12. Focused reviews and related documentation

No focused living Events security review is required at current complexity; concurrency/security behavior is covered by this profile and the living registration/attendance contract.

- [Event registration and attendance](../registration-and-attendance.md)
- [Notifications security profile](../../notifications/security/README.md)
- [Contributions security profile](../../contributions/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 3 threat model](../../../security/phase-3-threat-model.md)
