# Accounts Sign-In Methods & Credential Evolution — Delivery Ledger

Status: Active selected extension

Date: 2026-09-02

Canonical contract: [Accounts Sign-In Methods & Credential Evolution](accounts-sign-in-methods.md). Acceptance: [Acceptance Matrix](accounts-sign-in-methods-acceptance.md).

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Previous Accounts closeout | Existing expansion is a completed baseline and its final-main checks are reconciled. |
| 1 | Complete | Product contract | Canonical contract, acceptance matrix and this ledger exist. |
| 2 | Complete | Credential-set ADR/architecture | Exclusive account-type model is superseded in documentation before runtime change. |
| 3 | Pending | Canonical schema | `authentication_type` removed; direct fresh-schema passkey/sign-in-method design established. |
| 4 | Pending | Central sign-in-method policy | One Accounts policy determines available methods and safe removal. |
| 5 | Pending | Registration reconciliation | Password/Google both create Kingshot Alliance Users. |
| 6 | Pending | OAuth operation state | Register/login/reauthenticate/connect are explicit, bounded operations. |
| 7 | Pending | Google email collision | Matching email never links; safe existing-account handoff exists. |
| 8 | Pending | Google connect/disconnect | Intentional attachment and safe removal are complete. |
| 9 | Pending | Password add/change/remove | Password lifecycle works as an attached method. |
| 10 | Pending | Generic recent authentication | Sensitive operations accept approved attached methods. |
| 11 | Pending | Security Center | Sign-in methods are accurately managed in UX. |
| 12 | Pending | Passkey foundation | Maintained server/browser WebAuthn dependencies and config are integrated. |
| 13 | Pending | Passkey ceremonies | Register/login/confirm/list/rename/remove are complete. |
| 14 | Pending | MFA reconciliation | Password/Google TOTP and user-verifying passkey policy are enforced. |
| 15 | Pending | Security/session integration | Audit, Security Activity, Communications and session hardening complete. |
| 16 | Pending | Lifecycle/abuse controls | Finalization, throttling, replay and pending-operation cleanup complete. |
| 17 | Pending | Localization/accessibility | Visible states and selected visual journeys meet repository gates. |
| 18 | Pending | Acceptance/full verification | ASIM acceptance and repository quality/security/clean-schema gates green. |
| 19 | Pending | Documentation reconciliation | Product/architecture/catalogue/frontend/ledger match implementation. |
| 20 | Pending | Final containing commit | Final candidate verified and extension promoted to Current complete capability. |

No phase may be marked complete solely because code exists. Verification and documentation evidence are part of the exit condition.
