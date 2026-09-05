# Gift Code Redemption Workspace — Delivery Ledger

Status: **Complete — current capability**
Baseline: `main` at `0126df277d9ca840f58502e541fd41587b116d71`
Verified implementation candidate: `caf75e732a71ea5dfdd91f7c6432c30fa689d828`
Deployment: fresh schema; no compatibility/backfill/shim work

| ID | Deliverable | State | Completion evidence |
|---|---|---|---|
| GCW-01 | Product contract, ownership and docs | complete | Workspace contract, acceptance matrix, owner reference, capability catalogue/gap analysis and this closed ledger agree on ownership and provider boundaries. |
| GCW-02 | Personal Gift Code account state | complete | Canonical account-state schema + `UpdateGiftCodeAccountState`; V3 tests cover idempotent pin/snooze/restore/reminder/dismiss and prove catalogue status/revisions remain unchanged. |
| GCW-03 | Redemption-session schema and domain model | complete | Fresh canonical Gift Code schema contains account-owned sessions/items; session actions use scalar/value-object write contracts and Architecture V3/fresh-schema gates pass. |
| GCW-04 | Server-side multi-code/multi-Governor eligibility resolver | complete | `GiftCodeActionablePairResolver` rechecks ownership, trust/expiry, terminal redemption, retry timing, Player-ID readiness and qualified applicability; selector/cardinality acceptance tests pass. |
| GCW-05 | Persistent session execution/resume | complete | Create/reconcile/prepare/result/skip/abandon actions persist session workflow while `gift_code_redemptions` remains authoritative; PHP and desktop/mobile Playwright cover resume, result, skip and trust invalidation. |
| GCW-06 | Personal Gift Code inbox | complete | `GiftCodeWorkspaceQuery` and workspace controller compose bounded New/Ready/Expiring/Retry/In-progress/Snoozed/Completed views from current catalogue, owned Governors, personal state and redemptions. |
| GCW-07 | Redemption workspace frontend | complete | Responsive localized workspace UI supports selected/all-actionable runs, official handoff, recording outcomes, resume/skip/abandon and overflow-safe mobile/desktop behavior; Visual Regression passes. |
| GCW-08 | Smart/consolidated Communications intents | complete | `QueueGiftCodeWorkspaceNotifications` emits bounded idempotent `gift_code.redemption_ready` logical intents; Communications retains routing/digest/provider ownership; scheduled every 15 minutes. |
| GCW-09 | Reminder workflow | complete | `QueueDueGiftCodeReminders` rechecks current eligibility, carries eligible Governor IDs as authorization metadata, clears consumed reminders and emits one logical `gift_code.reminder`; scheduled every minute. |
| GCW-10 | Privacy-gated redemption intelligence | complete | `GiftCodeRedemptionSignalService` enforces rolling-window sample + distinct-account thresholds, exposes aggregate-only data and never feeds canonical trust/applicability; V3 privacy tests pass. |
| GCW-11 | Structured reward presentation | complete | `GiftCodeRewardPresenter` renders only qualified reward projections and preserves unknown/conflict states; V3 tests prove unqualified evidence is not promoted. |
| GCW-12 | Additional approved-source adapters/webhook | complete | Signed internal source webhook validates active source policy, signature, timestamp/replay and bounded observations before reusing the existing approved-source ingestion/provenance path; replay test passes. |
| GCW-13 | Alliance aggregate redemption coverage | complete | `gift_codes.coverage` is delegated through the `Gift Code Coordinator` specialist role, never rank alone; aggregate read model exposes counts only and V3 tests prove delegation, revocation and no Governor identity leakage. |
| GCW-14 | Contributor reputation projection | complete | `RebuildGiftCodeContributorProjections` derives bounded contributor history from existing evidence/moderation, runs hourly, and tests prove rank/reputation does not create Gift Code authority. |
| GCW-15 | API/event reconciliation | complete | Gift Code API adds qualified reward/redemption-signal presentation without exposing account session state; existing global Gift Code event contracts remain canonical and no personal-session public event stream is introduced. |
| GCW-16 | Operational diagnostics | complete | `GiftCodeWorkspaceOperationalHealth` exposes privacy-safe feature/session/item/reminder/contributor/source counters through Platform Administration diagnostics; behavior tests assert the diagnostic shape contains no user/Player IDs. |
| GCW-17 | Accessibility/localization/mobile | complete | Workspace and Notification Center strings are localized; desktop/mobile Playwright covers named controls, overflow, session execution and trust-change behavior; frontend lint/format/type/build and Visual Regression pass. |
| GCW-18 | Query-budget/load verification | complete | V3 load test evaluates 100 Gift Codes × 20 Governors (2,000 possible pairs), enforces the 500-item bound before persistence and keeps the eligibility scan within the asserted query budget. |
| GCW-19 | Full automated acceptance gate | complete | On implementation candidate `caf75e732a71ea5dfdd91f7c6432c30fa689d828`: CI, Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL, Dependency Review and King Perks Verification all pass, including fresh schema, staging deployment, backup/restore and image scan. |
| GCW-20 | Documentation/code reconciliation and closeout | complete | Product contract, acceptance matrix, owner reference, capability catalogue/gap analysis/global ledger and this delivery ledger describe one current fresh-schema implementation; no compatibility or parallel redemption engine is part of the extension. |

This ledger is closed. A later defect or material change that invalidates these acceptance conditions reopens the capability as a regression; Git history remains the implementation archive.