# Accounts Expansion Program

Status: Implementation complete — final main verification pending

Date: 2026-09-02

## Product identity

The product is **Kingshot Alliance**, an independent third-party alliance-management application for Kingshot players. Account authentication grants access to Kingshot Alliance only. It does not authenticate a Kingshot game account and must not imply publisher affiliation, endorsement or official status.

Use `Kingshot Alliance account`, `Kingshot Alliance password`, `Kingshot Alliance email`, `Sign in to Kingshot Alliance`, and `Continue with Google`. Avoid copy that calls application credentials a `Kingshot account` or `Kingshot password`.

## Fresh-schema delivery assumption

This program targets a fresh schema with no Accounts data. There is no account backfill, compatibility bridge, dual-read/dual-write path or legacy authentication-state migration. The canonical create-schema migrations are updated directly to the final design. The originally proposed existing-account classification/backfill phase is **not applicable by design**.

## Accounts ownership

Accounts owns application User identity, registration, exactly-one primary authentication type, password credentials for password accounts, durable Google identity for Google accounts, email ownership/verification, password recovery, TOTP/recovery codes, sessions, recent-authentication proof, user-facing security activity, account-level security notifications/events, profile settings and the account-side credential/provider invalidation and anonymization effects required by lifecycle finalization.

Platform/DataGovernance retains ownership of account-deletion request, cooling-off, cancellation and finalization coordination. Accounts does not own Governor/Player game identity, Alliance membership/rank, Kingdom identity/roles, game permissions, game credentials, Intelligence evidence or external-actor reconciliation. Those capabilities retain their existing owners.

## Authentication types

Every active account has exactly one primary authentication type:

- `password` — authenticates with a Kingshot Alliance email and local password;
- `google` — authenticates exclusively with Google.

Hybrid primary authentication is prohibited. A Google account has no usable local password and cannot add/change/reset one. A password account cannot attach Google as another primary credential. TOTP is a second factor and may be enabled for either primary type.

The User schema therefore carries an explicit `authentication_type` and a nullable password. Application behavior must not infer account type from password nullability alone.

## Durable Google identity

Google accounts persist an Accounts-owned identity record containing provider, provider subject, provider email, provider-email verification time, link/create time and last-used time. `provider=google` plus Google's `sub` is authoritative after account creation. Email alone is never the durable Google identity key.

OAuth access/refresh tokens are not persisted because Kingshot Alliance uses Google only for authentication.

Resolution order:

1. Resolve `google + sub`.
2. If found, authenticate that User subject to lifecycle and MFA checks.
3. If not found, apply registration/invitation policy for a new Google account.
4. If the verified Google email collides with an existing password account, reject the Google sign-in/registration without linking or changing authentication type.

## Registration, invitation and MFA

Google changes the proof of application identity; it does not change authorization. Google registration must obey the same registration/invitation eligibility as password registration. Disabled/deleting/anonymized accounts remain blocked according to lifecycle policy. If TOTP is enabled, successful Google authentication still proceeds through the Kingshot Alliance TOTP challenge.

## Email verification

Verification links remain Laravel signed/time-limited links. The UI uses the existing Kingshot Alliance dark/gold authentication layout with professional security language, accessible status/error handling, resend throttling/cooldown, resend success state and a sign-out/use-another-account action. Copy must refer to the Kingshot Alliance account, not an official game account.

## Password recovery

Only `password` accounts may receive or consume password-reset tokens. Forgot Password is enumeration resistant: nonexistent addresses, Google-account addresses and eligible password-account addresses return the same public outcome. Google addresses never receive reset credentials.

The Forgot Password page uses an intentional `ACCOUNT RECOVERY` → `CHECK YOUR INBOX` state. Reset Password uses a `SECURE PASSWORD RESET` experience with requirements derived from backend validation, show/hide controls, accessible validation, invalid/expired-token handling and a completion state.

## Security transactional mail

