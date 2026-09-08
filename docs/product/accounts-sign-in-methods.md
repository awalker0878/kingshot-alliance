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

Connecting Google never changes the Kingshot Alliance account email. Accounts ConnectGoogleAccount owns the account lock, provider attachment and connection audit/security intent in one transaction. Repeating the same subject refreshes provider metadata without duplicating connection effects; replacing a different subject requires an explicit disconnect. The global provider-subject constraint rejects competing ownership.

## Google sign-in

Established Google identities resolve only by `google + provider_subject`, then perform lifecycle checks, applicable MFA, session establishment, and security audit. Provider-email metadata may refresh on use but does not change account email.

## Disconnect Google

Disconnect requires recent authentication and the central sign-in-method policy. It is allowed only when another usable sign-in method remains. Successful removal commits identity deletion, durable session revocation, security audit and Communications intent together; the HTTP adapter clears recent proof after success. Raw session cleanup runs after the outermost commit. Provider use also locks the account before the identity, matching removal and finalization.

## Password sign-in method

A User may add a password after authenticating with another method. A password may be changed when present and removed only when another usable sign-in method remains. Removing it invalidates password-reset tokens and relevant recent-auth state.

Forgot Password remains enumeration resistant and emits reset credentials only when the User actually has a password.

## Account email

The Kingshot Alliance account email is independent from provider email and is managed by the existing signed verified-email-change workflow. Email request/promotion state, audit and account-security intent commit together. Verification and old-address mail wait for the outermost commit, and rolled-back operations send none. Each new change cycle receives new security intent even when an earlier address is reused. Google email updates provider metadata only. Email-change eligibility is based on account ownership/recent authentication, not on which sign-in methods are attached.

## Sign-in-method policy

Accounts owns a central policy/read contract that answers:

- password present;
- Google connected;
- passkeys registered;
- available method count;
- add/remove eligibility;
- whether the User remains authenticatable after a mutation.

Controllers, middleware, Vue components, and workflows consume this policy rather than reimplementing the invariant.

Credential removal checks and mutations share the User row lock, including the maintained package's passkey deletion route. Accounts binds that route's deletion Action to `DeleteAccountPasskey`, which resolves the current owned passkey and applies the central last-method policy within the transaction. Package responses remain intact; deletion events persist session revocation, audit and security intent inside that transaction. Recent proof clearing and raw session cleanup wait for the outermost commit. The model no longer hides an unlocked policy callback. Accounts similarly binds registration to StoreAccountPasskey, which holds the account lock and transaction while delegating validation, creation and the registration event to the maintained package. Renaming holds the same account-first lock order, treats the current name as a no-op and gives each later real rename its own notification occurrence.

Passkey routes are account operations and do not require an active Governor or game-context version. They retain their own authentication, recent-proof, ownership, WebAuthn verification and rate-limit boundaries. Game mutations still require the current Governor authority context.

## Recent authentication

Sensitive operations use a generic **Confirm it's you** boundary. Recent proof records the successful method and authentication time. Password, Google, and passkey proof may satisfy the boundary when available. The historical `password.confirm` route/middleware alias may remain as a compatibility name inside the codebase, but it no longer means that the User must possess a password.

`RecentAuthentication` owns the single session proof (`accounts.recent_authentication_at`, method and optional credential reference). Every successful confirmation writes this proof; sensitive operations accept only this proof within the configured timeout. Superseded password/Google timestamps neither grant recent authentication nor receive dual writes.

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

Accounts owns one pending MFA login challenge with a ten-minute lifetime. It binds the accepted primary proof to the current password hash or exact Google identity and to the enrolled MFA secret using opaque fingerprints. Completion rechecks these facts and account lifecycle under the User lock, consumes the challenge, registers the final session and records the successful method. Invalid second-factor input may retry within the lifetime and rate limit; expiry or changed credentials require a new sign-in. The challenge route serializes requests for the same session so successful proof cannot be replayed concurrently. Successful sign-in by another allowed method clears any pending MFA challenge.

## Security Center

Security exposes **Sign-in methods** as a first-class surface:

- Password — configured/not configured; add/change/remove;
- Google — connected/not connected; provider email; connect/disconnect;
- Passkeys — list; add; rename; remove.

MFA, recovery, sessions, security activity, account email, and lifecycle remain distinct sections.

MFA enrollment delivers the authenticator setup through the next authenticated profile response. Confirmation and recovery-code regeneration deliver plain recovery codes through that same one-time response contract. The profile consumes these flashed values; later responses do not repeat them. The User row retains the encrypted authenticator secret and encrypted recovery-code hashes, never the plain recovery codes.

## Security activity and notifications

Typed events cover at least Google connect/disconnect, password add/change/remove, passkey register/rename/remove/authenticate, and rejected/failed credential mutations where security relevant. Security Activity remains a projection of the canonical audit trail. Secrets, OAuth tokens, password material, TOTP secrets/recovery codes, and WebAuthn private material are never logged or delivered.

## Session hardening

Credential changes explicitly rotate or revoke sessions as appropriate, clear stale recent-authentication proof, invalidate password-reset tokens when applicable, and invalidate pending OAuth/WebAuthn operations tied to removed credentials.

Accounts owns durable session revocation markers. Successful login registers its final rotated session before returning its redirect. Request tracking never clears a revoked marker and rejects revoked or anonymized accounts before resolving game context. The Laravel session handler continues to store credentials; deleting its raw session is cleanup, while the committed marker prevents a concurrent storage write or failed delete from restoring access. Revoking one or all other sessions also rotates the account's remember-me token, so existing remembered sign-ins cannot recreate the revoked session. This resets remembered sign-ins across devices while preserving the current active session. All-other revocation is limited to the registered-session snapshot and loads records in batches; storage cleanup runs after the revocation transaction commits.

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

The profile derives credential-removal availability from one Accounts policy summary. Its credential query count remains constant as the account adds passkeys; listed credentials are account-scoped. These display flags are advisory: each mutation revalidates the current owner and final-method rule under the User lock.

Invitation onboarding commits account registration, Player claiming and Alliance acceptance together. A rejected invitation leaves no partial Player ownership or newly registered credentials, and verification email is delivered only after successful commit. Existing-account acceptance uses the same owner sequence and current account email/lifecycle.

Deletion request, cancellation and processing serialize through the current account and update the Platform request and Accounts lifecycle together. Repeating a request preserves its cooling-off deadline; a new request after cancellation starts a new seven-day period and sends a new security notification. Completed deletion cannot be reopened by replaying request or cancellation.
