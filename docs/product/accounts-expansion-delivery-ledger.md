# Accounts Expansion — Delivery Ledger

Status: Historical complete baseline — superseded for current credential semantics by [Accounts Sign-In Methods & Credential Evolution](accounts-sign-in-methods.md)

Date: 2026-09-02

Canonical historical contract: [Accounts Expansion Program](accounts-expansion.md). Historical acceptance: [Accounts Expansion Acceptance Matrix](accounts-expansion-acceptance.md). Current credential delivery ledger: [Accounts Sign-In Methods Delivery Ledger](accounts-sign-in-methods-delivery-ledger.md).

> Historical scope: this ledger records delivery of the first Accounts expansion and its then-current exclusive authentication-type architecture. Main commit `d757a0df172bf43dbe26f8d4a2d38cdbdf7751a2` subsequently carried the reconciled baseline with CI, Architecture V3, Intelligence Verification, Visual Regression, CodeQL and Dependency Review green. ADR-0014 and the Sign-In Methods program intentionally supersede the exclusive credential model while retaining the applicable identity, product-language, lifecycle and ownership boundaries.

## Documented delivery-order adjustment

The repository already protected MFA setup and multiple sensitive application routes with the `password.confirm` middleware alias. Under the selected exclusive-authentication model, leaving authentication-type-aware recent proof until phase 17 would have blocked Google accounts from those protected flows because they intentionally had no local password.

Therefore the **recent-authentication foundation from phase 17 was pulled forward immediately after the primary authentication model**. The existing `password.confirm` policy boundary became authentication-type aware: password accounts continued to use password confirmation, while Google accounts used a recent successful Google reauthentication. The remaining Security Center, email-change, MFA, session and lifecycle surfaces were reconciled against that foundation.

This was an architecture dependency adjustment for the historical program, not a change to its acceptance invariants or product scope.

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
| 21 | Complete | Full verification | Backend/frontend, Architecture V3, Intelligence, clean-schema, CodeQL, dependency, visual and release checks cleared on the completed baseline. |
| 22 | Complete | Documentation reconciliation | Product contract, Accounts architecture, ADR, acceptance matrix, capability catalogue and this ledger were reconciled to the baseline implementation. |
| 23 | Complete | Final main verification | The completed baseline was verified on `main` at `d757a0df172bf43dbe26f8d4a2d38cdbdf7751a2`. |

No historical phase remains open. Current Password/Google/Passkey credential semantics and their release evidence are governed by the Sign-In Methods program rather than reopening or rewriting this baseline ledger.