# Accounts Sign-In Methods & Credential Evolution

Status: Current complete capability

Date: 2026-09-02

## Product identity

A **Kingshot Alliance User** is the permanent application account identity. Password, Google, and passkeys are sign-in methods attached to that User. They are not separate account types and they never replace the Kingshot Alliance account.

```text
Kingshot Alliance User
├── account email / profile / settings
├── Governors and application relationships
├── sessions and security activity
└── sign-in methods
    ├── Password 0..1
    ├── Google 0..1
    └── Passkeys 0..N
```

TOTP remains optional multi-factor authentication and is not a primary sign-in method.

## Fresh-schema rule

The application is not deployed and there is no Accounts data requiring migration or compatibility. Canonical create-schema migrations are changed directly to the final design. There is no account backfill, compatibility shim, dual read/write, transitional authentication type, or legacy upgrade path.

## Core invariants

1. Every active Kingshot Alliance User has at least one usable sign-in method.
2. The User is independent from any one sign-in method.
3. Password existence is represented by a usable local password credential; no `authentication_type` discriminator exists.
4. Google is an optional provider identity keyed by `provider + provider_subject`.
5. Google email is provider metadata and never an account-linking key.
6. Matching email never automatically links, logs into, or merges another User.
7. Connecting Google requires an already authenticated User plus recent authentication.
8. A Google subject or passkey credential belongs to exactly one User.
9. Removing a sign-in method is rejected when it would leave zero usable methods.
10. Account merging is unsupported.
11. TOTP recovery codes recover TOTP only; they do not become general account-recovery credentials.
12. Accounts owns authentication material and security meaning; Communications owns outbound `account.security` delivery/retry/preferences; Platform/DataGovernance retains deletion orchestration.

## Registration

Every registration creates a Kingshot Alliance account.

### Password registration

`Create Kingshot Alliance account -> name/email/password -> User -> verify email -> onboarding`.

### Google registration

`Create Kingshot Alliance account -> Continue with Google -> verified Google identity -> complete Kingshot Alliance registration -> User -> attach Google subject -> onboarding`.

Google registration copy must say the user is creating a Kingshot Alliance account and using Google to sign in.

If an unconnected Google subject presents an email already owned by a Kingshot Alliance User, registration/login is rejected with a safe instruction to sign into the existing account and connect Google from Security. Email equality is never sufficient proof of ownership.

## Google operation intents

OAuth state is server-owned, short-lived, single-use, and explicitly identifies one of:

- `register`;
- `login`;
- `reauthenticate`;
- `connect`.

The callback never infers intent from matching email, browser redirect input, or incidental session state.

## Connect Google

An authenticated User may choose **Security -> Sign-in methods -> Connect Google**. The flow requires recent authentication, proves a Google subject, rejects subjects already owned by another User, and attaches the subject to the current User. Provider email may differ from the Kingshot Alliance account email.

Connecting Google never changes the Kingshot Alliance account email.

## Google sign-in

Established Google identities resolve only by `google + provider_subject`, then perform lifecycle checks, applicable MFA, session establishment, and security audit. Provider-email metadata may refresh on use but does not change account email.

## Disconnect Google

Disconnect requires recent authentication and the central sign-in-method policy. It is allowed only when another usable sign-in method remains. Successful removal clears relevant recent proof/pending operations, applies session hardening, records security activity, and sends an account-security notification.

## Password sign-in method

A User may add a password after authenticating with another method. A password may be changed when present and removed only when another usable sign-in method remains. Removing it invalidates password-reset tokens and relevant recent-auth state.

Forgot Password remains enumeration resistant and emits reset credentials only when the User actually has a password.

## Account email

The Kingshot Alliance account email is independent from provider email and is managed by the existing signed verified-email-change workflow. Google email updates provider metadata only. Email-change eligibility is based on account ownership/recent authentication, not on which sign-in methods are attached.

## Sign-in-method policy

