# Gift Codes

Status: Current — fresh-schema canonical implementation

Gift Codes are owned by `GameWorld/GiftCodes`. Global catalogue truth and per-Governor redemption are deliberately separate.

## Catalogue trust

`gift_codes` is a derived catalogue projection. Raw source labels, URLs, authority claims, expiry claims, reward claims and applicability claims live in append-only `gift_code_provenances`. Ordinary account submissions may use only `manual` or `community`; both create unverified community evidence and cannot claim official authority.

Approved sources are platform-owned records with a canonical domain, classification, verification method, policy revision, ingestion eligibility and optional installed adapter key. A registered source observation is verified only when its current policy permits automatic verification and the adapter reports that verification passed. Domain mismatch is rejected, failed policy is quarantined, and source revocation schedules bounded re-reconciliation without rewriting evidence.

The canonical resolver produces `pending`, `valid`, `invalid`, `expired`, `disputed`, or `quarantined` with a stable reason code and supporting evidence IDs. Every material trust transition increments `status_revision`; every accepted expiry change increments `expires_revision`. Conflicting qualified expiry claims remain disputed until an authorized moderation decision resolves them. There is no legacy resolver, shadow mode, backfill path or compatibility API.

Reward and applicability projections are separate derived facts. They are published only from qualified, non-conflicting evidence. Otherwise the UI and API return an explicit unknown/conflict state. A Governor-specific `wrong_kingdom` result never becomes a global applicability rule.

## Moderation and source administration

`/platform/gift-codes` is protected by authentication, verified email, MFA-backed Gift Code curator authority and recent password confirmation. Review queues cover pending, disputed, conflicting-expiry, suspicious-source, heavily-reported, platform-quarantined, ingestion-quarantined and source-revocation cases. Decisions support verify, reject, quarantine, restore, correct expiry and resolve dispute. Required-reason actions, evidence references, audit entries and outbox messages are preserved.

Bulk moderation is limited to 50 Gift Codes, previews eligibility before confirmation, reauthorizes every item and reports partial failures. Only MFA-protected Platform Administrators can register/revise/revoke approved sources or grant/revoke the narrower Gift Code curator role. Alliance R4/R5 authority does not grant either capability.

## Guided Governor redemption

The catalogue is cursor-paginated with active, pending-review, disputed, expired, completed and history views plus code, trust, source, expiry and Governor-outcome filters. Detail reads contain the full evidence, decision and owned-Governor redemption history without loading those histories on every index row.

An account can prepare the current Governor, all owned Governors, failed/incomplete Governors or an explicit owned subset. The UI walks one Governor at a time and shows Governor name, Kingdom and in-game Player ID before offering copy-Player-ID, copy-code and the official Century Games handoff. The application never calls an undocumented redemption endpoint.

The outcome vocabulary is `awaiting_confirmation`, `redeemed`, `already_redeemed`, `invalid_code`, `expired`, `wrong_kingdom`, `rate_limited`, `transient_failure`, and `permanent_failure`. A negative observation requires a recorded official handoff for that Gift Code/Governor pair. Terminal success cannot be overwritten. Retryable outcomes use bounded exponential backoff. Every submitted Player ID is only a selector; the server resolves current account ownership before mutation.

## Notifications and operations

`gift_codes.notification_fanout` controls `gift_code.available`, `gift_code.expiring`, and `gift_code.trust_changed`. Fan-out uses Communications preferences and delivery infrastructure, rechecks current account/Governor ownership and redemption state, deep-links to `/gift-codes/{id}`, and keys idempotency by notification type, code, account/Player, status revision, expiry revision and channel.

`gift-codes:maintain --limit=500 --cycle` expires due codes and advances bounded expiry and transition notification cursors. It runs hourly. The JSON receipt exposes examined, eligible, delivery, replay, skip, cursor and duration counters.

`gift_codes.approved_source_ingestion` controls scheduled source acquisition. `gift-codes:ingest-approved-sources --limit=25 --cycle` runs every 15 minutes; `--source=` provides targeted operator replay. `gift-codes:reconcile-source-policies --limit=500` runs every five minutes. Source and run health expose last attempt/success/failure, stale state, accepted/duplicate/quarantined counts and stable failure codes. No adapter ships unless its provider behavior and provenance policy are documented; an absent adapter leaves ingestion safely inactive.

The moderation and ingestion flags default off. Notification fan-out also defaults off. The trust resolver is not feature-selectable because it is the only deployed trust implementation.

## API and webhooks

`GET /api/v1/gift-codes` requires `gift-codes:read` and returns only verified active, unexpired codes to Alliance credentials. Results are limited to 100 and include opaque cursor metadata, status/reason/revisions, source count, accepted expiry, official handoff URL and qualified-or-unknown reward/applicability fields. Non-active catalogue filters fail closed.

Public global events are `gift_code.created`, `gift_code.provenance_added`, `gift_code.status_changed`, and `gift_code.expiry_changed`. Gift Code payloads include `version: 1` and `status_revision`; expiry transitions also include `expires_revision`. Existing webhook signing, subscription scoping, bounded retry and replay behavior applies.

See [ADR-0004](../architecture/adr/0004-gift-code-trust-from-append-only-evidence.md), the [extension closeout](../product/gift-code-extension-program.md), [API reference](api/README.md), and [event catalogue](events.md).
