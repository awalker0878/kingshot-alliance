# Accounts Expansion — Delivery Ledger

Status: Active selected extension

Date: 2026-08-31

Canonical contract: [Accounts Expansion Program](accounts-expansion.md). Acceptance: [Accounts Expansion Acceptance Matrix](accounts-expansion-acceptance.md).

## Documented delivery-order adjustment

The repository already protects MFA setup and multiple sensitive application routes with the `password.confirm` middleware alias. Under the selected exclusive-authentication model, leaving authentication-type-aware recent proof until phase 17 would block Google accounts from those protected flows because they intentionally have no local password.

Therefore the **recent-authentication foundation from phase 17 is pulled forward immediately after the primary authentication model**. The existing `password.confirm` policy boundary will become authentication-type aware: password accounts continue to use password confirmation, while Google accounts use a recent successful Google reauthentication. Phase 17 remains open until the later Security Center, email-change, MFA, session and lifecycle surfaces have all been reconciled against that foundation.

This is an architecture dependency adjustment, not a change to the acceptance invariants or product scope.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Documentation source of truth | Product contract, Accounts architecture, ADR, acceptance matrix and ledger exist before runtime changes. |
| 2 | In progress | Baseline stabilization | Socialite static-analysis blocker fixed; complete containing quality gates must be green. |
| 3 | Implemented / verification pending | Explicit authentication type | Fresh schema creates only `password|google` account types with nullable password and application invariants. |
| 4 | Not applicable | Existing-account backfill | Fresh schema/no Accounts data; no backfill or compatibility path is permitted. |
| 5 | Implemented / verification pending | Durable Google identity | Provider subject persisted and uniquely authoritative; no OAuth tokens persisted. |
| 6 | Implemented / verification pending | Google resolution hardening | Subject-first resolution; email collision never silently links password account. |
| 7 | Implemented / verification pending | Registration/invitation/TOTP | Google obeys same eligibility/lifecycle rules and existing MFA. |
| 8 | Pending | Email Verification UX | Professional themed, localized, accessible, throttled verification experience. |
| 9 | Pending | Forgot Password | Enumeration-resistant behavior and professional recovery/check-inbox states. |
| 10 | Pending | Reset Password | Password-only broker enforcement and professional reset UX. |
| 11 | Pending | Security mail | Shared branded verification/reset/security transactional mail. |
| 12 | Pending | Security Center | Profile/Security/Sessions/Account shell with auth-type-aware state. |
| 13 | Pending | Session inventory/revocation | Current/other session read and one/all-other revocation without raw ID exposure. |
| 14 | Pending | Security Activity | Account-scoped user-facing security-event projection. |
| 15 | Pending | Audit/notification integration | Typed events audited; Communications owns outbound delivery. |
| 16 | Pending | Verified email change | Password-account pending verification flow; safe Google email semantics. |
| 17 | Foundation pulled forward | Recent authentication | Password/Google-specific recent-proof middleware is required now because existing protected routes use `password.confirm`; phase closes after all sensitive surfaces are reconciled. |
| 18 | Pending | MFA/recovery-code reconciliation | Both primary auth types work with TOTP; recovery semantics/audit hardened. |
| 19 | Pending | Deletion/anonymization | Recent-auth lifecycle UX and credential/session/provider invalidation complete. |
| 20 | Pending | Localization/accessibility | All new visible copy localized and repository accessibility requirements green. |
| 21 | Pending | Full verification | Security/architecture/backend/frontend/regression/clean-schema gates green. |
| 22 | Pending | Documentation reconciliation | Product/architecture/catalogue/acceptance/ledger match actual implementation. |
| 23 | Pending | Final main verification | Final containing `main` commit verified green with all applicable ACCT rows complete. |

The ledger must be updated as implementation evidence lands. A code-complete row remains pending until its tests and relevant repository gates are green.