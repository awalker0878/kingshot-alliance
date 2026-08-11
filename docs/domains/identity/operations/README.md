# Identity operations profile

[← Identity domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Identity  
**Code owner:** `app/Domain/Identity`  
**Primary operational boundary:** account authentication, verification, password/session assurance, TOTP MFA and recovery state

## 1. Operational purpose and runtime shape

Identity owns synchronous account/authentication flows. Hosted sessions use Redis; durable account/MFA state is PostgreSQL-backed and encryption-sensitive. Identity has no dedicated scheduler or queue worker in the accepted runtime.

## 2. Persistent state and ownership

Durable state includes User account/security metadata, verification state, protected TOTP configuration and one-time recovery material. Session state is runtime state rather than durable business history. Membership and tenant permissions remain outside Identity.

## 3. Configuration and runtime dependencies

Critical dependencies include PostgreSQL, Redis/session configuration, HTTPS/cookie posture, `APP_KEY`, and mail configuration for supported verification/recovery messaging. `APP_KEY` is recovery-critical because encrypted application state can become unreadable if the key is lost.

## 4. Normal flow and background processing

Authentication, verification, password confirmation, MFA enrollment/challenge and recovery-code consumption occur in request flows. No background process should silently enroll/reset MFA or recreate recovery codes.

## 5. Health, observability and diagnostics

Use request/trace IDs, safe account IDs/email-normalized context, verification/MFA status and authentication outcome logs without secret values. Readiness covers PostgreSQL/cache but not real SMTP delivery or recipient mailbox behavior.

## 6. Failure modes and diagnosis

Common failures include Redis/session loss, PostgreSQL failure, invalid/stale password confirmation, verification-mail delivery problems, TOTP clock/input failure, exhausted recovery codes, or encryption-key mismatch/loss.

## 7. Recovery, replay and reconciliation

Restore database/session/mail dependencies and use supported user security flows. Session loss may require reauthentication rather than state repair. Never retrieve/reset MFA secrets or mark verification successful through direct database edits as a routine recovery path.

## 8. Backup, restore, migration and rollback

Identity recovery requires PostgreSQL plus the correct `APP_KEY` and runtime secret configuration. A database backup without the encryption key may not restore usable encrypted MFA/application state. After restore verify representative login, session, verification and MFA challenge behavior with approved test accounts.

## 9. Capacity, query and performance boundaries

Authentication endpoints are rate-limited and request-bounded. Redis/session and PostgreSQL connection capacity are shared production concerns; repository tests do not define production authentication throughput.

## 10. External-service degradation

SMTP degradation can delay verification/recovery messaging while existing authenticated users may continue according to current session/account state. Redis loss affects sessions broadly. No operator should weaken verification/MFA requirements merely to route around an external dependency failure.

## 11. Safe operator actions and stop conditions

Safe actions are restore dependencies, verify key consistency, have users reauthenticate, and use supported account-security recovery. Stop if a proposed repair exposes TOTP/recovery secrets, disables assurance globally, changes verification/MFA state directly, or loses the required `APP_KEY` recovery material.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, request/trace IDs, safe User IDs, outcome/status timestamps, dependency incident IDs and key-version/secret-manager identifiers—not secret values. No focused P3 Identity runbook is required. See [configuration](../../../operations/configuration-reference.md), [incident response](../../../operations/runbooks/incident-response.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Identity security profile](../security/README.md).
