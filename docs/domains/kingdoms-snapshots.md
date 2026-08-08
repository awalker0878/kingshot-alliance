# Kingdoms player snapshots

[← Kingdoms roster](kingdoms-roster.md)

**Status:** `KINGDOMS-001` Slice C1 / `K1-P3` validated implementation candidate  
**Dependency:** validated Slice B roster candidate  
**Approved scope:** [Kingdoms roster intelligence increment](../product/kingdoms-roster-intelligence-increment.md)

This guide defines the validated Slice C1 implementation contract for time-series game observations. Its protected implementation gate has passed; the slice remains a review candidate until accepted into the dependency stack. Snapshot history is append-oriented and alliance-scoped. It does not implement the aggregate intelligence/trend calculations owned by `K1-P4`.

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
- the actor who accepted the manual observation;
- observed player name;
- power as a signed 64-bit integer;
- optional progression/level text;
- optional observed game-side alliance/tag;
- capture timestamp;
- source/provenance (`manual` in Slice C1);
- a deterministic idempotency key; and
- created/updated timestamps.

Power is validated as a non-negative decimal integer no greater than `9223372036854775807`. Server responses serialize power as a decimal string so browser code does not introduce floating-point precision loss.

CSV source/provenance is not implemented in Slice C1. `K1-P5` will add the import workflow and its provenance contract without changing historical manual observations.

## Append-only behavior

Recording a snapshot creates a historical row. Normal roster edits, membership-link changes and mark-left operations do not rewrite or delete existing snapshots.

Slice C1 provides no snapshot edit or delete route. Corrections are represented by a later accepted observation rather than silently rewriting history.

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

Changing the capture timestamp produces a distinct observation even when all game values are unchanged. This preserves legitimate repeated observations over time.

## Latest-observation projection

Historical snapshots remain the source of truth. Roster views project the latest snapshot through `PlayerSnapshotQuery` rather than copying power, progression or observed alliance/tag into mutable roster columns.

"Latest" is selected by:

1. greatest `captured_at`; then
2. greatest snapshot ULID as a deterministic tie-breaker.

Creation order therefore does not override a later capture timestamp. A historical observation entered after a newer observation cannot become current merely because it was inserted later.

## Freshness semantics

Slice C1 replaces the temporary Slice B manual-roster freshness interpretation with snapshot-backed semantics.

The current threshold is **30 days**:

- **Current** — at least one snapshot exists with `captured_at` within the last 30 days.
- **Stale** — snapshot history exists, but no snapshot was captured within the last 30 days.
- **Missing** — no snapshot exists for the roster entry.

`AllianceRosterEntry.last_observed_at` remains roster-maintenance metadata. It is not the source for snapshot freshness or current power.

## Authorization and visibility

Snapshot history requires the normal authenticated, verified active-Alliance context plus `alliance.view`.

Recording a manual snapshot requires:

- `kingdoms.manage`; and
- recent password confirmation at the HTTP route.

Built-in Owner, Leader and Officer roles receive `kingdoms.manage`; custom-role permission union remains authoritative.

Member-visible snapshot data includes game-facing observation fields and capture/source metadata. Actor identity is management-only and is omitted from ordinary member payloads.

No manager notes, membership email addresses or other management-only roster fields are introduced into the member snapshot contract.

## HTTP/UI contract

Current routes:

- `GET /alliance/roster/{entry}/history` — member-visible tenant-scoped history and latest observation;
- `POST /alliance/roster/{entry}/snapshots` — password-confirmed manager mutation.

The history workspace shows at most the latest 250 observations, newest capture first. Managers receive the recording form on the same page.

The main roster projects latest:

- observed name;
- power;
- progression/level;
- observed alliance/tag; and
- capture timestamp.

## Audit and durable events

A newly accepted snapshot records:

- audit event `kingdoms.player_snapshot_recorded`; and
- matching transactional-outbox event `kingdoms.player_snapshot_recorded`.

An idempotent retry that resolves to an existing snapshot emits neither a second audit record nor a second outbox message.

The event payload identifies the snapshot, roster entry, KingdomPlayer, capture time and source. Private roster notes are not included.

## Tenant-isolation invariants

- Submitted roster-entry IDs are re-resolved under the active `alliance_id` before recording.
- History queries require both active Alliance and roster-entry ownership.
- Latest-snapshot projection begins from Alliance-scoped roster entries and Alliance-scoped snapshot predicates.
- Sharing a Kingdom or KingdomPlayer cannot expose another Alliance's snapshot history.
- Snapshot actor provenance does not grant cross-tenant access.

Cross-alliance object-ID tampering fails closed with no observation disclosure or mutation.

## Explicit deferrals

Slice C1 does not implement:

- aggregate total/average/median power;
- 7-day or 30-day power-change calculations;
- notable individual growth/decline views;
- roster intelligence scoring or dashboard (`K1-P4`);
- CSV import/export or import provenance (`K1-P5`);
- snapshot editing/deletion;
- public snapshot API/webhook exposure; or
- automated game-data ingestion.

Those capabilities must not be inferred from the snapshot foundation.
