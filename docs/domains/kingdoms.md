# Kingdoms

[← Domain documentation](README.md)

**Increment:** [`KINGDOMS-001` — Kingdoms roster intelligence](../product/kingdoms-roster-intelligence-increment.md)  
**Current state:** Complete implementation under `K1-P6` acceptance validation  
**Implementation sequence:** [KINGDOMS-001 implementation plan](../product/kingdoms-roster-intelligence-implementation-plan.md)

`Kingdoms` owns Kingshot game-world reference identity plus alliance-owned roster observations, history, controlled spreadsheet migration and derived roster intelligence. This guide is the top-level domain contract; detailed workflow contracts remain in [Kingdoms roster](kingdoms-roster.md), [player snapshots](kingdoms-snapshots.md), [roster intelligence](kingdoms-intelligence.md), and [controlled CSV migration](kingdoms-csv-migration.md).

## Ownership model

The increment deliberately separates three identities:

1. **Application identity** — global `User`, owned by Identity.
2. **Alliance membership** — the user↔alliance relationship, owned by Memberships.
3. **Kingshot identity** — neutral `KingdomPlayer` reference within a `Kingdom`, owned by Kingdoms.

A roster entry may optionally link a Kingshot player to an active membership in the same Alliance. That link does not merge the records or make the application account the source of truth for game identity.

## Global reference data

### Kingdom

A `Kingdom` is global reference data with:

- ULID primary key;
- unique positive canonical Kingdom number;
- lifecycle state (`active` / `archived`); and
- timestamps.

An Alliance stores `kingdom_id`. The old free-form `alliances.kingdom` persistence column is removed after fail-closed migration/backfill. Existing presentation/API fields named `kingdom` are derived from the relationship rather than maintained as a compatibility column.

### KingdomPlayer

A `KingdomPlayer` is also global neutral reference data, scoped to one Kingdom. It may carry a stable game-player ID when known and a current neutral display name.

Stable game-player ID inside the Kingdom is the only automatic identity-match key. Display names are not unique and are never sufficient for automatic merge.

Multiple alliances in one Kingdom may reference the same neutral player. That never grants access to another alliance's roster, notes, snapshots, imports or metrics.

## Alliance-owned roster

`AllianceRosterEntry` belongs to one Alliance and one `KingdomPlayer`. It owns the tenant-specific observation state:

- observed name;
- optional same-alliance membership link;
- game role/rank;
- active/tracked/left state;
- joined/left dates;
- private manager notes;
- last roster-observation time; and
- source/provenance.

Ordinary members read the roster under `alliance.view`. Management requires `kingdoms.manage`, which is included in the built-in Owner, Leader and Officer templates. Privileged roster mutations require recent password confirmation.

Marking a player left preserves neutral identity, membership linkage and historical snapshots.

## Append-only player snapshots

`PlayerSnapshot` is the historical source of truth for observed game state. A snapshot stores:

- alliance/roster/player identity;
- observed name;
- signed-64-bit integer power;
- optional progression/level;
- optional observed alliance/tag;
- capture time;
- source;
- actor; and
- optional CSV-import provenance.

Normal roster edits do not rewrite historical snapshots. Exact accepted-observation retries are deterministic and idempotent; a later capture time is a new observation.

The latest roster projection is chosen by capture time with deterministic ID tie-breaking. Current/stale/missing quality is based on snapshot history, not a duplicated mutable power field.

## Roster intelligence

The intelligence view derives tenant-scoped operational summaries from active/tracked roster entries and recorded snapshots:

- tracked-player count;
- total, average and median recorded power;
- current/stale/missing snapshot counts;
- recent joins/departures;
- membership-linkage coverage;
- aggregate 7-day and 30-day power change; and
- manager-only individual comparison detail.

Missing data is not zero. Trend baselines use the closest observation at or before the N-day target, bounded so the baseline is no older than 2N days. There is no interpolation.

Manager detail is alphabetical diagnostic information. It is not a ranking, punitive score or Contribution-domain metric.

## Controlled CSV migration

The accepted spreadsheet-exit path is a strict `kingdoms-roster.v1` UTF-8 CSV workflow:

1. upload is bounded to 1 MiB and 500 data rows;
2. the complete file is validated before roster persistence;
3. rows are classified create/update/ambiguous/rejected;
4. stable game ID is the only automatic identity match;
5. every display-name match requires explicit manager resolution;
6. accepted confirmation is password-confirmed and one database transaction;
7. preview drift fails closed;
8. Alliance + schema + SHA-256 checksum makes committed re-upload/reconfirmation idempotent; and
9. CSV-created snapshots retain import provenance.

Current-roster exports are tenant-scoped. Ordinary member export excludes management fields. Management export requires `kingdoms.manage`. Every string cell is neutralized against spreadsheet formula injection before CSV encoding, and responses are private/non-cacheable.

## Alliance Kingdom settings

Changing an Alliance's Kingdom remains an Alliance-setting mutation under `alliance.manage`, not `kingdoms.manage`.

The workflow uses authenticated/verified active-Alliance context plus recent password confirmation, transaction/row locking, audit evidence and a durable internal outbox event. Archived Kingdoms cannot be newly selected.

## Tenant and privacy boundary

The active Alliance is authoritative for all roster-owned behavior. Reads and mutations re-resolve submitted roster, membership, snapshot/import and resolution identifiers under the tenant boundary.

Ordinary member payloads exclude private manager notes, management membership details, import-management metadata and snapshot actor identity. Managers receive only the additional information required by the approved workflow.

See the [whole-increment security review](../security/kingdoms-roster-intelligence-security-review.md) for cross-slice threat analysis.

## Audit, outbox and external integrations

Privileged Kingdom/roster/snapshot/import mutations record audit evidence and durable transactional-outbox messages when state materially changes.

Those outbox messages are **internal** for `KINGDOMS-001`. The existing external API remains limited to its documented alliance/events/contributions read scopes, and `alliance.kingdom_updated` plus `kingdoms.*` events are excluded from generic webhook fan-out. A future external Kingdoms API or webhook schema requires an explicit integration-contract increment.

## Migration and rollback

The first-class Kingdom migration normalizes supported legacy numeric forms, reuses one canonical Kingdom for equivalent values and fails closed on malformed non-empty source data. It removes the old string column after backfill.

The full dependency rollback order is CSV import/provenance → snapshots → roster/permission dependencies → Kingdom foundation. The migration acceptance test reverses and reapplies the complete series from the accepted pre-increment representation.

## Operations

The increment adds no dedicated scheduler, worker queue, crawler or external ingestion service. It uses synchronous request workflows, PostgreSQL, audit and the existing outbox publisher.

Operational diagnosis for import failures, snapshot history, intelligence windows/query behavior and rollback is documented in [Kingdoms roster intelligence operations](../operations/kingdoms-roster-intelligence.md).

## Explicit deferrals

`KINGDOMS-001` does not implement:

- scraping, OCR, bots or undocumented Kingshot APIs;
- automated game-data ingestion;
- transfer planning;
- diplomacy/NAP intelligence;
- cross-alliance rankings/intelligence;
- automated player scoring/recommendations; or
- public Kingdoms API/webhook contracts.

Those are follow-on product candidates only and require separate approval before runtime implementation.
