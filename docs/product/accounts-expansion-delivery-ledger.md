# Accounts Expansion — Delivery Ledger

Status: Complete — merged to `main` and verified

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
| 21 | Complete | Full verification | Exact PR head `d757a0df172bf43dbe26f8d4a2d38cdbdf7751a2` passed CI, Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL and Dependency Review before merge. |
| 22 | Complete | Documentation reconciliation | Product contract, Accounts architecture, ADR, acceptance matrix, capability catalogue and this ledger are reconciled to implemented ownership and behavior. |
| 23 | Complete | Final main verification | PR #141 merged exact head `d757a0df172bf43dbe26f8d4a2d38cdbdf7751a2` into `main`; the same immutable commit passed configured `main` push CI, Architecture V3 Verification, Visual Regression and CodeQL. Intelligence Verification and Dependency Review are pull-request-only workflows and were green on that exact commit before merge. |

## Verification evidence

PR #141 merged at 2026-09-02T13:18:46Z with merge commit equal to the exact final PR head: `d757a0df172bf43dbe26f8d4a2d38cdbdf7751a2`.

Final PR-head runs on that exact commit:

- CI: `33634238267` — success.
- Architecture V3 Verification: `33634238364` — success.
- Intelligence Verification: `33634238548` — success.
- Visual Regression: `33634238165` — success.
- CodeQL: `33634238345` — success.
- Dependency Review: `33634238223` — success.

Post-merge `main` push runs on that exact commit:

- CI: `33634963356` — success.
- Architecture V3 Verification: `33634963394` — success.
- Visual Regression: `33634963354` — success.
- CodeQL: `33634963390` — success.

`Intelligence Verification` is configured for `pull_request` plus manual dispatch, and `Dependency Review` is configured for `pull_request`; neither has a `push` trigger. Their successful PR runs therefore provide the applicable exact-commit evidence for those gates.

No implementation phase is deferred. The Accounts expansion selected extension is closed.
