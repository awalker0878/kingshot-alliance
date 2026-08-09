# Kingdoms controlled CSV migration

[← Domain documentation](README.md)

**Status:** Accepted as part of `KINGDOMS-001`  
**Scope:** `KINGDOMS-001` controlled roster migration only  
**Acceptance evidence:** [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md)

This guide describes the accepted controlled CSV workflow for moving an alliance roster out of spreadsheets and into the Kingdoms roster/snapshot model. It is deliberately a bounded migration surface, not a generic game-data ingestion subsystem.

Code and tests remain authoritative for exact runtime behavior. The governing scope is [`KINGDOMS-001`](../product/kingdoms-roster-intelligence-increment.md), with sequencing in the [implementation plan](../product/kingdoms-roster-intelligence-implementation-plan.md).

## Authorization and assurance

- Import workspace, preview records and confirmation require `kingdoms.manage` in the active Alliance.
- Preview and confirmation routes require recent password confirmation.
- Every import lookup is constrained by the active Alliance before data is displayed or committed.
- Ordinary member export requires `alliance.view`.
- Management export requires `kingdoms.manage` because it includes management-only fields.
- Sharing a Kingdom or neutral `KingdomPlayer` identity never authorizes access to another alliance's import, roster, snapshot or export data.

## CSV schema

The accepted import contract is `kingdoms-roster.v1` with this exact header order:

```text
game_player_id,name,power,progression_level,alliance_tag,game_role,state,joined_at,captured_at
```

| Field | Required | Contract |
| --- | --- | --- |
| `game_player_id` | No | Stable game identifier when known; at most 100 characters. This is the only automatic identity-match key. |
| `name` | Yes | Observed player name, 1–160 characters. Never an automatic merge key. |
| `power` | Yes | Non-negative whole number within signed 64-bit range (`0` through `9223372036854775807`). |
| `progression_level` | No | Observed progression/level label, at most 64 characters. |
| `alliance_tag` | No | Observed in-game alliance/tag, at most 32 characters. |
| `game_role` | No | Roster game role/rank, at most 64 characters. |
| `state` | Yes | `active`, `tracked`, or `left`. |
| `joined_at` | No | Exact `YYYY-MM-DD` calendar date. |
| `captured_at` | No | ISO-8601 timestamp with timezone. A value more than five minutes in the future is rejected. |

A blank `captured_at` is replaced during preview with the preview's stored UTC timestamp. The stored preview therefore remains deterministic at confirmation time instead of substituting a new clock value later.

## Parser limits

The import parser:

- accepts `.csv` files only;
- requires valid UTF-8 text;
- rejects NUL bytes;
- accepts at most 1 MiB per upload;
- accepts at most 500 nonblank data rows;
- requires the exact documented header;
- treats CSV content as data and never evaluates spreadsheet formulas/macros;
- rejects duplicate nonblank `game_player_id` values within one file; and
- records row-level validation errors before any roster persistence occurs.

The browser upload is only a transport. The server parser and validation rules are the acceptance boundary.

## Dry-run preview

Previewing a valid upload persists an alliance-owned `RosterImport` record containing the checksum, schema version, row classifications and deterministic normalized row data. It does **not** create or update roster entries or snapshots.

Each row is classified as one of:

- **create** — no current alliance roster entry matches the supplied stable game ID, or no stable ID/name candidate exists;
- **update** — the supplied stable game ID maps to an existing roster entry for this alliance;
- **ambiguous** — no stable ID was supplied and one or more same-name roster candidates exist; or
- **rejected** — schema/field/duplicate validation failed.

A display-name match is never silently converted to an update, even when there is only one same-name candidate.

## Ambiguous identity resolution

Every ambiguous row must be explicitly resolved before confirmation. A manager can choose:

- `create`, which intentionally creates a new game-player identity; or
- one of the candidate roster entries captured in the preview.

The confirmation action accepts only previewed candidate IDs and re-establishes the active Alliance boundary. An arbitrary submitted roster ID cannot be used as a cross-tenant target.

## Atomic confirmation

Confirmation is one database transaction for the whole preview.

Before persistence the workflow:

1. locks and re-loads the alliance-owned import record;
2. refuses any preview containing rejected rows;
3. requires every ambiguous row to have an explicit allowed resolution;
4. re-loads update targets under the active Alliance;
5. checks stable-ID assumptions against current roster state; and
6. fails closed if roster state changed materially after preview.

Only after those checks pass are roster entries and snapshots written.

CSV writes reuse the existing Kingdoms roster and snapshot actions. This preserves their authorization, tenant re-resolution, audit/outbox, range validation and snapshot idempotency rules instead of maintaining a parallel mutation implementation.

For an existing roster entry, CSV update preserves application-membership linkage and private manager notes because those fields are not part of the public migration schema.

## Provenance and idempotency

`RosterImport` is keyed by active Alliance + schema version + SHA-256 file checksum.

- Re-previewing the same uncommitted content refreshes the dry-run classification instead of creating duplicate import records.
- Re-uploading content that has already been committed returns the committed import record; it does not execute the batch again.
- Re-confirming an already committed import is a no-op.
- Imported roster writes carry source `csv` and the import ID in audit/outbox metadata.
- Newly accepted snapshots carry source `csv` plus `roster_import_id` provenance.
- Snapshot idempotency continues to describe the accepted observation, so retrying the same imported observation cannot multiply snapshot history.

A successful confirmation stores a summary with committed row count, roster creates, roster updates and newly created snapshots. The batch also emits `kingdoms.roster_import_committed` audit/outbox evidence.

## Export contract

The accepted workflow exposes a current active/tracked roster CSV export using the same public columns as `kingdoms-roster.v1`.

The export projects each roster profile's latest recorded snapshot. No snapshot means snapshot-derived fields are blank; missing data is not converted to zero.

Two scopes exist:

- **member export** — requires `alliance.view` and contains only the public migration schema fields;
- **management export** — requires `kingdoms.manage` and adds `membership_id` and `manager_notes`.

Export responses are marked private/no-store and `nosniff`.

Every string cell is sanitized against spreadsheet formula execution. Cells beginning with `=`, `+`, `-`, `@`, tab, carriage return or line feed are prefixed with an apostrophe before CSV encoding. Quoting a dangerous formula-shaped value is not considered sufficient protection by itself.

## Internal events and external boundary

CSV preview/confirmation is a first-party tenant workflow. A committed import creates audit/outbox evidence, but `kingdoms.*` outbox events remain internal to `KINGDOMS-001` and are excluded from generic external webhook fan-out. No public roster/import API or webhook schema is accepted by this increment.

## Explicit boundaries

The accepted CSV workflow does not introduce:

- background or recurring imports;
- OCR/screenshot parsing;
- browser automation or scraping;
- undocumented Kingshot APIs or bots;
- public Kingdoms roster/import API endpoints;
- Kingdoms webhook contracts;
- cross-alliance roster exchange/ranking;
- transfer planning or diplomacy/NAP intelligence; or
- automatic player deduplication by name.

Those capabilities require their own approved product/integration increments.

## Verification

The accepted Slice D and `K1-P6` suites cover:

- create/update/ambiguous/rejected preview classification;
- preview-without-persistence;
- explicit ambiguous-name resolution;
- rejected-batch refusal;
- preview drift failing closed;
- committed-import and snapshot idempotency;
- CSV provenance and batch audit/outbox evidence;
- tenant and permission isolation;
- management-field export gating;
- formula-injection neutralization;
- 500-row / 1-MiB parser boundaries; and
- the complete end-to-end Kingdoms acceptance workflow.

Exact protected evidence is recorded in the [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md).
