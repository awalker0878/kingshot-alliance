# Gift Code Redemption Workspace & Personalization

Status: **Current complete capability**
Baseline: `main` at `0126df277d9ca840f58502e541fd41587b116d71`
Verified implementation candidate: `caf75e732a71ea5dfdd91f7c6432c30fa689d828`
Deployment model: **fresh-schema deployment**
Owner context: `GameWorld/GiftCodes`

## Product objective

Turn the completed trusted Gift Code catalogue into an actionable account workspace that minimizes the effort required to redeem every currently eligible code across every currently owned Governor.

The capability adds persistent multi-code/multi-Governor redemption sessions, personal workflow state, reminders, privacy-gated redemption intelligence, structured reward presentation, additional approved-source ingestion, Alliance aggregate coverage, and contributor-quality projections without weakening the existing evidence/trust model or automating the Century Games redemption provider.

## Fresh-schema rule

This is a fresh deployment. The canonical create migrations are edited directly. Do not add migration/backfill shims, compatibility routes, legacy aliases, dual-write paths, shadow implementations, or upgrade-only code.

There is one Gift Code implementation after this extension.

## Ownership model

### Global catalogue truth

`GameWorld/GiftCodes` continues to own Gift Code identity, append-only provenance, approved source policy, canonical trust/expiry/reward/applicability projections, moderation, ingestion health and global lifecycle.

Personal actions never mutate catalogue truth.

### Personal Gift Code workflow

Account-owned state answers only workflow questions such as pinned, snoozed, dismissed, reminder due, last opened and last action. It never duplicates canonical trust, expiry or reward facts.

### Per-Governor redemption truth

`gift_code_redemptions` remains authoritative for one Gift Code × one Governor outcome. Player IDs supplied by clients are selectors only; current account ownership is re-resolved on every mutation.

### Redemption-session orchestration

A redemption session belongs to one account and contains bounded Gift Code × Governor work items. Session items are workflow state only; they never replace `gift_code_redemptions` as redemption truth.

## Provider boundary

The official Century Games redemption centre remains the provider boundary. The application may prepare/copy identifiers, open the official centre, record user-observed outcomes and guide continuation. It does not add CAPTCHA automation, undocumented redemption APIs, proxy redemption or inferred provider success.

## Communications boundary

GiftCodes owns whether a Gift Code notification/reminder exists, its factual content, source idempotency identity and generic urgency. `Communications/Delivery` owns recipient preferences, channels, endpoints, quiet hours, digest cadence, retry timing, provider delivery and provider diagnostics.

GiftCodes submits `NotificationIntent`; it does not select Discord/Telegram/Web Push endpoints, email addresses, digest windows or provider retry behavior.

## Workspace

The account workspace exposes bounded actionable views:

- new;
- ready to redeem;
- expiring soon;
- retry ready;
- in progress;
- snoozed;
- completed.

A Gift Code is actionable only when the server rechecks current canonical trust/expiry, currently owned Governors, existing redemption state, retry timing, in-game Player ID readiness and qualified applicability evidence.

## Redemption sessions

Supported creation modes:

- all actionable;
- expiring;
- retry ready;
- explicit Gift Code/Governor selection.

Every session item is reauthorized immediately before preparation/result/skip. A material catalogue transition can make an uncompleted item unavailable. `valid -> disputed`, `valid -> quarantined`, `valid -> invalid` or canonical expiry cannot allow a stale session to continue redemption.

Session state is persistent and resumable across refresh/device changes. A user may skip an item, abandon a session, resume an active session and create a new run after an older run is complete/abandoned.

## Personal state

Supported account actions:

- pin/unpin;
- snooze until a bounded future time;
- dismiss;
- restore;
- schedule/cancel a reminder.

Personal state never promotes or retracts global catalogue truth.

## Redemption intelligence

Redemption intelligence is a privacy-safe rolling aggregate over official-handoff-backed Governor observations. It exposes counts/rates/recency only after minimum distinct-account and sample thresholds pass.

It is **observational only**. It cannot directly produce canonical Gift Code trust, canonical expiry, reward facts or Kingdom/region applicability. Many Governors owned by one account do not count as independent accounts.

## Structured reward presentation

Qualified reward evidence may be rendered through structured item types such as resource, currency, speedup, hero item, chest and other. Unknown/conflicting evidence remains explicitly unknown/conflicted; presentation does not relax the existing evidence gate.

## Approved-source expansion

New adapters/webhook ingestion reuse the approved source registry and `IngestApprovedGiftCodeObservation` path. Source registration, active policy, verification method, replay protection, bounded batch size and content fingerprinting remain mandatory. A webhook does not become authoritative merely because it can reach an endpoint.

## Alliance coverage

Normal users see their own Governors. Alliance aggregate coverage requires the explicit `gift_codes.coverage` permission. The default `Gift Code Coordinator` specialist role carries that permission and can be explicitly delegated/revoked through existing Alliance role management; R4/R5 rank alone is never coverage authority.

The coverage projection exposes aggregate Governor/code counts only. It does not expose member names, Player IDs or individual redemption history. Gift Code catalogue moderation and approved-source administration remain platform-only regardless of Alliance role or permission.

## Contributor quality

Contributor quality is derived from useful/corroborated/rejected/moderation-confirmed misleading submissions. It may affect spam controls, moderation prioritization and recognition. It never converts community evidence into official evidence or bypasses the canonical evidence gate.

## Operational rules

- All list and scheduled operations are bounded.
- Session creation has configurable Gift Code/Governor/item limits.
- Personal reminders are idempotent and scheduled every minute.
- Consolidated actionable-workspace notifications are idempotent and scheduled every 15 minutes.
- Contributor projections rebuild through a bounded hourly cycle.
- Aggregate intelligence is privacy gated.
- Communications provider state never mutates Gift Code source truth.
- Source-specific diagnostics remain with GiftCodes; provider diagnostics remain with Communications.
- Privacy-safe Gift Code workspace operational counters are exposed through Platform Administration diagnostics.
- All new strings are localized and desktop/mobile flows are accessible.

## Feature flags

Operationally separable capabilities use:

- `gift_codes.redemption_workspace`;
- `gift_codes.redemption_intelligence`;
- `gift_codes.alliance_coverage`;
- `gift_codes.contributor_reputation`;
- `gift_codes.source_webhook_ingestion`.

Canonical authorization, session invariants and trust/evidence integration are not dual-run or selectable implementations.

## Delivery closeout

The authoritative acceptance matrix is `gift-code-redemption-workspace-acceptance.md`; the closed delivery ledger is `gift-code-redemption-workspace-delivery-ledger.md`.

Implementation candidate `caf75e732a71ea5dfdd91f7c6432c30fa689d828` passed CI, Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL, Dependency Review and King Perks Verification. The capability is therefore current product truth rather than selected-extension work.