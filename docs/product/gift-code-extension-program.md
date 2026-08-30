# Gift Code trust, discovery, and redemption expansion

Status: Selected extension
Baseline: `main` at `f63896f`
Owner context: `GameWorld/GiftCodes`

## 1. Product position

The existing Gift Code capability remains **current complete** for its documented baseline behavior. This program is an additive extension named **Gift Code trust, discovery, and redemption expansion**. It does not replace the existing capability, redefine unsupported Kingshot mechanics, or automate the Century Games redemption provider.

The extension strengthens global catalogue trust, governed evidence, multi-Governor redemption, discovery/lifecycle delivery, approved-source ingestion, bounded reads, and evidence-gated game facts.

## 2. Ownership and authority

### Global catalogue

Global Gift Code identity, approved sources, accepted evidence, derived trust state, moderation decisions, canonical expiry, canonical reward/applicability facts, ingestion health, and catalogue lifecycle are platform-owned.

Alliance rank is not catalogue authority. R4/R5 membership does not permit verification, rejection, quarantine, restoration, expiry correction, dispute resolution, source registration, or ingestion administration.

Global trust decisions require an MFA-protected platform administrator or the narrowly scoped Gift Code curator grant. All writes are server-authorized at execution time.

### Governor redemption

A Gift Code redemption is owned by the account/owned Governor pair. The server resolves and reauthorizes every target Governor from current account ownership. Submitted Player IDs are selectors only and never proof of authority.

Governor-specific provider outcomes remain observations about that Governor's handoff. They do not automatically establish global game facts such as regional eligibility or global invalidity.

## 3. Trust and evidence model

### Raw evidence

`GiftCodeProvenance` is append-only. An observation can record:

- source type and label;
- registered source identifier when applicable;
- source URL;
- claimed expiry;
- expiry precision and timezone;
- source publication timestamp;
- observation timestamp;
- evidence classification;
- verification state;
- retrieval version;
- parser version;
- content fingerprint/checksum;
- immutable raw-evidence reference where applicable.

Corrections never rewrite prior provenance. They append a new observation or a moderation decision referencing the evidence being corrected.

Legacy provenance that was user-labelled `official` is backfilled as **unverified legacy evidence**. The migration must not promote those rows to verified official evidence.

### Approved source registry

Approved Gift Code sources have platform-owned identity, classification, canonical domain, active/revoked state, verification method, provenance policy, ingestion eligibility, and timestamps. Ordinary submissions cannot claim the authoritative `official` classification.

A source being registered does not by itself prove every observation; the observation must satisfy the source verification policy.

### Derived status

Trust-v2 derives a status and explanation from accepted evidence. Runtime status is one of:

- `pending` — insufficient accepted evidence;
- `valid` — qualified positive evidence establishes credible redeemability;
- `invalid` — qualified global negative evidence establishes invalidity;
- `expired` — accepted expiry evidence establishes that the code has expired;
- `disputed` — credible accepted evidence conflicts materially;
- `quarantined` — a platform moderation decision temporarily removes the code from normal redeemable discovery while preserving its evidence.

Every derived state exposes:

- stable reason code;
- monotonic `status_revision`;
- supporting evidence references;
- derivation timestamp;
- shadow/comparison result while trust-v2 is not authoritative.

A community submission begins `pending`. One unverified negative Governor report cannot make a code globally invalid or unavailable. Verified official evidence, or a documented independent-evidence threshold, can establish global validity/invalidity. Conflicting credible evidence becomes `disputed`.

Expiry is derived from accepted evidence, not the earliest arbitrary user claim. Precise expiry is not canonical until the evidence gate qualifies it.

### Transition delivery

Every material derived-state transition increments `status_revision`. Audit/outbox idempotency keys include the revision so a sequence such as `valid -> disputed -> valid` emits three distinct material transitions while a replay of the same revision remains idempotent.

## 4. Trust-v2 rollout

Feature flag: `gift_codes.trust_v2`.

Modes:

1. `off` — existing resolver remains authoritative.
2. `shadow` — trust-v2 evaluates the same records, stores/records comparison diagnostics, but does not write authoritative trust state.
3. `authoritative` — trust-v2 owns derived trust transitions.

Authority may switch only after legacy evidence is classified/backfilled, migrations are complete, comparison differences are understood, acceptance tests pass, and product/architecture documentation is reconciled.

## 5. Moderation workflow

Feature flag: `gift_codes.moderation`.

Review queues include pending, disputed, conflicting-expiry, suspicious-source, heavily-reported, ingestion-quarantined, and source-revocation cases.

