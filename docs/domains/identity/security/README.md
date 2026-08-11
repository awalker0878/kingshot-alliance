# Identity security profile

[← Identity domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Identity  
**Code owner:** `app/Domain/Identity`  
**Primary security boundary:** global User authentication and assurance without conferring tenant or Platform authority

## 1. Security purpose and scope

Identity protects global account authentication, verification, password/session assurance, profile security, and MFA/recovery state. Its central boundary is that proving who a User is never by itself grants Alliance membership, feature permission, or Platform administration.

The secret-bearing MFA/recovery lifecycle is reviewed in [MFA and recovery security review](mfa-and-recovery-security-review.md).

## 2. Assets and sensitive data

Assets include password hashes, verified-email state, authenticated sessions, remember/session security state, password-confirmation assurance, MFA enrollment state, encrypted TOTP secret, and hashed/encrypted recovery-code material.

Email/profile data is personal account data. Passwords, session credentials, MFA secrets, recovery material, application keys, and reset/bearer material are security secrets and must never enter routine business payloads/logs/audit/outbox records.

## 3. Actors, authentication and authorization

Unauthenticated users interact with registration, login, verification, recovery, and MFA challenge surfaces. Authenticated Users may manage supported profile/security state.

Owning feature domains consume verified-email, recent-password, or MFA assurance only as additional identity confidence; they must still enforce tenant membership/permission or Platform grants.

## 4. Tenant and privacy boundaries

`User` is global and is not duplicated per Alliance. Identity data is not tenant-owned merely because a User belongs to one or more Alliances.

Feature domains must not use Identity account existence or MFA state to infer tenant access. Platform deletion/anonymization may orchestrate account lifecycle while preserving domain ownership and evidence rules.

## 5. Trust boundaries and data flows

Material boundaries include anonymous browser → authentication/recovery endpoints, authenticated browser → profile/security endpoints, password authentication → session establishment, MFA challenge → strengthened authenticated session, and owning domains → assurance outcomes.

Persistence uses protected application/database/session mechanisms; secret values must not cross into generic audit/outbox/log transport.

## 6. Threats, abuse cases and controls

Threats include account enumeration, credential stuffing, session fixation, stale sessions after password change/reset, MFA downgrade, TOTP/recovery replay, recovery-code reuse, secret serialization/logging, and using successful authentication as tenant authorization.

Controls include normalized identity inputs, generic recovery responses, named rate limits, session regeneration, session/token invalidation on applicable password changes, verified-email/password gates, encrypted MFA secret, hashed one-time recovery codes, challenge throttling, and strict authorization separation.

## 7. Integrity, concurrency and idempotency

Security-state transitions fail closed when assurance is missing or stale. MFA enrollment does not silently overwrite an already-confirmed factor. Recovery-code consumption is one-time and concurrency safe so one code cannot be accepted twice.

Repeated recovery/login failure does not mutate business authorization state; successful authentication establishes identity only.

## 8. Secrets and credential handling

Passwords are handled by framework-configured hashing and never persisted plaintext. MFA secrets use protected encrypted storage and are excluded from normal serialization. Recovery codes are displayed only at creation/regeneration and persisted only in protected non-replayable form.

Other domains consume boolean/time-bound assurance outcomes, never raw MFA/recovery material.

## 9. Destructive operations, retention and deletion

Password/reset/account-security operations may invalidate sessions/tokens as part of supported lifecycle. Account deletion/anonymization is coordinated with Platform and owning business domains so personal identifiers are minimized while legitimate retained history/evidence remains consistent.

Direct database edits are not an accepted recovery or account-deletion mechanism.

## 10. Auditability, observability and evidence

Security-relevant Identity changes are attributable using safe metadata. Operators distinguish authentication, verification, password-confirmation, session, and MFA failures from membership/permission/tenant failures.

Tests cover registration/login/recovery, enumeration resistance, session regeneration/invalidation, assurance gates, MFA/recovery behavior, and separation from tenant/Platform authority. Shared controls are in [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Application controls cannot prove external email-account security, user device integrity, enterprise identity-provider policy, or managed secret-store configuration. WebAuthn/external IdP are not part of the current accepted Identity capability.

Identity does not grant Alliance access, assign Alliance roles, establish active tenant context, grant Platform administration, or expose underlying authentication secrets to feature domains.

## 12. Focused reviews and related documentation

- [MFA and recovery security review](mfa-and-recovery-security-review.md)
- [MFA and recovery contract](../mfa-and-recovery.md)
- [Alliances security profile](../../alliances/security/README.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
