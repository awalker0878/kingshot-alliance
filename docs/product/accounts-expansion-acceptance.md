# Accounts Expansion — Acceptance Matrix

Status: Implementation complete — final main verification pending

Date: 2026-09-02

Canonical contract: [Accounts Expansion Program](accounts-expansion.md).

| ID | Acceptance criterion |
| --- | --- |
| ACCT-01 | Every active User has exactly one explicit primary authentication type: `password` or `google`. |
| ACCT-02 | Fresh-schema creation produces the final account design directly; no compatibility/backfill layer exists. |
| ACCT-03 | Google accounts have no usable local password and cannot add/change/reset one. |
| ACCT-04 | Password accounts cannot silently gain Google as another primary credential. |
| ACCT-05 | Matching email alone never changes authentication type or links a Google subject. |
| ACCT-06 | `google + provider_subject` is the authoritative durable Google identity after creation. |
| ACCT-07 | OAuth access/refresh tokens are not stored for authentication-only Google use. |
| ACCT-08 | Google authentication obeys registration/invitation, lifecycle and TOTP requirements. |
| ACCT-09 | Password authentication continues independently and remains covered by regression tests. |
| ACCT-10 | TOTP remains a second factor usable with either primary authentication type. |
| ACCT-11 | Google-only account password login/change/reset paths are rejected server-side, not merely hidden in UI. |
| ACCT-12 | Forgot Password remains enumeration resistant across nonexistent, Google and password accounts. |
| ACCT-13 | Google-account addresses never receive password-reset credentials. |
| ACCT-14 | Verification links remain signed/time-limited and resend behavior is throttled. |
| ACCT-15 | Verification/Forgot/Reset pages use professional Kingshot Alliance theme/copy, localized strings and accessible status/error behavior. |
| ACCT-16 | Security transactional mail uses Kingshot Alliance naming and never implies official game-account authentication. |
| ACCT-17 | Security Center exposes actual auth/email/MFA/recovery state and hides inapplicable credential controls. |
| ACCT-18 | Active sessions are account scoped; raw session IDs are not exposed; one/all-other revocation works and is tested. |
| ACCT-19 | User-facing Security Activity is account scoped and derives from typed audit/security events rather than a second truth store. |
| ACCT-20 | Security-sensitive changes are audited without secrets/password/recovery material. |
| ACCT-21 | Communications owns outbound security-notification delivery/retry/preferences; Accounts owns event meaning. |
| ACCT-22 | Password-account email change requires recent authentication, pending verification, atomic promotion, old-address notice and audit. |
| ACCT-23 | Google provider-subject identity is not changed by email text; provider-email collisions fail safely without relinking. |
| ACCT-24 | Recent authentication is type aware: password proof for password accounts, Google reauthentication proof for Google accounts. |
| ACCT-25 | MFA disable and recovery-code regeneration/use obey documented recent-auth and audit rules. |
| ACCT-26 | Account deletion/anonymization revokes/invalidate sessions and the applicable primary credential/provider identity. |
| ACCT-27 | Accounts does not acquire Governor/Alliance/Kingdom/game authorization ownership. |
| ACCT-28 | No user-facing copy describes a Kingshot Alliance credential as an official `Kingshot account/password`. |
| ACCT-29 | All new visible strings are localized; authentication/security flows meet repository accessibility gates. |
| ACCT-30 | PHP/Pint/PHPStan/tests, frontend/type/lint/build/tests, architecture/security gates and clean-schema verification are green on the final containing commit. |
| ACCT-31 | `/docs/product`, Accounts architecture, ADR, acceptance matrix, capability catalogue and delivery ledger match the final implementation. |

A row is complete only with corresponding implementation and automated or documented verification evidence.