Review detail shows evidence history, verification state, source registration state, Governor outcome distribution, affected accounts/Governors only within authorization, current derived state, proposed state, reason code, and supporting evidence.

Supported audited actions:

- verify;
- reject;
- quarantine;
- restore;
- correct expiry;
- resolve dispute.

Reject, quarantine, expiry correction, and dispute resolution require a reason. Every material decision writes audit and outbox evidence.

Bulk review is bounded, previewed, confirmed, reauthorized per item, reports per-item results, and tolerates partial failure without silently retrying completed items.

## 6. Guided multi-Governor redemption

An account may select the current Governor, all owned Governors, failed/incomplete Governors, or an individual subset. The workflow presents one Governor at a time with Governor name, Kingdom, in-game Player ID, copy Player ID, copy Gift Code, open official Century Games center, record observed outcome, and continue.

Confirmation/outcome recording can target any currently owned Governor without switching active context. Every mutation reauthorizes ownership server-side.

Negative provider evidence is accepted only after a prior official handoff record for that Gift Code/Governor pair.

UI outcome vocabulary:

- redeemed;
- already redeemed;
- invalid;
- expired;
- wrong Kingdom;
- rate-limited;
- temporarily unavailable;
- permanent failure.

Terminal success remains terminal. Retryable outcomes use bounded retry/backoff. UI labels distinguish **handoff prepared** from **redemption completed**.

Incomplete-redemption views are resumable and filterable by Governor, status, urgency, and failure state.

## 7. Discovery and lifecycle notifications

Feature flag: `gift_codes.notification_fanout`.

Notification types:

- `gift_code.available`;
- `gift_code.expiring`;
- `gift_code.trust_changed`.

Delivery uses bounded cursor-based fan-out and existing Communications infrastructure.

Rules:

- availability: once per account/revision when a code becomes credibly redeemable, listing eligible owned Governors;
- expiry: per incomplete Governor and qualified expiry revision;
- trust changed: affected users who started the code when it becomes disputed, quarantined, invalid, or receives a material expiry correction;
- recheck current ownership, channel preference, and redemption state immediately before queueing;
- idempotency includes code ID, Player ID where applicable, expiry revision, status revision, notification type, and channel;
- disabled channels remain disabled;
- notifications deep-link to the specific Gift Code detail/workflow.

Scheduled commands are bounded and cursor/checkpoint based, with counters, receipts/failure diagnostics, retry, and operator replay.

## 8. Approved-source ingestion

Feature flag: `gift_codes.approved_source_ingestion`.

Only documented registered sources with active ingestion authorization may produce automated observations. Adapters record source/retrieval time, source version, parser version, checksum/content fingerprint, raw-evidence reference, and normalized observation metadata.

Ingestion is idempotent. Parser failures, unsupported formats, source revocation, policy failures, and conflicting material values are quarantined for review.

Health reporting exposes stale sources, failures, rejected/quarantined observations, last attempted retrieval, and last successful retrieval.

The Century Games redemption center remains a handoff boundary. This capability must not add CAPTCHA automation, undocumented redemption APIs, proxying, or inferred provider results. Alliance API credentials remain read-only for global Gift Code state; ingestion credentials are separately platform-authorized.

## 9. Catalogue and read model

The catalogue uses bounded cursor pagination instead of a fixed all-record load.

Views:

- active;
- pending review;
- disputed;
- expired;
- completed;
- history.

Index rows do not eagerly load complete provenance/redemption histories. Full histories are detail queries.

Search/filter dimensions include code, trust/status, source, expiry, and Governor outcome. Actionable order is expiry urgency, trust, then discovery time.

Command Overview distinguishes new redeemable, in-progress, retry-due, and disputed/retracted Gift Codes.

## 10. API and webhook contract

Canonical read endpoint: `/api/v1/gift-codes`.
Compatibility alias: `/commands/gift-codes`.

Default API result is verified active codes. Pending/disputed entries require an explicit filter and applicable authorization.

Read representation includes, as appropriate:

- Gift Code ID and code;
- trust status;
- reason code;
- `status_revision`;
- source count;
- canonical expiry and precision;
- official handoff URL;
- bounded cursor metadata.

Webhook payloads are versioned and include `status_revision`. Existing signing, scoping, retry, receipt, and replay boundaries are reused.

## 11. Reward and applicability evidence gates

Reward contents, Kingdom/region applicability, and precise expiry are sourced observations rather than unsupported columns treated as game truth.

Qualification evaluates source authority, version, locale, publication/observation timestamps, verification state, conflicts, and current source policy. Only a passing evidence gate promotes a canonical fact projection.

Unqualified rewards render **Reward details unknown**. A single `wrong_kingdom` Governor result must never create a global Kingdom restriction.

