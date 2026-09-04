# Accounts Sign-In Methods & Credential Evolution — Delivery Ledger

Status: Complete

Date: 2026-09-02

Canonical contract: [Accounts Sign-In Methods & Credential Evolution](accounts-sign-in-methods.md). Acceptance: [Acceptance Matrix](accounts-sign-in-methods-acceptance.md).

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Previous Accounts closeout | Existing expansion is a completed baseline and its final-main checks are reconciled. |
| 1 | Complete | Product contract | Canonical contract, acceptance matrix and this ledger exist. |
| 2 | Complete | Credential-set ADR/architecture | Exclusive account-type model is superseded in documentation before runtime change. |
| 3 | Complete | Canonical schema | `authentication_type` removed; direct fresh-schema passkey/sign-in-method design established. |
| 4 | Complete | Central sign-in-method policy | One Accounts policy determines available methods and safe removal. |
| 5 | Complete | Registration reconciliation | Password/Google both create Kingshot Alliance Users. |
| 6 | Complete | OAuth operation state | Register/login/reauthenticate/connect are explicit, bounded operations. |
| 7 | Complete | Google email collision | Matching email never links; safe existing-account handoff exists. |
| 8 | Complete | Google connect/disconnect | Intentional attachment and safe removal are implemented. |
| 9 | Complete | Password add/change/remove | Password lifecycle works as an attached method. |
| 10 | Complete | Generic recent authentication | Sensitive operations accept approved attached methods. |
| 11 | Complete | Security Center | Sign-in methods are accurately managed in UX. |
| 12 | Complete | Passkey foundation | Maintained server/browser WebAuthn dependencies and config are integrated. |
| 13 | Complete | Passkey ceremonies | Register/login/confirm/list/rename/remove are implemented. |
| 14 | Complete | MFA reconciliation | Password/Google TOTP and user-verifying passkey policy are implemented. |
| 15 | Complete | Security/session integration | Audit, Security Activity, Communications and session hardening are implemented. |
| 16 | Complete | Lifecycle/abuse controls | Finalization, throttling, replay and pending-operation cleanup are implemented. |
| 17 | Complete | Localization/accessibility | Visible states are localized and repository frontend/accessibility/visual gates cover the delivered surfaces. |
| 18 | Complete | Acceptance/full verification | Architecture V3, static analysis, full V3 PHPUnit, PHP quality/tests, frontend quality/build, clean-schema, CodeQL, dependency review, Intelligence Verification and visual regression are release gates for the containing candidate. |
| 19 | Complete | Documentation reconciliation | Product/architecture/catalogue/frontend/ledger are reconciled to the credential-set implementation. |
| 20 | Complete | Final containing commit | The extension is promoted to Current complete capability only when the containing candidate retains the full green release-gate set. |

No phase is complete solely because code exists. If a containing candidate loses a required gate, the affected verification phase is reopened rather than weakening this ledger.