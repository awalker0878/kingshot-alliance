# Accounts context

Status: Current — Architecture V3; Sign-In Methods & Credential Evolution current complete capability

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

- **Identity** owns the permanent User identity, durable external-provider identities, provider metadata, and account-side anonymization/credential invalidation.
- **Registration** owns Kingshot Alliance User creation under invitation/registration policy. Password and Google registration create the same User identity and differ only in the attached sign-in method.
- **Authentication** owns password/Google/passkey sign-in, explicit Google operation intent, passkey ceremonies, sign-out, privacy-conscious account-session inventory, session revocation, central sign-in-method policy and method-agnostic recent-authentication proof.
- **Credentials** owns local password establishment/change/removal and password-recovery lifecycle when a usable password exists.
- **EmailVerification** owns signed/time-limited verification notification/link behavior, including verification of a pending account-email destination.
- **Profile** owns profile changes and account-security presentation/orchestration, including verified account-email change and the Sign-in methods Security Center surface, without taking ownership from other capabilities.
- **Security** owns account-scoped Security Activity projection and the meaning of account-security notification events. Communications remains the owner of outbound channel delivery, retry and preferences.
- **MultiFactorAuthentication** owns Kingshot Alliance TOTP challenge and recovery codes as an optional second factor. TOTP recovery codes recover TOTP only; they are not general account-recovery credentials.

Platform/DataGovernance retains deletion request, cooling-off, cancellation and finalization coordination. Accounts supplies account-side lifecycle effects that remove password/reset material, provider identities, passkeys, MFA material and sessions and invalidate authentication surfaces.

## Sign-in-method invariant

A Kingshot Alliance User is independent from any one credential. An active User must always retain at least one usable sign-in method:

```text
Kingshot Alliance User
├── Password 0..1
├── Google 0..1
└── Passkeys 0..N
```

There is no `authentication_type` or equivalent primary-method discriminator. Availability is derived from actual credential state by `Authentication/Services/AccountSignInMethodPolicy`. Server-side removal of Password, Google or a passkey is rejected when it would leave zero usable methods.

Google identity remains keyed by `provider + provider_subject`; provider email is metadata only. Matching provider/account email never links, merges or authenticates a User. Connecting Google is an explicit authenticated recent-proof operation targeting the current User, and a Google subject cannot belong to multiple Users.

Passkeys use Laravel's maintained first-party Passkeys implementation. The app-owned `AccountPasskey` model extends the package model, binds to the canonical `passkeys` table and adds an opaque public route identifier. User-verifying passkey authentication satisfies primary authentication without an additional TOTP challenge; password and Google continue through TOTP when configured.

See [ADR-0014](../../adr/0014-account-sign-in-methods.md), which supersedes the exclusive-primary-authentication decision in historical [ADR-0013](../../adr/0013-exclusive-account-authentication-types.md), and the [Accounts Sign-In Methods & Credential Evolution](../../../product/accounts-sign-in-methods.md) product contract.

## Recent authentication

Sensitive account operations use a generic **Confirm it's you** boundary. Password, Google or a registered passkey may satisfy recent proof when attached and allowed. The historical `password.confirm` route/middleware alias remains an internal route name only; it does not imply that the User must possess a password.

Material credential mutations clear stale recent proof and harden sessions as defined by the owning action. Controllers and workflows do not persist account credentials directly.

## Fresh-schema rule

The application remains fresh-schema with no deployed Accounts data requiring upgrade. Canonical create-schema migrations are updated directly. There is no account backfill, compatibility shim, dual read/write or legacy authentication-state bridge.

## Boundary

Accounts does not own Player game state, Alliance membership/rank, Kingdom roles or Operations/Intelligence permissions. Game-domain requests resolve a Player through `GameWorld/Players`.

External Actor Connections remain integration/reconciliation identity and never become Accounts authentication credentials.

Platform Administrator is a User-scoped Platform grant, not an Accounts game permission.

## Product-language boundary

User-facing authentication copy refers to a **Kingshot Alliance account/password/email**. It must not imply that Kingshot Alliance credentials are official Kingshot game credentials or that the application is publisher-operated.

## Physical rule

Accounts does not use context-root `Actions`, `Models`, `Services` or `Http` buckets in V3. Each implementation class belongs under the capability that owns the behavior.
