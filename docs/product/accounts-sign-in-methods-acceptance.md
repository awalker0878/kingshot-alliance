# Accounts Sign-In Methods & Credential Evolution — Acceptance Matrix

Status: Complete

Date: 2026-09-02

Canonical contract: [Accounts Sign-In Methods & Credential Evolution](accounts-sign-in-methods.md).

| ID | Acceptance criterion |
| --- | --- |
| ASIM-01 | Kingshot Alliance User is the permanent application identity; sign-in methods are attached credentials, not account types. |
| ASIM-02 | Fresh schema has no `users.authentication_type` or equivalent primary-type discriminator. |
| ASIM-03 | Every active User retains at least one usable sign-in method. |
| ASIM-04 | Password presence, Google connection and passkeys are derived from actual credential state through one Accounts-owned policy. |
| ASIM-05 | Password registration creates a Kingshot Alliance User and verification flow. |
| ASIM-06 | Google registration creates a Kingshot Alliance User and attaches the verified Google subject. |
| ASIM-07 | Google registration/login never links an existing User solely because provider email matches account email. |
| ASIM-08 | Existing-email Google collision directs the user to sign into the existing account and connect Google. |
| ASIM-09 | OAuth state explicitly distinguishes register, login, reauthenticate and connect operations and is server-owned, short-lived and single-use. |
| ASIM-10 | Connect Google requires authenticated User + recent proof and attaches only to that User. |
| ASIM-11 | A Google subject already attached to another User cannot be connected. |
| ASIM-12 | Google provider email may differ from account email and never automatically changes `users.email`. |
| ASIM-13 | Established Google login resolves by provider + subject, not email. |
| ASIM-14 | Google disconnect is recent-auth protected and rejected if Google is the final usable sign-in method. |
| ASIM-15 | A User without a password may add one after recent authentication. |
| ASIM-16 | Password change works whenever a password exists, independent of Google/passkeys. |
| ASIM-17 | Password removal is recent-auth protected, invalidates reset material and is rejected if it is the final usable method. |
| ASIM-18 | Forgot/reset Password remains enumeration resistant and only emits/consumes reset credentials for Users that actually have a password. |
| ASIM-19 | Verified account-email change is independent of provider email and usable by authenticated Users under recent-auth policy. |
| ASIM-20 | Security Center exposes actual Password, Google and Passkey state and applicable controls without a primary-authentication-type concept. |
| ASIM-21 | Generic recent authentication may be satisfied by any allowed attached method and stores method/time without secret material. |
| ASIM-22 | Passkeys use a maintained WebAuthn implementation; custom WebAuthn cryptography is prohibited. |
| ASIM-23 | Passkey user handles are stable opaque non-PII values. |
| ASIM-24 | Passkey ceremonies verify RP ID, allowed origin, challenge, signature and user verification; challenges are time-limited/single-use. |
| ASIM-25 | Passkey credential IDs are globally unique and credentials cannot cross User boundaries. |
| ASIM-26 | Authenticated Users may register, list, rename and remove passkeys under recent-auth policy. |
| ASIM-27 | Guest passkey login resolves the owning User from verified credential identity rather than account email. |
| ASIM-28 | User-verifying passkey login does not require an additional TOTP challenge; password/Google do when TOTP is enabled. |
| ASIM-29 | TOTP recovery codes remain MFA recovery only and never become primary account recovery. |
| ASIM-30 | Removing any sign-in method is server-side rejected when zero usable methods would remain. |
| ASIM-31 | Account merging, Governor transfer between Users and email-based identity consolidation are unsupported. |
| ASIM-32 | Credential mutations produce typed audit/Security Activity events without secrets. |
| ASIM-33 | Communications remains owner of outbound account-security delivery/retry/preferences. |
| ASIM-34 | Significant credential mutations reconcile session rotation/revocation, recent-proof invalidation and pending operation invalidation. |
| ASIM-35 | Final account anonymization removes all Accounts-owned password, provider, passkey, MFA, session and pending-auth material. |
| ASIM-36 | Google/passkey/password mutation endpoints have bounded throttling and replay protection without credential-ownership disclosure. |
| ASIM-37 | New UI/copy is localized, keyboard/screen-reader accessible, responsive and covered by repository visual/accessibility gates. |
| ASIM-38 | Password, Google, multi-method, passkey, recent-auth, MFA, collision, cross-account and lifecycle behavior has automated V3 acceptance coverage. |
| ASIM-39 | PHP/Pint/PHPStan/backend tests, frontend lint/format/types/build/tests, Architecture V3, clean-schema, visual/accessibility, CodeQL and dependency review are green on the containing candidate. |
| ASIM-40 | `/docs/product`, Accounts architecture, ADRs, capability catalogue, frontend map and delivery ledger match final implementation. |

A row is complete only when implementation and corresponding automated/documented verification evidence exist. Release completion therefore requires the containing candidate to retain the repository's complete green verification set; any later regression reopens the applicable row rather than changing the product contract.