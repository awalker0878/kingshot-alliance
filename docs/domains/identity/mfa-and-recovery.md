# MFA and recovery

[← Identity domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Identity

## 1. Purpose

Defines TOTP multi-factor authentication and one-time recovery-code assurance for the global User account.

MFA strengthens Identity assurance for privileged workflows but never replaces Alliance membership, domain permission checks, or Platform-administrator grants.

## 2. Scope and non-scope

In scope:

- TOTP enrollment/confirmation/challenge state;
- one-time recovery-code generation/use/rotation;
- password-confirmation prerequisites for MFA administration;
- MFA assurance consumed by Platform administration and other sensitive workflows; and
- secret-handling boundaries.

Out of scope:

- Alliance RBAC;
- membership lifecycle;
- password reset/account recovery outside MFA-specific recovery codes; and
- third-party identity-provider/OAuth contracts.

## 3. Model and state

MFA configuration belongs to one global User. It includes the TOTP secret/configuration state and a set of one-time recovery codes managed as authentication secrets.

The accepted lifecycle distinguishes:

- MFA not enrolled;
- enrollment/configuration pending confirmation where applicable;
- MFA enabled/confirmed; and
- recovery-code consumption/rotation.

Recovery codes are one-time assurance material, not ordinary profile data.

## 4. Invariants

1. MFA state is global User identity state, not Alliance-scoped data.
2. MFA administration requires verified identity and recent password confirmation.
3. MFA success never grants an Alliance permission by itself.
4. Platform-administrator web access requires the separate Platform grant in addition to MFA and other Identity assurance.
5. TOTP secrets and recovery codes are secrets and never belong in logs, audit metadata, URLs, documentation, exports, or cross-domain payloads.
6. Recovery codes are one-time use.
7. Regenerating/rotating recovery codes invalidates prior recovery material according to the supported flow.
8. Failure to satisfy MFA fails closed for surfaces that require it.

## 5. Workflows

### Enroll MFA

The verified authenticated User reconfirms the password, initiates TOTP enrollment, stores the authenticator configuration securely, and completes the supported confirmation challenge before relying on MFA assurance.

### Authenticate with TOTP

A protected workflow requests the current TOTP challenge according to the application's authentication flow. Successful challenge establishes MFA assurance for that session/workflow boundary.

### Use recovery code

When TOTP is unavailable, a valid unused recovery code may satisfy the supported recovery challenge. The code is consumed and cannot be reused.

### Rotate recovery codes

The User uses the supported Identity security workflow with required assurance to generate replacement recovery material. Previous codes are no longer valid according to the rotation semantics.

## 6. Authorization, tenancy and privacy

MFA is Identity-owned global assurance. Other domains consume only the fact that the required assurance was satisfied; they do not read or persist the underlying TOTP secret/recovery material.

Alliance-scoped operations still require active Alliance context, active membership, and the owning permission. Cross-tenant Platform access still requires a Platform administrator grant.

## 7. Persistence and query semantics

Identity owns persisted MFA configuration and protected recovery-code state. Queries should expose only the minimum state required to determine enrollment/assurance status.

Plaintext secret/recovery material is never a general application projection.

## 8. Events, integrations and background processing

Security-relevant MFA administration may be audited with safe identifiers/action names only. Secrets are excluded from audit/outbox payloads.

No public MFA API, OAuth identity flow, or external secret synchronization contract is implied.

## 9. Failure, idempotency and concurrency

- Invalid TOTP/recovery challenge fails without falling back to Alliance role state.
- A consumed recovery code cannot be reused.
- Concurrent recovery-code use must not allow the same logical one-time code to succeed twice.
- Partial enrollment must not be treated as fully enabled assurance.
- Direct persistence edits are not an accepted recovery path.

## 10. Operations and observability

Operators should distinguish password-confirmation failure, MFA enrollment state, TOTP challenge failure, recovery-code exhaustion/use, and downstream permission denial.

Telemetry must never include secret values or recovery codes.

## 11. Tests and validation

Tests should cover:

- enrollment/confirmation;
- password-confirmation gate;
- successful/failed TOTP challenge;
- recovery-code one-time use;
- recovery-code rotation;
- secret exclusion from serialization/log/audit paths; and
- separation of MFA assurance from Alliance/Platform authorization.

## 12. Related documentation

- [Identity domain](README.md)
- [Authorization](../authorization/README.md)
- [Alliances](../alliances/README.md)
- [Platform](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
