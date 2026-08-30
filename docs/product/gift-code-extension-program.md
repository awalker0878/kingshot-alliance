# Gift Code trust, discovery, and redemption expansion

Status: Implementation complete — Gift Code-specific verification passed; unrelated repository visual-baseline failures remain documented below
Baseline: `main` at `f63896f`
Deployment model: **fresh-schema redeployment**
Owner context: `GameWorld/GiftCodes`

## 1. Product position

The existing Gift Code capability is the functional starting point, but this extension is delivered as a fresh-schema redeployment. The deployed application has one canonical Gift Code implementation only.

The extension strengthens global catalogue trust, governed evidence, multi-Governor redemption, discovery/lifecycle delivery, approved-source ingestion, bounded reads, and evidence-gated game facts without automating the Century Games redemption provider or inventing unsupported Kingshot mechanics.

### Fresh-schema rule

The final repository must not retain Gift Code migration/backfill shims, legacy trust resolvers, shadow-comparison modes, compatibility routes, deprecated API aliases, legacy evidence classifications, or code whose only purpose is preserving the pre-extension Gift Code schema or behavior.

The canonical Gift Code schema is defined directly by the create migrations. Redeployment recreates the database from those migrations; no production Gift Code data migration is required by this program.

## 2. Ownership and authority

### Global catalogue

Global Gift Code identity, approved sources, accepted evidence, derived trust state, moderation decisions, canonical expiry, canonical reward/applicability facts, ingestion health, and catalogue lifecycle are platform-owned.

Alliance rank is not catalogue authority. R4/R5 membership does not permit verification, rejection, quarantine, restoration, expiry correction, dispute resolution, source registration, or ingestion administration.

Global trust decisions require an MFA-protected platform administrator or the narrowly scoped Gift Code curator grant. All writes are server-authorized at execution time.

### Governor redemption

A Gift Code redemption is owned by the account/owned Governor pair. The server resolves and reauthorizes every target Governor from current account ownership. Submitted Player IDs are selectors only and never proof of authority.

Governor-specific provider outcomes remain observations about that Governor's handoff. They do not automatically establish global game facts such as regional eligibility or global invalidity.

## 3. Canonical trust and evidence model

### Gift Code projection

`gift_codes` contains catalogue identity and derived projection only:

- code and normalized code;
- creating Governor where applicable;
- current derived trust status;
- monotonic `status_revision`;
- stable status reason code;
- supporting evidence references;
- status changed/derived timestamps;
- discovery timestamp;
- canonical accepted expiry, precision, and monotonic `expires_revision`.

Source labels, URLs, authority claims, reward claims, applicability claims, and raw observations are not duplicated onto `gift_codes`; they belong to evidence.

### Append-only evidence

`GiftCodeProvenance` is append-only. An observation records, as applicable:

- registered source identifier;
- submitting Governor for community/manual observations;
- source label and source URL as observed metadata;
- assertion type and structured assertion payload;
- claimed expiry, precision, and timezone;
- source publication timestamp and observation timestamp;
- evidence classification;
- verification state;
- source/retrieval/parser versions;
- content fingerprint/checksum;
- immutable raw-evidence reference;
- deduplication fingerprint.

Corrections never rewrite prior provenance. They append a new observation or a moderation decision referencing the evidence being corrected.

Ordinary submissions cannot assert official authority. They create unverified community/manual evidence and begin with global status `pending` unless independently qualified evidence already establishes another state.

### Approved source registry

Approved Gift Code sources have platform-owned identity, classification, canonical domain, active/revoked state, verification method, provenance policy, ingestion eligibility, and timestamps. Ordinary users cannot create or impersonate registered authoritative sources.

A source being registered does not by itself prove every observation; the observation must satisfy that source's verification policy.

### Derived status

The canonical resolver derives:

- `pending` — insufficient accepted evidence;
- `valid` — qualified positive evidence establishes credible redeemability;
- `invalid` — qualified global negative evidence establishes invalidity;
- `expired` — accepted expiry evidence establishes expiry;
- `disputed` — credible accepted evidence conflicts materially;
- `quarantined` — platform moderation temporarily removes normal redeemability while preserving evidence.

