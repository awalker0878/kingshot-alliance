# Accounts context

Status: Current — Architecture V3; Accounts expansion selected

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
└── MultiFactorAuthentication/
```

The selected Accounts expansion may add capability-owned session/security-activity support while preserving these boundaries.

- **Identity** owns User identity, explicit primary authentication type and durable external provider identity metadata.
- **Registration** owns account creation under invitation/registration policy.
- **Authentication** owns password/Google sign-in, sign-out, session establishment, recent-authentication proof and authentication-type routing.
- **Credentials** owns local password and password-recovery lifecycle for `password` accounts only.
- **EmailVerification** owns verification flows/state and verified password-account email-change workflow.
- **Profile** owns account profile changes and account-security presentation/orchestration that does not take ownership from other capabilities.
- **MultiFactorAuthentication** owns Kingshot Alliance TOTP challenge and recovery codes as a second factor for either primary authentication type.

## Primary authentication invariant

Every active User has exactly one explicit primary authentication type: `password` or `google`. Hybrid primary authentication is prohibited. Google accounts have no usable local password and cannot use password recovery. Password accounts cannot silently acquire Google as another credential. Google `provider + sub` is authoritative for established Google accounts.

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