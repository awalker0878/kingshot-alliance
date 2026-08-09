# Kingdoms roster intelligence operations

[← Operations documentation](README.md)

**Scope:** `KINGDOMS-001` accepted runtime operations contract  
**Status:** Accepted

## Runtime shape

`KINGDOMS-001` adds synchronous Laravel request workflows and PostgreSQL state. It does **not** add a dedicated scheduler command, queue worker, crawler, OCR service, bot, external game-data poller or new environment variable.

Kingdom association, roster mutations, snapshot recording and CSV confirmation write audit/outbox evidence transactionally. The existing outbox publisher continues to process those durable messages. Kingdoms outbox events are internal-only for this increment and are excluded from generic external webhook fan-out until a future approved integration contract explicitly exposes them.

## Primary persisted state

Operators troubleshooting the increment should distinguish:

- `kingdoms` — global Kingdom reference records;
- `kingdom_players` — global neutral game-player identity within a Kingdom;
- `alliance_roster_entries` — alliance-owned roster state, linkage and private manager notes;
- `player_snapshots` — append-only alliance-owned observations and provenance; and
- `kingdom_roster_imports` — bounded CSV preview, resolution and commit evidence.

A global Kingdom/player reference is never evidence that two alliances share tenant data.

## CSV import diagnostics

A CSV upload is parsed and fully classified before roster persistence. The import record stores schema version, filename, SHA-256 checksum, row/create/update/ambiguous/rejected counts and the normalized preview. A committed record additionally stores explicit ambiguity resolutions, commit summary, actor and timestamp.

When an import cannot be committed:

1. inspect the preview row outcome/errors in the manager UI;
2. confirm all ambiguous rows have an explicit previewed resolution;
3. if the roster changed after preview, create a fresh preview rather than forcing the stale batch;
4. if any row is rejected, correct the source CSV and upload it again; and
5. use audit/request correlation for unexpected server failures rather than editing persisted preview JSON manually.

Do not retry by changing the file bytes only to bypass checksum idempotency. A byte-different file is a new import and is validated from the beginning.

## Snapshot/history diagnostics

Snapshots are append-only normal-operation history. Latest roster projection is chosen by `captured_at` with deterministic ID tie-breaking. A missing snapshot means no observation exists; stale means the newest accepted snapshot is older than 30 days.

Exact accepted-observation retries are idempotent. A later capture time intentionally creates new history. CSV-created snapshots retain their `roster_import_id`; manual observations have no import reference.

Do not repair a bad observation by mutating historical rows directly. Use an explicit later observation/correction workflow consistent with the domain contract.

## Intelligence diagnostics

The intelligence view is calculated at request time from active/tracked roster entries and snapshot history. It performs batched roster/latest/baseline queries rather than one query per player.

For an N-day trend, the baseline is the closest observation at or before the N-day target and no older than 2N days. Missing eligible history yields no comparison. Current totals include the latest recorded power even when that latest observation is stale, while stale/missing counts make data quality visible.

Useful first checks for an unexpected dashboard value are:

- active/tracked roster count;
- current/stale/missing snapshot counts;
- the dashboard `asOf` timestamp;
- comparable-player count for the 7- or 30-day window; and
- the affected player's snapshot history/capture times.

## Query/index boundary

The accepted schema supports tenant-first access with:

- roster indexes/uniqueness on Alliance + player/membership/state/observed fields;
- snapshot indexes on Alliance + roster/player + capture time;
- alliance-scoped snapshot idempotency; and
- import uniqueness on Alliance + schema version + checksum.

`tests/Performance/KingdomRosterPerformanceTest.php` exercises 150 tracked players with 450 snapshots and asserts the intelligence calculation remains within a bounded SELECT-query budget. This is a regression/N+1 gate, not a production capacity benchmark.

Real production sizing still belongs to accountable infrastructure/load evidence and must not be inferred from a repository fixture.

## Migration and rollback

The Kingdoms migration series must be reversed in dependency order:

1. CSV import/provenance migration;
2. player snapshots;
3. roster tables/permission dependency as applicable; and
4. first-class Kingdom migration.

The migration round-trip test exercises that dependency order and then reapplies the full series, including reconstruction/backfill of the legacy pre-increment Alliance kingdom representation for development/test rollback verification.

Production rollback should prefer the normal immutable-image/database backup procedures in the deployment runbook. A destructive schema rollback after real user data exists requires an explicit operator decision and backup evidence; the repository migration test does not substitute for a production data-recovery plan.

## Accepted validation evidence

The accepted implementation SHA `7f743507b70865692290f517cd2de494ec54abae` passed the full protected gate, including the realistic-volume query regression, migrations, immutable-image staging, backup/restore and image scan. See the [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md) for run IDs and measured counts.

## Security and privacy

- Never log raw private manager notes as operational telemetry.
- Treat CSV files and management exports as potentially sensitive alliance data.
- Ordinary member export intentionally excludes management-only fields.
- Do not expose internal `kingdoms.*` outbox payloads through external webhooks or new API routes without an approved integration-contract change.
- Do not introduce automated Kingshot ingestion as an operational shortcut; it is outside `KINGDOMS-001`.

See the [whole-increment security review](../security/kingdoms-roster-intelligence-security-review.md), [observability guide](observability.md), [background processing](background-processing.md), [backup/restore runbook](runbooks/backup-restore.md), and [production launch approval](../product/production-launch-approval.md).