Every derived state exposes a stable reason code and supporting evidence references. One unverified negative Governor report cannot make a code globally invalid or unavailable. Verified authoritative evidence, or a documented independent-evidence threshold, can establish validity or invalidity. Conflicting credible evidence produces `disputed`.

Expiry is derived from accepted evidence, not the earliest arbitrary claim. Conflicting qualified expiry evidence produces `disputed` until resolved.

### Material transitions

Every material trust transition increments `status_revision`. Every material canonical expiry change increments `expires_revision`. Audit/outbox idempotency uses those revisions so `valid -> disputed -> valid` emits all three material transitions while replay of an unchanged revision remains idempotent.

There is no legacy resolver, shadow mode, comparison column, or authority switch in the final implementation.

## 4. Feature flags

Feature flags exist only for operationally independent capabilities that may be enabled after deployment:

- `gift_codes.moderation`;
- `gift_codes.approved_source_ingestion`;
- `gift_codes.notification_fanout`.

The canonical trust resolver itself is not dual-run or selectable. It is the only Gift Code trust implementation.

## 5. Moderation workflow

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

There is one canonical outcome-recording route. The deprecated generic issue-report route/action is removed rather than retained as a compatibility alias.

## 7. Discovery and lifecycle notifications

Feature flag: `gift_codes.notification_fanout`.

Notification types:

- `gift_code.available`;
- `gift_code.expiring`;
- `gift_code.trust_changed`.

Delivery uses bounded cursor-based fan-out and existing Communications infrastructure.

Rules:

- availability: once per account/status revision when a code becomes credibly redeemable, listing eligible owned Governors;
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

There is no deprecated `/commands/gift-codes` compatibility alias in the fresh-schema implementation.

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
- No compatibility shim may bypass the canonical authorization or evidence path.

## 13. Delivery ledger

| ID | Deliverable | State | Completion evidence |
|---|---|---|---|
| GCX-01 | Product contract and ADR reconciliation | complete | This contract, ADR-0004, capability catalogue/gap/ledger, user journey, architecture map and Gift Code/API/event references agree on fresh-schema semantics |
| GCX-02 | Canonical approved-source/evidence/moderation schema | complete | `2026_08_19_220000_create_gift_code_tables.php`, curator-grant create migration and corresponding models |
| GCX-03 | Remove legacy/backfill/shadow/compatibility Gift Code paths | complete | Canonical route/action/schema search; no old resolver, comparison field, backfill, report action/route or `/commands/gift-codes` alias remains |
| GCX-04 | Canonical trust resolver and monotonic status/expiry revisions | complete | `GiftCodeTrustResolver`, `ReconcileGiftCodeStatus`, and revision assertions in `GiftCodeBehaviorV3Test` |
| GCX-05 | Governed moderation workflow | complete | curator middleware/grants, source administration, moderation action/controller/routes, `Platform/GiftCodes/Review.vue`, authority/revision behavior tests |
| GCX-06 | Guided multi-Governor redemption | complete | `PrepareGiftCodeRedemptions`, canonical result action/route, `Kingdom/GiftCodes/Index.vue`, ownership/terminal-result behavior and `GiftCodes.spec.ts` |
| GCX-07 | Availability notification fan-out | complete | persisted campaigns, bounded transition sweep, Communications preferences, scheduler and fan-out behavior test |
| GCX-08 | Expiry notification fan-out | complete | bounded cursor sweep, qualified-expiry revision idempotency, current-state recheck, preference/revision behavior test |
| GCX-09 | Trust-change notification fan-out | complete | transition campaign scheduling for dispute/hold/invalid/expiry changes, started-user targeting and revision-aware delivery |
| GCX-10 | Approved-source ingestion | complete | registered-source policy UI/action, adapter registry/contract, ingestion runner, run health, targeted replay, parser/replay/revocation tests |
| GCX-11 | Cursor-paginated catalogue/detail reads | complete | `GiftCodeCatalogQuery`, catalogue/detail controller/UI and constant-query large-history behavior test |
| GCX-12 | Command Overview lifecycle projections | complete | new-redeemable/in-progress/retry-due/disputed-retracted projection and `CommandOverviewBehaviorV3Test` |
| GCX-13 | Canonical `/api/v1/gift-codes` | complete | canonical route/controller/query, OpenAPI/Connections update, authorization and opaque-cursor behavior test |
| GCX-14 | Versioned webhook payload with revision | complete | runtime catalogue, JSON Schema/event reference, revisioned outbox payloads and published-contract/webhook tests |
| GCX-15 | Reward/applicability evidence gate | complete | fact resolver/projection/reconciliation, unknown/conflict UI/API states and fact qualification/conflict tests |
| GCX-16 | Operational diagnostics and replay | complete | persisted notification/source cursors, JSON sweep receipts, source run health, targeted ingestion replay and policy-reconciliation scheduler |
| GCX-17 | Accessibility/mobile/visual regression | complete | localized responsive catalogue/moderation flows, static accessibility/localization gates and desktop/mobile `GiftCodes.spec.ts` |
| GCX-18 | Query-budget and large-history verification | complete | 80-code/120-provenance constant-query fixture in `GiftCodeBehaviorV3Test`; API cursor bound test |
| GCX-19 | Full automated gate execution | complete | Local `npm run check` passes; remote CI run `33335631761`, Architecture V3 run `33335631773`, Intelligence, King Perks, CodeQL, and dependency review pass; Gift Code desktop/mobile visual coverage passes in run `33335631755` |
| GCX-20 | Final docs/code reconciliation and closeout | complete | Implementation commit `78fb1234a0471dd020c7f651a3e4d468f22b2c34`; final branch tip and this ledger were verified through the GitHub branch/file APIs; stale-path/TODO/route/scheduler/flag searches reconciled |

