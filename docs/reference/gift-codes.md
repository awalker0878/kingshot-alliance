# Gift Codes

Status: Current complete capability — fresh-schema canonical implementation

Gift Codes are owned by `GameWorld/GiftCodes`. Global catalogue truth, account-personal workflow state, persistent redemption-session state and per-Governor redemption truth are deliberately separate.

## Catalogue trust

`gift_codes` is a derived catalogue projection. Raw source labels, URLs, authority claims, expiry claims, reward claims and applicability claims live in append-only `gift_code_provenances`. Ordinary account submissions may use only `manual` or `community`; both create unverified community evidence and cannot claim official authority.

Approved sources are platform-owned records with a canonical domain, classification, verification method, policy revision, ingestion eligibility and optional installed adapter key. A registered source observation is verified only when its current policy permits automatic verification and the adapter reports that verification passed. Domain mismatch is rejected, failed policy is quarantined, and source revocation schedules bounded re-reconciliation without rewriting evidence.

The canonical resolver produces `pending`, `valid`, `invalid`, `expired`, `disputed`, or `quarantined` with a stable reason code and supporting evidence IDs. Every material trust transition increments `status_revision`; every accepted expiry change increments `expires_revision`. Conflicting qualified expiry claims remain disputed until an authorized moderation decision resolves them. There is no legacy resolver, shadow mode, backfill path or compatibility API.

Reward and applicability projections are separate derived facts. They are published only from qualified, non-conflicting evidence. Otherwise the UI and API return an explicit unknown/conflict state. A Governor-specific `wrong_kingdom` result never becomes a global applicability rule.

## Moderation and source administration

`/platform/gift-codes` is protected by authentication, verified email, MFA-backed Gift Code curator authority and recent password confirmation. Review queues cover pending, disputed, conflicting-expiry, suspicious-source, heavily-reported, platform-quarantined, ingestion-quarantined and source-revocation cases. Decisions support verify, reject, quarantine, restore, correct expiry and resolve dispute. Required-reason actions, evidence references, audit entries and outbox messages are preserved.

Bulk moderation is limited to 50 Gift Codes, previews eligibility before confirmation, reauthorizes every item and reports partial failures. Only MFA-protected Platform Administrators can register/revise/revoke approved sources or grant/revoke the narrower Gift Code curator role. Alliance R4/R5 authority does not grant either capability.

The research-backed source catalogue is deliberately separate from the approved-source registry. `GiftCodeResearchedSourceCatalogue` records staged candidates and intended transports only. A catalogue entry never creates a `GiftCodeSourceRegistry` row, grants `official` classification, enables `auto_verify`, or enables ingestion. Those remain explicit Platform Administrator source-policy decisions.

## Guided Governor redemption

The catalogue is cursor-paginated with active, pending-review, disputed, expired, completed and history views plus code, trust, source, expiry and Governor-outcome filters. Detail reads contain the full evidence, decision and owned-Governor redemption history without loading those histories on every index row.

An account can prepare the current Governor, all owned Governors, failed/incomplete Governors or an explicit owned subset. The UI walks one Governor at a time and shows Governor name, Kingdom and in-game Player ID before offering copy-Player-ID, copy-code and the official Century Games handoff. The application never calls an undocumented redemption endpoint.

The outcome vocabulary is `awaiting_confirmation`, `redeemed`, `already_redeemed`, `invalid_code`, `expired`, `wrong_kingdom`, `rate_limited`, `transient_failure`, and `permanent_failure`. A negative observation requires a recorded official handoff for that Gift Code/Governor pair. Terminal success cannot be overwritten. Retryable outcomes use bounded exponential backoff. Every submitted Player ID is only a selector; the server resolves current account ownership before mutation.

## Personal workspace and persistent redemption runs

`/gift-codes/workspace` is the account-personal action surface. Its New, Ready to redeem, Expiring soon, Retry ready, In progress, Snoozed and Completed views are derived from current global catalogue state, all currently owned Governors, canonical per-Governor redemption state and the account's personal Gift Code state. Personal `pinned`, `snoozed`, `dismissed` and reminder values never alter global trust, expiry, reward, applicability or provenance.

A redemption run is account-owned and may contain many Gift Codes across many owned Governors. Session items are workflow orchestration only; the existing `gift_code_redemptions` row remains authoritative for the Gift Code/Governor outcome. Session construction de-duplicates selectors and resolves current ownership, canonical trust/expiry, qualified applicability, terminal success and retry timing server-side. A session item never treats a submitted Player ID as proof of ownership.

