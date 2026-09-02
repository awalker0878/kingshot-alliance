# Accounts context

Status: Current — Architecture V3; Accounts expansion implemented

Implementation target: `app/Contexts/Accounts`

Accounts owns global User identity and account security. It answers **which account is operating Kingshot Alliance**, not which game persona has authority. Kingshot Alliance is a third-party alliance-management application; Accounts does not authenticate an official Kingshot game account.

## Capabilities

```text
Accounts/
├── Identity/
├── Registration/
├── Authentication/
├── Credentials/
├── EmailVerification/
├── Profile/
├── Security/
└── MultiFactorAuthentication/
```

- **Identity** owns User identity, explicit primary authentication type, durable external provider identity metadata and account-side anonymization/credential invalidation.
- **Registration** owns account creation under invitation/registration policy.
- **Authentication** owns password/Google sign-in, sign-out, privacy-conscious account-session inventory, session revocation, recent-authentication proof and authentication-type routing.
- **Credentials** owns local password and password-recovery lifecycle for `password` accounts only.
- **EmailVerification** owns signed/time-limited verification notification/link behavior, including verification of a pending password-account email destination.
- **Profile** owns profile changes and account-security presentation/orchestration, including pending password-account email-change state, without taking ownership from other capabilities.
- **Security** owns account-scoped Security Activity projection and the meaning of account-security notification events. Communications remains the owner of outbound channel delivery, retry and preferences.
- **MultiFactorAuthentication** owns Kingshot Alliance TOTP challenge and recovery codes as a second factor for either primary authentication type.

Platform/DataGovernance retains the existing deletion request, cooling-off, cancellation and finalization coordination. Accounts supplies the account-side lifecycle effects that remove usable credentials/provider identity and invalidate authentication surfaces.

## Primary authentication invariant

Every active User has exactly one explicit primary authentication type: `password` or `google`. Hybrid primary authentication is prohibited. Google accounts have no usable local password and cannot use password recovery. Password accounts cannot silently acquire Google as another credential. Google `provider + sub` is authoritative for established Google accounts.

A verified Google provider-email change may refresh provider metadata and the Kingshot Alliance contact email when it does not collide with another User. It never changes the authoritative provider subject, links a password account, or changes authentication type.

See [ADR-0013](../../adr/0013-exclusive-account-authentication-types.md) and the [Accounts Expansion Program](../../../product/accounts-expansion.md).

## Fresh-schema rule

The Accounts expansion targets a fresh schema with no existing account data. Canonical create-schema migrations are updated directly. There is no backfill, compatibility shim, dual read/write or legacy account-classification path.

## Boundary

Accounts does not own Player game state, Alliance membership/rank, Kingdom roles or Operations/Intelligence permissions. Game-domain requests resolve a Player through `GameWorld/Players`.

External Actor Connections remain integration/reconciliation identity and never become Accounts authentication credentials.

Platform Administrator is a User-scoped Platform grant, not an Accounts game permission.

## Product-language boundary

User-facing authentication copy refers to a **Kingshot Alliance account/password/email**. It must not imply that Kingshot Alliance credentials are official Kingshot game credentials or that the application is publisher-operated.

## Physical rule

Accounts does not use context-root `Actions`, `Models`, `Services` or `Http` buckets in V3. Each implementation class belongs under the capability that owns the behavior.