A row becomes `complete` only when documented behavior, integration, authorization, UX where applicable, tests, and operational behavior are present. No migration/backfill or compatibility work is intentionally retained for the previous Gift Code implementation.

### Closeout exception

The repository-wide Visual Regression workflow remains red for six pre-existing, non-Gift-Code cases: ApplicationShell desktop/mobile, CapabilityAcceptanceMatrix desktop/mobile, and ScreenshotIntake desktop/mobile. The Gift Code visual test passes on both viewports in run `33335631755`; reconciling those unrelated baselines is owned by their respective capabilities and is not a Gift Code dependency.

## 14. Acceptance criteria

The extension is acceptable only when automated coverage demonstrates:

- spoofed official source rejection;
- duplicate and conflicting provenance preservation;
- malicious/misleading source URL rejection or quarantine;
- a single user cannot globally invalidate a code;
- conflicting expiry claims remain unresolved/disputed until qualified;
- `valid -> disputed -> valid` emits all revisions;
- concurrent submission/moderation/redemption is safe;
- foreign/revoked Governor ownership is rejected at mutation time;
- multi-Governor continuation and partial failure are resumable;
- terminal outcomes stay terminal and retries are bounded;
- disabled notification channels are respected;
- duplicate sweeps do not duplicate unchanged delivery while changed revisions do deliver;
- ingestion replay is idempotent;
- parser failures and revoked sources quarantine;
- API authorization, cursor pagination and response bounds hold;
- webhook versioning, scoping, signing, retry and replay hold;
- Gift Code catalogue/detail/guided redemption are accessible on desktop and mobile;
- large-history fixtures remain within documented query budgets;
- repository search confirms no deprecated Gift Code resolver, shadow comparison field, legacy evidence classification, report-route shim, migration backfill, or compatibility API alias remains;
- all applicable architecture, PHP, static-analysis, TypeScript, localization, accessibility, and Playwright gates pass.

## 15. Delivery sequence

For the fresh-schema redeployment, implement in this order:

1. Product/ADR reconciliation and removal of migration-compatibility requirements.
2. Canonical Gift Code/source/evidence/moderation/curator schema in create migrations.
3. Canonical trust resolver and transition revisions; delete old/shadow resolver code.
4. Governed moderation workflow.
5. Guided multi-Governor redemption; delete deprecated report path.
6. Availability, expiry, and trust-change delivery.
7. Approved-source ingestion.
8. Paginated catalogue, dashboard, canonical API, and webhook updates.
9. Evidence-gated reward/applicability framework.
10. Full legacy/shim search, automated verification, product-doc reconciliation, and delivery-ledger closeout.

A dependency-driven sequence adjustment must first be recorded here with the reason and resulting order.
