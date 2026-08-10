# Kingdoms player snapshots

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as part of `KINGDOMS-001`  
**Owning domain:** `Kingdoms`

## 1. Purpose

This document defines the current time-series player-observation contract. Snapshot history is append-oriented, Alliance-scoped, and the source of truth for current/stale/missing projection and roster trends. Observations may be accepted manually or through the controlled CSV workflow.

## 2. Scope and non-scope

In scope:

- persisted `PlayerSnapshot` observation fields/provenance;
- append-only history;
- exact-retry idempotency;
- latest-observation projection;
- 30-day freshness semantics;
- member/manager visibility; and
- audit/outbox behavior.

Out of scope:

- destructive normal snapshot edit/delete;
- aggregate/trend calculation details (see [Intelligence](intelligence.md));
- generic automated ingestion; and
- public Kingdoms API/webhook exposure.

## 3. Model and state

A snapshot belongs to one Alliance and references the Alliance roster entry plus neutral KingdomPlayer represented by that roster entry:

```text
Kingdom
  └─ KingdomPlayer                 global neutral identity
       └─ AllianceRosterEntry      Alliance-owned roster relationship
            └─ PlayerSnapshot      Alliance-owned historical observation
```

`PlayerSnapshot` records:

- `alliance_id`;
- `roster_entry_id`;
- `kingdom_player_id`;
- actor provenance for the User who accepted the observation;
- observed player name;
- power as signed 64-bit integer;
- optional progression/level text;
- optional observed game-side Alliance/tag;
- capture timestamp;
- source (`manual` or `csv`);
- optional `roster_import_id` for CSV-origin observations;
- deterministic idempotency key; and
- timestamps.

Power is a non-negative decimal integer no greater than `9223372036854775807`. Server responses serialize power as a decimal string so browser JavaScript cannot lose integer precision.

## 4. Invariants

1. Snapshot history is append-oriented.
2. Normal roster edits, membership-link changes, CSV roster updates, mark-left, and transfer completion do not rewrite existing snapshots.
3. Corrections are represented by a later accepted observation, not silent historical rewrite.
4. Global KingdomPlayer reference never authorizes another tenant's history.
5. Exact retries return the existing observation and do not duplicate audit/outbox evidence.
6. Changing capture timestamp creates a distinct observation even if values are unchanged.
7. Current projection is selected by greatest `captured_at`, then greatest snapshot ULID as deterministic tie-breaker.
8. Insertion time never overrides a newer capture timestamp.
9. Missing snapshot is distinct from recorded zero power.

## 5. Workflows

### Record manual snapshot

An authorized manager records the observed player state under the active Alliance and roster entry. The recorder validates ranges, canonicalizes the accepted values/time, calculates deterministic idempotency, and appends the observation.

### Record CSV-origin snapshot

Controlled CSV confirmation uses the same snapshot action, setting source `csv` and `roster_import_id` provenance instead of creating a parallel history model.

### Exact retry

The recorder computes SHA-256 identity from:

- Alliance ID;
- roster-entry ID;
- KingdomPlayer ID;
- observed name;
- canonical power;
- optional progression/level;
- optional observed Alliance/tag;
- canonical UTC capture time; and
- source.

Actor is intentionally excluded from observation identity.

### Latest projection

Roster views project latest accepted snapshot fields through `PlayerSnapshotQuery` rather than copying current power/progression/tag into mutable roster columns.

### Freshness

The current threshold is **30 days**:

- **Current** — latest snapshot captured within 30 days.
- **Stale** — history exists, latest snapshot older than 30 days.
- **Missing** — no snapshot exists.

`AllianceRosterEntry.last_observed_at` is roster-maintenance metadata and is not the source for snapshot freshness/current power.

## 6. Authorization, tenancy and privacy

History reads require authenticated/verified active-Alliance context plus `alliance.view`.

Manual recording and CSV confirmation require `kingdoms.manage` plus recent password confirmation.

Member-visible history includes game observation fields and capture/source metadata. Actor identity and import-management metadata are omitted from ordinary member payloads. Private roster notes/membership emails are not part of snapshot member output.

Submitted roster-entry IDs and all history/latest queries are constrained by the active Alliance.

## 7. Persistence and query semantics

Historical snapshots are the source of truth. No second mutable current-power table is maintained by this contract.

The member history workspace returns at most the latest **250** observations ordered by capture time newest-first. Latest projection and trend queries remain tenant-scoped.

## 8. Events/integrations/background processing

A newly accepted observation records audit event `kingdoms.player_snapshot_recorded` plus matching internal outbox event.

An idempotent retry emits no second audit/outbox record. Event metadata identifies safe observation identifiers/capture/source and excludes private roster notes.

No snapshot-specific scheduler/worker exists. `kingdoms.*` events remain internal, not public webhook contracts.

## 9. Failure, idempotency and concurrency

- Power outside the accepted non-negative signed-64-bit range is rejected.
- Future/invalid timestamps are constrained by the owning input workflow.
- Exact canonical retry returns existing state.
- Cross-Alliance roster/history object IDs fail closed.
- A historical observation inserted later cannot become current if its capture timestamp is older.

## 10. Operations and observability

Operators can use source, capture time, actor/import provenance (where authorized), freshness state, and audit/outbox evidence to distinguish missing history from delivery/input failures.

See [Kingdoms roster intelligence operations](../../operations/kingdoms-roster-intelligence.md).

## 11. Tests and validation

Accepted validation covers:

- append-only history;
- exact-retry idempotency;
- latest-by-capture ordering/tie-break;
- current/stale/missing projection;
- decimal-string power safety;
- member/manager privacy split;
- tenant isolation; and
- manual/CSV provenance sharing one history model.

See the [KINGDOMS-001 exit report](../../product/kingdoms-roster-intelligence-exit-report.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Roster](roster.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [KINGDOMS-001 security review](../../security/kingdoms-roster-intelligence-security-review.md)