## 12. Security and abuse boundaries

- Ordinary submissions cannot assert authoritative official provenance.
- Source URLs are normalized and validated; registered-source authority is matched to configured canonical domains/policies rather than display labels.
- Moderation is platform-scoped and MFA-protected/narrowly granted.
- Alliance rank is never used for catalogue moderation.
- Ownership is reauthorized per Governor target.
- Negative provider evidence requires an existing official handoff.
- Raw evidence remains immutable.
- Credentials for source ingestion are platform-scoped and separate from Alliance API credentials.
- Every bulk/scheduled operation has explicit bounds.

## 13. Delivery ledger

| ID | Deliverable | State | Completion evidence |
|---|---|---|---|
| GCX-01 | Product contract and ADR reconciliation | in progress | This document + ADR-0004 amendment |
| GCX-02 | Approved source/evidence/moderation additive schema | pending | Migration + models/tests |
| GCX-03 | Legacy `official` evidence safe backfill | pending | Migration/backfill tests |
| GCX-04 | Trust-v2 resolver and monotonic status revision | pending | Resolver + transition tests |
| GCX-05 | Shadow comparison and feature flag | pending | Config + comparison diagnostics/tests |
| GCX-06 | Governed moderation workflow | pending | Auth/actions/queries/UI/tests |
| GCX-07 | Guided multi-Governor redemption | pending | Actions/controllers/UI/tests |
| GCX-08 | Availability notification fan-out | pending | Publisher/command/scheduler/tests |
| GCX-09 | Expiry notification fan-out | pending | Publisher/command/scheduler/tests |
| GCX-10 | Trust-change notification fan-out | pending | Publisher/outbox/command/tests |
| GCX-11 | Approved-source ingestion | pending | Registry/adapters/command/health/tests |
| GCX-12 | Cursor-paginated catalogue/detail reads | pending | Query/controller/UI/tests |
| GCX-13 | Command Overview lifecycle projections | pending | Read model/tests |
| GCX-14 | `/api/v1/gift-codes` + compatibility alias | pending | Routes/controller/tests |
| GCX-15 | Versioned webhook payload with revision | pending | Contract/publisher/tests |
| GCX-16 | Reward/applicability evidence gate | pending | Observation projection/tests/UI |
| GCX-17 | Operational diagnostics and replay | pending | Counters/receipts/replay tests |
| GCX-18 | Accessibility/mobile/visual regression | pending | Frontend/Playwright evidence |
| GCX-19 | Query-budget and large-history verification | pending | Fixtures/tests |
| GCX-20 | Final docs/code reconciliation and gate closeout | pending | Updated ledger + verification results |

A row becomes `complete` only when documented behavior, integration, authorization, UX where applicable, tests, and operational behavior are present. `intentionally deferred` requires a precise dependency/reason. `not applicable` requires repository evidence.

## 14. Acceptance criteria

The extension is acceptable only when automated coverage demonstrates:

- spoofed official source rejection;
- duplicate and conflicting provenance preservation;
- malicious/misleading source URL rejection or quarantine;
- a single user cannot globally invalidate a code;
- conflicting expiry claims derive pending/disputed until qualified;
- `valid -> disputed -> valid` emits all revisions;
- concurrent submission/moderation/redemption is safe;
- foreign/revoked Governor ownership is rejected at mutation time;
- multi-Governor continuation and partial failure are resumable;
- terminal outcomes stay terminal and retries are bounded;
- disabled notification channels are respected;
- duplicate sweeps do not duplicate unchanged delivery while changed revisions do deliver;
- ingestion replay is idempotent;
- parser failures and revoked sources quarantine;
- API authorization, cursor pagination, compatibility and response bounds hold;
- webhook versioning, scoping, signing, retry and replay hold;
- Gift Code catalogue/detail/guided redemption are accessible on desktop and mobile;
- large-history fixtures remain within documented query budgets;
- all applicable architecture, PHP, static-analysis, TypeScript, localization, accessibility, and Playwright gates pass.

## 15. Delivery sequence

1. Documentation and trust-policy ADR.
2. Additive evidence/source/moderation schema and legacy backfill.
3. Trust resolver and transition-revision correction.
4. Moderation workflow.
5. Guided multi-Governor redemption.
6. Availability, expiry, and trust-change delivery.
7. Approved-source ingestion.
8. Paginated catalogue, dashboard, API, and webhook updates.
9. Evidence-gated reward/applicability framework.
10. Full reconciliation, operational verification, and delivery-ledger closeout.

A dependency-driven sequence adjustment must first be recorded in this document with the reason and resulting delivery order.