Every prepare/result/skip/reconcile operation reauthorizes the session and current Governor ownership. Trust or expiry changes can make a pending item unavailable before execution. Runs survive reload/device changes through persisted session state and support skip, retry, resume and abandon without creating a second redemption truth store.

## Structured rewards and redemption signals

Qualified reward evidence is presented through a structured display projection for resource, currency, speedup, hero-item, chest and other source-backed items. Unqualified or conflicting facts remain explicit unknown/conflict states; presentation never promotes unsupported evidence.

Recent redemption signals aggregate observed Governor outcomes only after configured minimum sample and distinct-account thresholds pass. Signals may show recent success/failure distribution and timing, but they are observational intelligence only. Many Governors owned by one account do not become many independent accounts, and aggregate signals never independently establish canonical validity, invalidity, expiry or Kingdom applicability.

## Alliance coverage and contributor projections

Alliance Gift Code coverage requires the explicit `gift_codes.coverage` permission. The default `Gift Code Coordinator` specialist role carries that permission and is delegated/revoked through existing Alliance role management; R4/R5 rank alone does not grant coverage. Coverage is aggregate-only: the projection reports the number of active Governors with a usable in-game Player ID plus, per currently valid Gift Code, completed, incomplete, retry-ready and unknown counts. `unknown` represents active members whose Governor lacks a usable in-game Player ID. The surface does not expose member names, Player IDs or individual redemption history.

Alliance coverage never grants platform Gift Code moderation, curator or approved-source administration authority.

Contributor projections are derived from community submission history and moderation outcomes. They can support prioritization and abuse controls, but cannot convert community/manual evidence into registered-source or official authority.

## Notifications and operations

`gift_codes.notification_fanout` controls `gift_code.available`, `gift_code.expiring`, and `gift_code.trust_changed`. The workspace adds source-owned readiness/reminder semantics while still submitting one logical `NotificationIntent` to Communications. GiftCodes chooses factual content, recipient eligibility, source subject/idempotency identity and generic urgency; Communications owns endpoint selection, verified email resolution, channel preferences, quiet hours, immediate/digest timing, provider retry and delivery diagnostics.

Workspace notifications can consolidate several actionable Gift Codes into one logical message and deep-link back to the workspace/session. Due personal reminders are bounded and idempotent. Current ownership/redemption state is rechecked before materialization so a stale reminder cannot re-authorize an obsolete action.

`gift-codes:maintain --limit=500 --cycle` expires due codes and advances bounded expiry and transition notification cursors. It runs every 15 minutes and processes a configured maximum number of transition campaigns per invocation. The JSON receipt exposes examined, eligible, delivery, replay, skip, cursor and duration counters.

Workspace operations are scheduled independently of catalogue maintenance: due personal reminders run every minute, consolidated actionable-workspace notifications run every 15 minutes, and bounded contributor projections rebuild hourly. Each schedule uses single-server and overlap protection; the actions themselves remain idempotent/bounded.

`gift_codes.approved_source_ingestion` controls scheduled source acquisition. `gift-codes:ingest-approved-sources --limit=25 --cycle` runs every 15 minutes; `--source=` provides targeted operator replay. `gift-codes:reconcile-source-policies --limit=500` runs every five minutes. Source and bounded recent-run health expose last attempt/success/failure, stale state, accepted/duplicate/quarantined counts, stable failure codes and reviewable failure detail. Parser/unsupported-format and observation-policy failures are quarantined; source-retrieval failures remain explicit failures.

The installed pull-adapter set is:

- `json-feed-v1` — a bounded HTTPS JSON document with explicit observation fields;
- `rss-atom-v1` — bounded RSS or Atom XML containing explicit direct-child Gift Code elements; it does not infer codes from titles, descriptions or nested content markup;
- `structured-html-v1` — bounded approved HTML containing explicit machine-readable `data-gift-code*` attributes; it does not scrape arbitrary prose;
- `x-api-v2-kingshot-v1` — the documented X API v2 user-post timeline for a separately confirmed official account, with author-identity verification and an explicit `Gift Code:`/`Redeem Code:` line grammar;
- `century-games-kingshot-news-rss-v1` — a Century Games provider-permission-gated RSS/Atom parser requiring an agreed Gift Code category plus an explicit Gift Code label contract.

