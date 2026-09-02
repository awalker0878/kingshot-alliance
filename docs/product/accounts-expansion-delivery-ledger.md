# Accounts Expansion — Delivery Ledger

Status: Implementation complete — final verification in progress

Date: 2026-09-02

Canonical contract: [Accounts Expansion Program](accounts-expansion.md). Acceptance: [Accounts Expansion Acceptance Matrix](accounts-expansion-acceptance.md).

## Documented delivery-order adjustment

The repository already protects MFA setup and multiple sensitive application routes with the `password.confirm` middleware alias. Under the selected exclusive-authentication model, leaving authentication-type-aware recent proof until phase 17 would block Google accounts from those protected flows because they intentionally have no local password.

Therefore the **recent-authentication foundation from phase 17 was pulled forward immediately after the primary authentication model**. The existing `password.confirm` policy boundary became authentication-type aware: password accounts continue to use password confirmation, while Google accounts use a recent successful Google reauthentication. The remaining Security Center, email-change, MFA, session and lifecycle surfaces have now been reconciled against that foundation.

This was an architecture dependency adjustment, not a change to the acceptance invariants or product scope.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Documentation source of truth | Product contract, Accounts architecture, ADR, acceptance matrix and ledger exist before runtime changes. |
| 2 | Complete | Baseline stabilization | Socialite/static-analysis blocker is resolved and the implementation-containing quality gates are green. |
| 3 | Complete | Explicit authentication type | Fresh schema creates only `password|google` account types with nullable password and application invariants. |
| 4 | Not applicable | Existing-account backfill | Fresh schema/no Accounts data; no backfill or compatibility path is permitted. |
| 5 | Complete | Durable Google identity | Provider subject is persisted and uniquely authoritative; OAuth access/refresh tokens are not persisted. |
| 6 | Complete | Google resolution hardening | Subject-first resolution is authoritative; matching email never silently links a password account; verified non-colliding provider-email changes may refresh contact email without changing subject/authentication type. |
| 7 | Complete | Registration/invitation/TOTP | Google obeys the same registration/invitation/lifecycle eligibility and existing TOTP second-factor requirements. |
| 8 | Complete | Email Verification UX | Professional themed, localized, accessible, throttled verification experience is implemented. |
| 9 | Complete | Forgot Password | Enumeration-resistant behavior and recovery/check-inbox states are implemented; Google addresses never receive reset credentials. |
| 10 | Complete | Reset Password | Password-only broker enforcement and reset UX are implemented and regression covered. |
| 11 | Complete | Security mail | Shared Kingshot Alliance verification/reset/security transactional mail and plain-text fallback are implemented. |
| 12 | Complete | Security Center | Profile/Security/Sessions/Account shell exposes authentication-type-aware state and applicable controls. |
| 13 | Complete | Session inventory/revocation | Account-scoped current/other session reads and one/all-other revocation are implemented without raw-session-ID exposure. |
| 14 | Complete | Security Activity | Account-scoped user-facing security-event projection derives from typed audit/security events. |
| 15 | Complete | Audit/notification integration | Security-sensitive changes are audited and Communications owns outbound `account.security` delivery/preferences/retry. |
| 16 | Complete | Verified email change | Password accounts use pending signed verification, atomic promotion, old-address notice and audit; Google provider-email reconciliation preserves provider-subject authority and fails safely on collisions. |
| 17 | Complete | Recent authentication | Password/Google-specific recent-proof handling is reconciled across sensitive account surfaces. |
| 18 | Complete | MFA/recovery-code reconciliation | Both primary authentication types work with TOTP; disable/regeneration/use semantics are audited and recent-auth protected where required. |
| 19 | Complete | Deletion/anonymization | Platform/DataGovernance lifecycle coordination and Accounts-side final credential/provider/session/token invalidation are reconciled and tested. |
| 20 | Complete | Localization/accessibility | New visible copy is localized and authentication/security frontend checks satisfy repository accessibility/quality gates. |
| 21 | Verification in progress | Full verification | The implementation-containing commit has cleared backend/frontend, Architecture V3, Intelligence, clean-schema, CodeQL and dependency checks; the final reconciled closeout commit must complete its applicable repository gates before merge. |
| 22 | Complete | Documentation reconciliation | Product contract, Accounts architecture, ADR, acceptance matrix, capability catalogue and this ledger are reconciled to implemented ownership and behavior. |
| 23 | Pending final main verification | Final main verification | Merge only after the final PR commit is green; then verify the containing `main` commit and close this ledger. |

No implementation phase is intentionally deferred. The ledger remains open only for final containing-commit verification and post-merge `main` verification required by ACCT-30/31.
