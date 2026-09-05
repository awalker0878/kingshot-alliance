# Gift Code Redemption Workspace — Delivery Ledger

Status: **Complete — current capability**
Baseline: `main` at `2c4d3ae8b296f81b77e1a92cf8b5edaba1f231f9`
Verified candidate-adapter correction: `610082b9cbf663e3eb6bd0c14dbe3cdba1d2b086`
Deployment: fresh schema; no compatibility/backfill/shim work

The post-merge audit of PR #145 correctly found that the documented candidate approved-source set was not fully represented by production adapters: bounded JSON and signed webhook ingestion were present, while RSS/Atom and approved structured-HTML extraction were absent. This correction adds the missing production adapters, fixes their canonical ingestion semantics, verifies them through the existing approved-source pipeline, and reconciles documentation to implemented behavior.

| ID | Deliverable | State | Completion evidence |
|---|---|---|---|
| GCW-01 | Product contract, ownership and docs | complete | Workspace contract, acceptance matrix, owner reference and capability documentation agree on ownership and provider boundaries. |
| GCW-02 | Personal Gift Code account state | complete | Canonical account-state schema + `UpdateGiftCodeAccountState`; V3 tests cover idempotent pin/snooze/restore/reminder/dismiss and prove catalogue status/revisions remain unchanged. |
| GCW-03 | Redemption-session schema and domain model | complete | Fresh canonical Gift Code schema contains account-owned sessions/items; session actions use scalar/value-object write contracts and Architecture V3/fresh-schema gates pass. |
| GCW-04 | Server-side multi-code/multi-Governor eligibility resolver | complete | `GiftCodeActionablePairResolver` rechecks ownership, trust/expiry, terminal redemption, retry timing, Player-ID readiness and qualified applicability; selector/cardinality acceptance tests pass. |
| GCW-05 | Persistent session execution/resume | complete | Create/reconcile/prepare/result/skip/abandon actions persist session workflow while `gift_code_redemptions` remains authoritative; PHP and desktop/mobile Playwright cover resume, result, skip and trust invalidation. |
| GCW-06 | Personal Gift Code inbox | complete | `GiftCodeWorkspaceQuery` and workspace controller compose bounded New/Ready/Expiring/Retry/In-progress/Snoozed/Completed views from current catalogue, owned Governors, personal state and redemptions. |
| GCW-07 | Redemption workspace frontend | complete | Responsive localized workspace UI supports selected/all-actionable runs, official handoff, recording outcomes, resume/skip/abandon and overflow-safe mobile/desktop behavior. |
| GCW-08 | Smart/consolidated Communications intents | complete | `QueueGiftCodeWorkspaceNotifications` emits bounded idempotent `gift_code.redemption_ready` logical intents; Communications retains routing/digest/provider ownership; scheduled every 15 minutes. |
| GCW-09 | Reminder workflow | complete | `QueueDueGiftCodeReminders` rechecks current eligibility, carries eligible Governor IDs as authorization metadata, clears consumed reminders and emits one logical `gift_code.reminder`; scheduled every minute. |
| GCW-10 | Privacy-gated redemption intelligence | complete | `GiftCodeRedemptionSignalService` enforces rolling-window sample + distinct-account thresholds, exposes aggregate-only data and never feeds canonical trust/applicability. |
| GCW-11 | Structured reward presentation | complete | `GiftCodeRewardPresenter` renders only qualified reward projections and preserves unknown/conflict states. |
| GCW-12 | Additional approved-source adapters/webhook | complete | Production registry contains `json-feed-v1`, `rss-atom-v1` and `structured-html-v1`; signed webhook remains the fourth push transport. RSS/Atom and HTML use canonical-domain/path controls, no redirects, bounded documents/observations and evidence fingerprints. Missing assertions normalize to canonical `available`, RSS/Atom accepts only explicit direct-child Gift Code metadata, and end-to-end V3 tests prove both new adapters are accepted through `RunApprovedGiftCodeSourceIngestion`/`IngestApprovedGiftCodeObservation` rather than only parser-tested. |
| GCW-13 | Alliance aggregate redemption coverage | complete | `gift_codes.coverage` is delegated through the `Gift Code Coordinator` specialist role, never rank alone; aggregate read model exposes counts only. |
| GCW-14 | Contributor reputation projection | complete | `RebuildGiftCodeContributorProjections` derives bounded contributor history from existing evidence/moderation, runs hourly, and reputation does not create Gift Code authority. |
| GCW-15 | API/event reconciliation | complete | Gift Code API adds qualified reward/redemption-signal presentation without exposing account session state; existing global Gift Code event contracts remain canonical. |
| GCW-16 | Operational diagnostics | complete | `GiftCodeWorkspaceOperationalHealth` exposes privacy-safe feature/session/item/reminder/contributor/source counters through Platform Administration diagnostics. |
| GCW-17 | Accessibility/localization/mobile | complete | Workspace and Notification Center strings are localized; desktop/mobile Playwright covers named controls, overflow, session execution and trust-change behavior. |
| GCW-18 | Query-budget/load verification | complete | V3 load test evaluates 100 Gift Codes × 20 Governors (2,000 possible pairs), enforces the 500-item bound before persistence and keeps the eligibility scan within the asserted query budget. |
| GCW-19 | Full automated acceptance gate | complete | Candidate `610082b9cbf663e3eb6bd0c14dbe3cdba1d2b086` passed CI (PHP/frontend, fresh schema, production image, ephemeral staging, backup/restore and image scan), Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL and Dependency Review. King Perks Verification is path-filtered and did not trigger because the correction changes no King Perks-owned path; that capability is unchanged from the previously verified `main`. |
| GCW-20 | Documentation/code reconciliation and closeout | complete | Product, acceptance, reference and operations documentation now name the implemented four candidate ingestion modes: JSON pull, RSS/Atom pull, structured HTML pull and signed webhook push; assertion vocabulary, explicit-machine-readable parsing and source-policy boundaries match code and tests. |

This ledger is closed. A later defect or material change that invalidates these acceptance conditions reopens the capability as a regression; Git history remains the implementation archive.
