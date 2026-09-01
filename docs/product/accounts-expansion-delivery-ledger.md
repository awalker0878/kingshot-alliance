# Accounts Expansion — Delivery Ledger

Status: Active selected extension

Date: 2026-08-31

Canonical contract: [Accounts Expansion Program](accounts-expansion.md). Acceptance: [Accounts Expansion Acceptance Matrix](accounts-expansion-acceptance.md).

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Documentation source of truth | Product contract, Accounts architecture, ADR, acceptance matrix and ledger exist before runtime changes. |
| 2 | Pending | Baseline stabilization | Socialite static-analysis blocker fixed and baseline quality gates green. |
| 3 | Pending | Explicit authentication type | Fresh schema creates only `password|google` account types with nullable password and application invariants. |
| 4 | Not applicable | Existing-account backfill | Fresh schema/no Accounts data; no backfill or compatibility path is permitted. |
| 5 | Pending | Durable Google identity | Provider subject persisted and uniquely authoritative; no OAuth tokens persisted. |
| 6 | Pending | Google resolution hardening | Subject-first resolution; email collision never silently links password account. |
| 7 | Pending | Registration/invitation/TOTP | Google obeys same eligibility/lifecycle rules and existing MFA. |
| 8 | Pending | Email Verification UX | Professional themed, localized, accessible, throttled verification experience. |
| 9 | Pending | Forgot Password | Enumeration-resistant behavior and professional recovery/check-inbox states. |
| 10 | Pending | Reset Password | Password-only broker enforcement and professional reset UX. |
| 11 | Pending | Security mail | Shared branded verification/reset/security transactional mail. |
| 12 | Pending | Security Center | Profile/Security/Sessions/Account shell with auth-type-aware state. |
| 13 | Pending | Session inventory/revocation | Current/other session read and one/all-other revocation without raw ID exposure. |
| 14 | Pending | Security Activity | Account-scoped user-facing security-event projection. |
| 15 | Pending | Audit/notification integration | Typed events audited; Communications owns outbound delivery. |
| 16 | Pending | Verified email change | Password-account pending verification flow; safe Google email semantics. |
| 17 | Pending | Recent authentication | Password/Google-specific recent-proof rules for sensitive operations. |
| 18 | Pending | MFA/recovery-code reconciliation | Both primary auth types work with TOTP; recovery semantics/audit hardened. |
| 19 | Pending | Deletion/anonymization | Recent-auth lifecycle UX and credential/session/provider invalidation complete. |
| 20 | Pending | Localization/accessibility | All new visible copy localized and repository accessibility requirements green. |
| 21 | Pending | Full verification | Security/architecture/backend/frontend/regression/clean-schema gates green. |
| 22 | Pending | Documentation reconciliation | Product/architecture/catalogue/acceptance/ledger match actual implementation. |
| 23 | Pending | Final main verification | Final containing `main` commit verified green with all applicable ACCT rows complete. |

The ledger must be updated as implementation evidence lands. A code-complete row remains pending until its tests and relevant repository gates are green.