Accounts owns a central policy/read contract that answers:

- password present;
- Google connected;
- passkeys registered;
- available method count;
- add/remove eligibility;
- whether the User remains authenticatable after a mutation.

Controllers, middleware, Vue components, and workflows consume this policy rather than reimplementing the invariant.

## Recent authentication

Sensitive operations use a generic **Confirm it's you** boundary. Recent proof records the successful method and authentication time. Password, Google, and passkey proof may satisfy the boundary when available. The historical `password.confirm` route/middleware alias may remain as a compatibility name inside the codebase, but it no longer means that the User must possess a password.

## Passkeys

Use the maintained first-party `laravel/passkeys` server package and `@laravel/passkeys` browser client. Do not implement WebAuthn cryptography directly.

Canonical requirements:

- RP ID derived from approved application configuration;
- explicit allowed origins;
- opaque non-PII user handles;
- required user verification;
- discoverable credentials for passkey login where supported;
- short-lived single-use challenges;
- globally unique credential IDs;
- replay/origin/RP/signature verification;
- user-friendly passkey names;
- account-scoped management.

A user-verifying passkey completes authentication without an additional TOTP prompt. Password and Google sign-in continue through TOTP when TOTP is enabled.

## Security Center

Security exposes **Sign-in methods** as a first-class surface:

- Password — configured/not configured; add/change/remove;
- Google — connected/not connected; provider email; connect/disconnect;
- Passkeys — list; add; rename; remove.

MFA, recovery, sessions, security activity, account email, and lifecycle remain distinct sections.

## Security activity and notifications

Typed events cover at least Google connect/disconnect, password add/change/remove, passkey register/rename/remove/authenticate, and rejected/failed credential mutations where security relevant. Security Activity remains a projection of the canonical audit trail. Secrets, OAuth tokens, password material, TOTP secrets/recovery codes, and WebAuthn private material are never logged or delivered.

## Session hardening

Credential changes explicitly rotate or revoke sessions as appropriate, clear stale recent-authentication proof, invalidate password-reset tokens when applicable, and invalidate pending OAuth/WebAuthn operations tied to removed credentials.

## Lifecycle

Platform/DataGovernance retains deletion request/cooling-off/cancellation/finalization orchestration. Accounts finalization removes password/reset tokens, provider identities, passkeys, MFA material, sessions, pending authentication ceremonies, and recent-authentication state.

## Abuse protection

Google operation starts/callbacks, password establishment/removal, passkey registration/login/confirmation, and credential removal are bounded by rate limits, short-lived state, and replay protection. Errors do not disclose whether another User owns a credential.

## Explicit non-goals

- account merging;
- Governor/Player ownership changes caused by credential attachment;
- official Kingshot game authentication;
- game credentials;
- API credential ownership changes;
- compatibility/backfill paths for undeployed legacy Accounts data.

## Delivery order

1. Close previous Accounts expansion.
2. Product contract, acceptance matrix, ledger, ADR, architecture.
3. Canonical multi-method schema and central policy.
4. Remove `authentication_type` assumptions.
5. Password and Google registration reconciliation.
6. Explicit OAuth operation state.
7. Existing-email collision behavior.
8. Connect/disconnect Google.
9. Add/change/remove password.
10. Generic recent authentication.
11. Security Center sign-in-method UX.
12. First-party passkey server/browser foundation.
13. Passkey registration/login/confirmation/management.
14. TOTP/passkey reconciliation.
15. Session/security event/notification hardening.
16. Lifecycle cleanup and abuse protection.
17. Localization/accessibility/visual coverage.
18. Full acceptance/security/clean-schema verification.
19. Documentation and delivery-ledger reconciliation.
20. Final containing-commit verification and promotion to Current complete capability.

This document is the canonical product contract for the current complete capability. Future credential or recovery changes must preserve these security invariants and ownership boundaries or explicitly supersede them through a new product/architecture decision.