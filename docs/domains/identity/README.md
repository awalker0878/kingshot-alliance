# Identity domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Identity`  
**Primary authorization boundary:** authenticated global User identity with verified-email/password/MFA assurance where required

## 1. Purpose and ownership

Identity owns the global User account and authentication/account-security lifecycle: registration/sign-in/out, email verification, password/session security, profile/account assurance, and MFA/recovery assurance.

Identity does not own Alliance tenancy, membership, Alliance roles/permissions, Platform grants, or neutral game identity.

## 2. Scope

In scope: global User identity, authentication/account recovery, verified email, password/session assurance, profile/account security, TOTP MFA, and recovery codes.

Out of scope: active Alliance selection/context, Memberships/Authorization, Platform-administrator grant ownership, and Kingdoms game identity.

## 3. Domain model

`User` is one global application identity regardless of Alliance membership count. Verified email and recent password confirmation are reusable Identity assurance contracts consumed by sensitive workflows.

The secret-bearing MFA/recovery lifecycle is documented in [MFA and recovery](mfa-and-recovery.md).

## 4. Core invariants

1. User identity is global and never duplicated per Alliance.
2. Identity alone never grants Alliance access.
3. Verified email gates workflows that require verified identity.
4. Recent password confirmation strengthens sensitive operations but never replaces owning-domain authorization.
5. MFA strengthens assurance but never replaces tenant membership/permission or Platform grants.
6. Authentication/MFA/recovery secrets never belong in logs/audit/outbox/business payloads.

## 5. Lifecycles and workflows

Users register/sign in, verify email, manage password/session/profile security, and recover account access through supported Identity flows.

Sensitive domain operations may require recent password confirmation. MFA enrollment/challenge/recovery behavior is defined in [MFA and recovery](mfa-and-recovery.md).

## 6. Authorization and tenancy

Identity authenticates **who** the User is. Alliance access additionally requires Alliances tenant context, active Memberships relationship, and Authorization permission. Platform administration additionally requires the separate Platform grant and required assurance.

## 7. Cross-domain contracts

Consumes Platform deletion/legal-hold orchestration and shared session/framework infrastructure.

Exposes authenticated User identity, verified-email state, recent password-confirmation assurance, and MFA assurance without exposing underlying secrets.

## 8. Persistence and data ownership

Identity owns User account/authentication/profile/security state and MFA/recovery material. Alliance membership/role/business state remains in owning domains. Platform may orchestrate anonymization/deletion while respecting Identity ownership.

## 9. Events, outbox and integrations

Security-relevant Identity changes may create safe audit evidence. Internal evidence does not imply a public identity API/OAuth contract.

## 10. HTTP, UI and API surfaces

Identity owns first-party registration, sign-in/out, verification, password reset/confirmation, profile/security, and MFA administration surfaces. Feature routes merely consume Identity assurance.

## 11. Background processing

Identity authorization/assurance is primarily request driven. It does not provide a background process that grants tenant access by inference.

## 12. Failure, idempotency and concurrency

Unverified/stale-assurance/MFA failures fail closed for workflows that require them. Supported recovery flows are used instead of direct persistence edits. One-time MFA recovery concurrency is specified in [mfa-and-recovery.md](mfa-and-recovery.md).

## 13. Security and privacy

Passwords, session secrets, MFA secrets, and recovery material are sensitive Identity state. Other domains consume assurance outcomes, not secret values.

## 14. Observability and operations

Diagnose authentication, verification, password-confirmation, session, and MFA failures separately from tenant/membership/permission failures. See [Security baseline](../../security/security-baseline.md).

## 15. Testing and architecture enforcement

Tests protect registration/authentication/recovery, verification/password-confirmation gates, session security, MFA/recovery behavior, and global-Identity versus tenant-authorization separation.

## 16. Explicit non-capabilities

Identity does not own Alliance membership, assign Alliance roles/permissions, establish active tenant context, grant Platform administration, or own game identity.

## 17. Capability documents

- [MFA and recovery](mfa-and-recovery.md) — TOTP enrollment/challenge, one-time recovery codes, secret handling, and assurance boundaries.

## 18. Related documentation

- [Alliances](../alliances/README.md)
- [Memberships](../memberships/README.md)
- [Authorization](../authorization/README.md)
- [Platform](../platform/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Identity/README.md`](../../../app/Domain/Identity/README.md)
