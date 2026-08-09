# Kingdoms player snapshots

[← Kingdoms roster](kingdoms-roster.md)

**Status:** Accepted as part of `KINGDOMS-001`  
**Scope:** [Kingdoms roster intelligence increment](../product/kingdoms-roster-intelligence-increment.md)  
**Acceptance evidence:** [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md)

This guide defines the current time-series observation contract. Snapshot history is append-oriented, alliance-scoped and the source of truth for current/stale/missing projection and roster trends. Observations may be accepted manually or through the controlled CSV workflow.

## Ownership and identity

A snapshot belongs to one Alliance and references both the Alliance roster entry and the neutral KingdomPlayer represented by that roster entry.

```text
Kingdom
  └─ KingdomPlayer                 global neutral identity
       └─ AllianceRosterEntry      alliance-owned roster relationship
            └─ PlayerSnapshot      alliance-owned historical observation
```

The shared KingdomPlayer reference never authorizes access to another Alliance's observations. Every snapshot read and mutation begins from the active Alliance boundary.

## Persisted observation

`PlayerSnapshot` records:

- `alliance_id`;
- `roster_entry_id`;
- `kingdom_player_id`;
- actor provenance for the user who accepted the observation;
- observed player name;
- power as a signed 64-bit integer;
- optional progression/level text;
- optional observed game-side alliance/tag;
- capture timestamp;
- source/provenance (`manual` or `csv`);
- optional `roster_import_id` for CSV-origin observations;
- a deterministic idempotency key; and
- created/updated timestamps.

Power is validated as a non-negative decimal integer no greater than `9223372036854775807`. Server responses serialize power as a decimal string so browser code does not introduce floating-point precision loss.

CSV provenance extends the same snapshot model rather than creating a second history store. Historical manual observations remain unchanged when an import later updates the roster.

## Append-only behavior

Recording a snapshot creates a historical row. Normal roster edits, membership-link changes, CSV roster updates and mark-left operations do not rewrite or delete existing snapshots.

There is no normal snapshot edit or delete route. Corrections are represented by a later accepted observation rather than silently rewriting history.

Alliance lifecycle deletion may cascade tenant-owned data according to the existing platform lifecycle contract; that is separate from normal roster management.

## Idempotency

The recorder computes a SHA-256 idempotency key from the canonical accepted observation:

- Alliance ID;
- roster-entry ID;
- KingdomPlayer ID;
- observed name;
- canonical power value;
- optional progression/level;
- optional observed alliance/tag;
- canonical UTC capture time; and
- source.

The actor is intentionally not part of observation identity. An exact retry of the same accepted observation returns the existing snapshot and does not create duplicate audit or outbox records.

Changing the capture timestamp produces a distinct observation even when all game values are unchanged. This preserves legitimate repeated observations over time. CSV batch idempotency additionally prevents a committed import from being applied twice.

## Latest-observation projection

Historical snapshots remain the source of truth. Roster views project the latest snapshot through `PlayerSnapshotQuery` rather than copying power, progression or observed alliance/tag into mutable roster columns.

"Latest" is selected by:

1. greatest `captured_at`; then
2. greatest snapshot ULID as a deterministic tie-breaker.

Creation order therefore does not override a later capture timestamp. A historical observation entered after a newer observation cannot become current merely because it was inserted later.

## Freshness semantics

The current threshold is **30 days**:

- **Current** — at least one snapshot exists with `captured_at` within the last 30 days.
- **Stale** — snapshot history exists, but no snapshot was captured within the last 30 days.
- **Missing** — no snapshot exists for the roster entry.

`AllianceRosterEntry.last_observed_at` remains roster-maintenance metadata. It is not the source for snapshot freshness or current power.

## Authorization and visibility

Snapshot history requires the normal authenticated, verified active-Alliance context plus `alliance.view`.

Recording a manual snapshot requires `kingdoms.manage` plus recent password confirmation. CSV preview/confirmation has the same management/password-assurance boundary and records snapshots through the same domain action.

Built-in Owner, Leader and Officer roles receive `kingdoms.manage`; custom-role permission union remains authoritative.

Member-visible snapshot data includes game-facing observation fields and capture/source metadata. Actor identity and import-management metadata are omitted from ordinary member payloads. No manager notes, membership email addresses or other management-only roster fields are introduced into the member snapshot contract.

## HTTP/UI contract

Current routes include:

- `GET /alliance/roster/{entry}/history` — member-visible tenant-scoped history and latest observation;
- `POST /alliance/roster/{entry}/snapshots` — password-confirmed manager mutation.

The history workspace shows at most the latest 250 observations, newest capture first. Managers receive the recording form on the same page.

The main roster projects latest observed name, power, progression/level, observed alliance/tag and capture timestamp.

## Audit and durable events

A newly accepted snapshot records audit event `kingdoms.player_snapshot_recorded` and a matching transactional-outbox event.

An idempotent retry that resolves to an existing snapshot emits neither a second audit record nor a second outbox message. The event payload identifies the snapshot, roster entry, KingdomPlayer, capture time and source; private roster notes are not included.

`kingdoms.*` outbox events are internal durability events for `KINGDOMS-001` and are excluded from generic external webhook fan-out until a separately approved integration contract exposes them.

## Tenant-isolation invariants

- Submitted roster-entry IDs are re-resolved under the active `alliance_id` before recording.
- History queries require both active Alliance and roster-entry ownership.
- Latest-snapshot projection begins from Alliance-scoped roster entries and Alliance-scoped snapshot predicates.
- CSV import provenance never changes the owning Alliance boundary.
- Sharing a Kingdom or KingdomPlayer cannot expose another Alliance's snapshot history.
- Snapshot actor/import provenance does not grant cross-tenant access.

Cross-alliance object-ID tampering fails closed with no observation disclosure or mutation.

## Related accepted contracts and boundaries

- [Kingdoms roster](kingdoms-roster.md)
- [Kingdoms roster intelligence](kingdoms-intelligence.md)
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md)
- [Whole-increment security review](../security/kingdoms-roster-intelligence-security-review.md)

Snapshot editing/deletion, public Kingdoms API/webhook exposure, transfer/diplomacy workflows, cross-alliance rankings and automated game-data ingestion remain outside `KINGDOMS-001`.
