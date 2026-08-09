# Kingdoms alliance intelligence operations

[← Operations documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice A / `K3-P1` candidate

## Runtime ownership

Slice A is a synchronous first-party web workflow using PostgreSQL, the existing audit recorder and transactional outbox. It introduces no Kingdoms scheduler, queue worker, crawler, scraper, bot or automated game-data ingestion process.

Routes:

- member-safe tracked alliance list: `/alliance/kingdom-alliances`;
- manager tracking workspace: `/alliance/kingdom-alliances/manage`;
- mutations use the existing recent-password-confirmation middleware.

## Expected durable state

Operators may diagnose Slice A through:

- `kingdom_alliances` neutral identity rows;
- `tracked_kingdom_alliances` tenant tracking rows;
- `audit_events`; and
- `outbox_messages`.

A successful tracking start creates one tenant tracking row and either reuses one same-Kingdom stable-ID neutral reference or creates a new neutral reference. Name/tag equality alone is never evidence that a merge should have occurred.

## Failure modes and recovery

### Alliance has no current Kingdom

Tracking creation fails. Configure the Alliance Kingdom through the accepted Alliance-setting workflow before retrying.

### Stable ID conflict

Assigning a stable game alliance ID that already belongs to another neutral reference in the same Kingdom fails closed. Do not repair this by rewriting IDs directly in PostgreSQL. Confirm the game identity and resolve the duplicate through an explicitly reviewed future correction workflow if needed.

### Alliance Kingdom changed

Historical tracking remains readable, but normal updates fail because the captured Kingdom no longer equals the Alliance current Kingdom. Archive the stale tracking row if it should no longer be active, then create deliberate tracking under the new Kingdom.

Do not rewrite `tracked_kingdom_alliances.kingdom_id` to make a stale record appear current.

### Duplicate active tracking

The application serializes tracking creation on the Alliance row and PostgreSQL enforces one active tracking row per Alliance + neutral reference. If a unique violation appears outside the normal action path, investigate concurrent/direct-write behavior rather than deleting history.

### Outbox publication failure

The business transaction remains committed with an unpublished outbox row. Recover through the existing `outbox:publish` workflow; do not manufacture `published_at` or recreate the tracking action.

## Privacy/diagnostics

Manager tracking notes are private tenant data. Do not copy note text into logs, tickets, metrics labels, audit metadata or outbox payloads.

Structured diagnostics should use bounded identifiers/state such as Alliance ID, tracked record ID, neutral reference ID, captured Kingdom ID and event type.

## Migration/rollback

Slice A migration:

`2026_08_09_140000_create_kingdom_alliance_tracking.php`

It depends on the accepted Kingdom/Alliance foundation and sits above the complete `KINGDOMS-002` migration chain. Rollback order begins with K3 alliance tracking, then proceeds through K2 and K1 dependencies.

Rollback drops tenant tracking before neutral references. There is no compatibility shim or future-slice schema to preserve.

## Stop conditions

Escalate instead of applying manual data fixes when recovery would require:

- changing a stable game alliance ID already assigned to a neutral reference;
- merging references solely because names/tags match;
- changing another tenant's tracking row;
- rewriting captured Kingdom context after drift;
- exposing manager notes to ordinary members/logs/outbox; or
- bypassing `kingdoms.manage` / password confirmation.