Verification, password reset, password changed and security-alert mail use a shared Kingshot Alliance presentation. Mail preserves Laravel signed verification/broker semantics, has one clear CTA, expiry guidance, fallback URL, responsive email-safe styling, plain-text support and localized professional language. It must not imitate or imply an official game-publisher communication.

## Security Center

Account settings are organized as `Profile`, `Security`, `Sessions`, and `Account`.

Security shows the one primary sign-in method and actual state rather than advertising unavailable credentials. Google accounts show Google identity and explicitly state that no Kingshot Alliance password exists. Password accounts expose password controls. Both may expose email verification, TOTP and recovery-code state.

## Sessions

Accounts exposes a privacy-conscious active-session inventory. Raw session identifiers are never exposed. The user can identify the current session, revoke a specific other session, and revoke all other sessions. Device/browser/platform labels are coarse; no invasive browser fingerprint is introduced. Existing framework session truth should be reused where practical rather than creating a competing session system.

## Security activity and notifications

User-facing Security Activity is an account-scoped projection of existing/typed audit events, not a second audit source of truth. Relevant events include sign-in, Google sign-in, password change/reset, email verification/change, MFA/recovery-code changes, session revocation and deletion lifecycle changes.

Accounts owns the fact that a security event occurred. Communications retains ownership of channel delivery/retry/preferences for notifications that leave the Accounts context.

## Email change

Password accounts may request a new account email through recent authentication, pending-email state, verification of the new address, atomic promotion, notification of the previous address and audit.

Google accounts do not receive a manual local-email-change workflow. Their durable Google `sub` remains authoritative. A verified provider-email change observed on a later Google sign-in may update provider metadata and the Kingshot Alliance contact email only when it does not collide with another User. A collision fails safely and never relinks identities or changes authentication type.

## Recent authentication

Sensitive changes require authentication-type-aware recent proof. Password accounts use password confirmation. Google accounts use a recent successful Google reauthentication marker/challenge and are never asked for a nonexistent local password.

Sensitive operations include email change, password change, MFA disable/recovery-code regeneration, destructive session operations where required, and account deletion.

## MFA and recovery codes

TOTP is a Kingshot Alliance second factor available to either authentication type. Recovery codes recover Kingshot Alliance MFA only; they do not recover Google access. A Google user who loses Google access must recover it through Google.

## Account lifecycle

Account deletion/anonymization uses the existing Platform/DataGovernance lifecycle for request, cooling-off, cancellation and finalization coordination. Sensitive lifecycle entry points require recent authentication, clear consequences, security audit/notification and documented request/cancel/finalization state.

When finalization occurs, Accounts owns the account-side invalidation effects: sessions and application tokens are invalidated; pending-email and MFA material are cleared; password credentials/reset tokens are invalidated for password accounts; Google provider identity is removed for Google accounts; and the User is anonymized so no primary authentication surface remains usable.

## Deferred/non-goals

This program does not implement hybrid accounts, Google linking to password accounts, adding a password to Google accounts, passkeys, official Kingshot game authentication, Kingshot game credential management or a cross-context data-export system.

## Delivery order

1. Documentation contract, Accounts architecture, ADR, acceptance matrix and ledger.
2. Existing Google/Socialite static-analysis blocker and green baseline.
3. Explicit fresh-schema `password|google` authentication type; no backfill.
4. Durable Google provider-subject identity.
5. Google resolution/linking hardening.
6. Registration/invitation/TOTP reconciliation.
7. Email Verification UX.
8. Forgot Password enumeration-safe UX/behavior.
9. Reset Password UX/behavior.
10. Shared Kingshot Alliance security mail.
11. Security Center.
12. Session inventory/revocation.
13. Security Activity.
14. Audit/Communications integration.
15. Verified password-account email change and Google email semantics.
16. Authentication-type-aware recent authentication.
17. MFA/recovery-code reconciliation.
18. Deletion/anonymization hardening.
19. Localization/accessibility.
20. Full security, architecture, backend, frontend and regression verification.
21. Documentation/delivery-ledger reconciliation and final `main` verification.

The implementation must keep this contract current when repository architecture requires a documented adjustment.