The three generic document adapters are restricted to the registered public canonical hostname plus an absolute source `feed_path`, disable redirects, preserve source/retrieval/parser versions, create content fingerprints/raw-evidence references and feed the same `IngestApprovedGiftCodeObservation` action. Missing RSS/Atom or structured-HTML assertion metadata normalizes to canonical `available`. RSS/Atom parsing disables network XML access and rejects document type/entity declarations. Exceeding configured document or observation bounds is a reviewable parser failure rather than silent truncation.

The provider-specific Stage 1 adapters use fixed documented/provider-agreed endpoints rather than arbitrary URL policy. X credentials are server-side configuration only; source policy holds the confirmed X user id and username. Century Games Kingshot-news ingestion cannot be enabled until source policy records confirmed provider permission, an approved feed path and the agreed Gift Code category. Both adapters still feed the canonical ingestion action, and `verificationPassed` is not sufficient for verified evidence unless source policy separately enables `auto_verify`.

When `gift_codes.source_webhook_ingestion` is enabled, a registered source may use the signed internal source webhook transport. Signature verification, timestamp/replay protection, batch bounds and active source policy are enforced before the payload enters the same approved-source observation action used by scheduled adapters. The webhook transport does not create a new evidence/trust path.

The researched rollout is staged separately from installed transport availability: Stage 0 prioritizes cooperative Century Games webhook/JSON/RSS; Stage 1 installs official X and permission-gated Century Games news adapters; Stage 2 holds Official Wiki structured feeds and legitimate Discord bots for later provider/platform work; Stage 3 keeps Kingshot.net, Optimizer, Mastery, Atlas and separately registered editorial publishers as independent corroboration; Stage 4 reserves Facebook, Instagram, Reddit and YouTube for documented platform-dependent redundancy/discovery. Catalogue presence alone never changes source authority.

Generic prose scraping, Gift Code Center reverse engineering, Discord self-bots/user-token automation, undocumented provider automation and shared editorial source identities that could defeat the independent-source threshold are excluded.

Platform Administration diagnostics include privacy-safe Gift Code workspace feature/session/item/reminder/contributor/source counters. They do not expose account IDs, Player IDs or Governor names. Communications provider diagnostics remain Communications-owned.

The generic development defaults remain off. Hosted staging configuration explicitly enables selected launch features. The trust resolver is not feature-selectable because it is the only deployed trust implementation.

## API and webhooks

`GET /api/v1/gift-codes` requires `gift-codes:read` and returns only verified active, unexpired codes to Alliance credentials. Results are limited to 100 and include opaque cursor metadata, status/reason/revisions, source count, accepted expiry, official handoff URL and qualified-or-unknown reward/applicability fields. Privacy-qualified redemption-signal data may be exposed only when its thresholds pass; private account workspace/session state is not exposed through ordinary Alliance API credentials. Non-active catalogue filters fail closed.

Public global events remain `gift_code.created`, `gift_code.provenance_added`, `gift_code.status_changed`, and `gift_code.expiry_changed`. Gift Code payloads include `version: 1` and `status_revision`; expiry transitions also include `expires_revision`. Personal redemption-session progression is not promoted to a globally subscribable webhook contract. Existing webhook signing, subscription scoping, bounded retry and replay behavior applies.

## Workspace feature controls

Operationally separable workspace controls are `gift_codes.redemption_workspace`, `gift_codes.redemption_intelligence`, `gift_codes.alliance_coverage`, `gift_codes.contributor_reputation`, and `gift_codes.source_webhook_ingestion`. These flags do not select alternative trust/resolver semantics and cannot bypass canonical authorization/evidence paths.

The Gift Code Redemption Workspace & Personalization capability remains current complete. The researched-source rollout extends approved-source acquisition without changing catalogue trust semantics or claiming that research candidates are approved sources.

See [ADR-0004](../architecture/adr/0004-gift-code-trust-from-append-only-evidence.md), the [trust/discovery extension closeout](../product/gift-code-extension-program.md), the [Researched Source Rollout](../product/gift-code-researched-source-rollout.md), the [Redemption Workspace & Personalization contract](../product/gift-code-redemption-workspace.md), its [acceptance matrix](../product/gift-code-redemption-workspace-acceptance.md), its [delivery ledger](../product/gift-code-redemption-workspace-delivery-ledger.md), [API reference](api/README.md), and [event catalogue](events.md).
