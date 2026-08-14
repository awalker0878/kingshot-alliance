# Notifications security profile

[← Notifications domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary security boundary:** exact Event scope plus Player-specific recipient identity derived from authoritative source-domain state

## 1. Security purpose and scope

Notifications protects Event reminder and scheduled-report coordination from cross-scope disclosure, stale recipient state, duplicate logical work, payload over-collection, and retry amplification.

## 2. Assets and sensitive data

Assets include reminder rules, occurrence/Player delivery identity, recipient User ID, due/delivery state, report coordination identity, and minimized outbox payloads. Operational instructions, unrelated profile data, credentials, and private narrative do not belong in notification payloads.

## 3. Actors, authentication and authorization

Events authorizes reminder-rule configuration against the exact Player, Alliance, or Kingdom target. Notifications never derives manager authority from the active Player persona. Background scheduler work acts only on persisted, previously authorized rule configuration and current source eligibility.

## 4. Tenant and privacy boundaries

Event reminder deliveries are keyed by `player_id`; `recipient_user_id` is resolved from authoritative `players.user_id`. Multiple Players owned by one User remain separate reminder identities. Alliance and Kingdom audience resolution uses exact current Event eligibility rather than process-global tenant state.

## 5. Trust boundaries and data flows

The reminder flow is Event/Player source state → due-time audience resolution → Notifications delivery state → Platform outbox → first-party in-app completion. Redis/queue execution and the active Player UI context are not sources of authorization truth.

## 6. Threats, abuse cases and controls

Threats include forged Player identity, cross-Kingdom or cross-Alliance audience expansion, stale roster state, duplicate reminders, scheduler replay, payload overexposure, and retry storms. Controls include authoritative Player ownership, exact scope checks, current eligibility revalidation, deterministic delivery keys, unique database constraints, bounded scheduler work, and minimized outbox payloads.

## 7. Integrity, concurrency and idempotency

A reminder is unique by rule + occurrence + Player. Queueing and outbox identities are deterministic, making repeated scheduler execution safe. The publisher listener advances only matching reminder deliveries and ignores unrelated outbox events.

## 8. Secrets and credential handling

Notifications owns no provider credentials. Delivery/outbox/log payloads must not contain passwords, MFA/recovery material, API credentials, signing secrets, or unrelated private data.

## 9. Destructive operations, retention and deletion

Reminder history must not be deleted to rewrite whether a reminder was queued or sent. Event cancellation changes source eligibility/state; retention and account anonymization are handled through supported lifecycle workflows.

## 10. Auditability, observability and evidence

Operational evidence distinguishes rule, Event/occurrence, Player, recipient User, due time, delivery state, scope partition, outbox state, and completion timestamp. Tests protect cross-scope isolation, multi-Player identity, scheduler idempotency, and source eligibility rechecks.

## 11. Residual risks and explicit non-capabilities

`sent` represents durable in-app outbox completion, not third-party email/SMS/push delivery. Notifications does not own generic external messaging or webhook transport.

## 12. Focused reviews and related documentation

- [Event reminders](../event-reminders.md)
- [Events security profile](../../events/security/README.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
