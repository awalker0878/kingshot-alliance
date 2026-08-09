# Kingdoms alliance intelligence operations

[← Operations documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice B / `K3-P2` candidate

## Runtime ownership

Slices A and B are synchronous first-party web workflows using PostgreSQL, the existing audit recorder and transactional outbox. They introduce no Kingdoms scheduler, queue worker, crawler, scraper, OCR process, bot or automated game-data ingestion process.

Routes:

- member-safe tracked alliance list: `/alliance/kingdom-alliances`;
- manager tracking workspace: `/alliance/kingdom-alliances/manage`;
- member/manager observation history: `/alliance/kingdom-alliances/{tracking}/history`;
- password-confirmed observation record/correction/invalidation mutations under `/alliance/kingdom-alliances/{tracking}/observations`.

## Expected durable state

Operators may diagnose current K3 state through:

- `kingdom_alliances` neutral identity rows;
- `tracked_kingdom_alliances` tenant tracking rows;
- `kingdom_alliance_observations` tenant factual history;
- `audit_events`; and
- `outbox_messages`.

Observation history is append-oriented. Do not treat multiple rows for one tracked alliance as duplicates merely because observed values match; changing capture time represents a legitimate later observation.

## Observation idempotency

Exact retry identity is deterministic SHA-256 over the canonical accepted observation. A repeated request with the same Alliance/tracking/reference, facts, capture time, source and correction target resolves to the existing row and emits no duplicate audit/outbox event.

When diagnosing apparent duplicates, compare capture time and canonical facts before modifying data. Do not delete rows manually to enforce value-level uniqueness.

## Latest/freshness projection

Latest accepted observation is selected by greatest `captured_at`, then greatest observation ULID. Invalidated rows are excluded.

Freshness uses the accepted Kingdoms 30-day threshold:

- current: latest accepted capture within 30 days;
- stale: accepted history exists but latest capture is older than 30 days;
- missing: no accepted observation exists.

The main tracking list loads only the latest accepted observation per row. History is bounded to the latest 250 rows.

## Corrections and invalidations

A correction appends a replacement observation and invalidates the original in one transaction. The original row remains historical and the replacement records the correction link.

Standalone invalidation marks the existing row with invalidation time/actor/reason. Repeating the invalidation is idempotent and should not create a second durability event.

Do not directly update historical observation facts in PostgreSQL to correct a record. Use the correction/invalidation action so attribution, history, neutral identity projection and audit/outbox evidence remain coherent.

## Failure modes and recovery

### Alliance has no current Kingdom

Tracking creation fails. Configure the Alliance Kingdom through the accepted Alliance-setting workflow before retrying.

### Stable ID conflict

Assigning a stable game alliance ID that already belongs to another neutral reference in the same Kingdom fails closed. Do not repair this by rewriting IDs directly in PostgreSQL.

### Alliance Kingdom changed

Historical tracking and observation history remain readable, but normal identity/tracking/observation mutations fail because captured Kingdom context no longer equals the Alliance current Kingdom.

Archive stale tracking if appropriate, then deliberately establish tracking under the new current Kingdom. Do not rewrite `tracked_kingdom_alliances.kingdom_id` or observation foreign keys to make old history appear current.

### Observation capture too far in the future

The mutation fails if `captured_at` is more than five minutes ahead of server time. Confirm the operator device clock/time zone and retry with the actual game observation time; do not bypass the validation.

### Invalid correction target

Correction requires an accepted observation owned by the same Alliance and tracking row. Cross-tenant, cross-tracking or already-invalidated targets fail closed.

### Outbox publication failure

The business transaction remains committed with an unpublished outbox row. Recover through the existing `outbox:publish` workflow; do not recreate the business mutation solely to publish its event.

## Privacy/diagnostics

Manager tracking notes and observation correction/invalidation reasons are private tenant data. Do not copy those values into logs, tickets, metrics labels, audit metadata or outbox payloads.

Structured diagnostics should use bounded identifiers/state such as Alliance ID, tracked record ID, observation ID, neutral reference ID, captured Kingdom ID, capture time, freshness state and event type.

Member-facing payloads deliberately omit observation IDs, actor identity and invalidation detail.

## Migration/rollback

Current K3 migrations:

1. `2026_08_09_140000_create_kingdom_alliance_tracking.php`;
2. `2026_08_09_150000_create_kingdom_alliance_observations.php`.

Rollback order is the reverse: observations first, then tracking/neutral references, then the accepted K2/K1 chain.

The observation migration has no compatibility shim and no diplomacy/contact/scoring/ingestion/public-integration placeholders.

## Stop conditions

Escalate instead of applying manual data fixes when recovery would require:

- changing a stable game alliance ID already assigned to a neutral reference;
- merging references solely because names/tags match;
- changing another tenant's tracking or observation row;
- rewriting captured Kingdom context after drift;
- editing/deleting historical observation facts instead of correcting/invalidation;
- exposing manager notes, actors or invalidation reasons to ordinary members/logs/outbox;
- calculating threat/ranking/diplomacy behavior from factual observations; or
- bypassing `kingdoms.manage` / password confirmation.
