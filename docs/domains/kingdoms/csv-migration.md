# Kingdoms controlled CSV migration

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as part of `KINGDOMS-001`  
**Owning domain:** `Kingdoms`

## 1. Purpose

This document defines the accepted controlled CSV workflow for moving an Alliance roster out of spreadsheets into the Kingdoms roster/snapshot model. It is deliberately a bounded first-party migration surface, not a generic game-data ingestion subsystem.

## 2. Scope and non-scope

In scope:

- strict `kingdoms-roster.v1` CSV schema;
- bounded parser/validation;
- dry-run preview/classification;
- explicit resolution of ambiguous name matches;
- atomic password-confirmed commit;
- roster/snapshot action reuse;
- provenance/idempotency/drift protection; and
- member/management CSV export with spreadsheet-formula neutralization.

Out of scope:

- recurring/background imports;
- OCR/screenshot parsing;
- scraping/browser automation/bots;
- undocumented Kingshot APIs;
- public roster/import API/webhook contracts;
- cross-Alliance roster exchange/ranking;
- transfer/diplomacy automation; and
- automatic player deduplication by name.

## 3. Model and state

### CSV schema

The exact `kingdoms-roster.v1` header order is:

```text
game_player_id,name,power,progression_level,alliance_tag,game_role,state,joined_at,captured_at
```

| Field | Required | Contract |
| --- | --- | --- |
| `game_player_id` | No | Stable game identifier when known; max 100 chars; only automatic identity-match key. |
| `name` | Yes | Observed name, 1–160 chars; never automatic merge key. |
| `power` | Yes | Non-negative integer `0` through `9223372036854775807`. |
| `progression_level` | No | Observed progression/level, max 64 chars. |
| `alliance_tag` | No | Observed game Alliance/tag, max 32 chars. |
| `game_role` | No | Roster role/rank, max 64 chars. |
| `state` | Yes | `active`, `tracked`, or `left`. |
| `joined_at` | No | Exact `YYYY-MM-DD`. |
| `captured_at` | No | ISO-8601 with timezone; >5 minutes future rejected. |

Blank `captured_at` is replaced during preview with the preview's stored UTC timestamp so confirmation remains deterministic.

### RosterImport

Preview persists an Alliance-owned `RosterImport` containing checksum, schema version, deterministic normalized rows, classifications, candidate resolutions, and eventual commit summary/provenance. Preview does not create/update roster entries or snapshots.

Row classifications are:

- `create`;
- `update`;
- `ambiguous`; and
- `rejected`.

## 4. Invariants

1. Import workspace/preview/confirmation require `kingdoms.manage` and active Alliance context.
2. Preview/confirmation require recent password confirmation.
3. Stable game ID is the only automatic identity-match key.
4. Display-name match never silently becomes an update, even with one candidate.
5. Every ambiguous row must be explicitly resolved before commit.
6. Confirmation is one database transaction for the whole preview.
7. Rejected rows prevent commit.
8. Material roster drift between preview and confirmation fails closed.
9. Existing roster membership linkage/private manager notes are preserved because they are not part of the public migration schema.
10. Roster/snapshot writes reuse accepted domain actions instead of parallel persistence logic.
11. Same committed file checksum/schema/Alliance is not applied twice.
12. Export strings are neutralized against spreadsheet formula execution.

## 5. Workflows

### Parse upload

The parser:

- accepts `.csv` only;
- requires UTF-8;
- rejects NUL bytes;
- accepts at most **1 MiB**;
- accepts at most **500 nonblank data rows**;
- requires the exact header;
- treats cells as data, never executes formulas/macros;
- rejects duplicate nonblank `game_player_id` within one file; and
- records row validation errors before any roster persistence.

### Dry-run preview

A valid upload persists preview/classification only.

- **create** — no stable-ID current roster match, or no stable-ID/name candidate.
- **update** — stable ID maps to an existing same-Alliance roster entry.
- **ambiguous** — no stable ID and one or more same-name roster candidates.
- **rejected** — schema/field/duplicate validation failed.

### Resolve ambiguity

For each ambiguous row, a manager chooses:

- `create`; or
- one of the previewed candidate roster entries.

Confirmation accepts only previewed candidate IDs and re-establishes the active Alliance boundary. Arbitrary roster IDs cannot become cross-tenant targets.

### Confirm atomically

Before writes, confirmation:

1. locks/reloads the Alliance-owned import;
2. refuses rejected previews;
3. requires explicit allowed resolution for every ambiguous row;
4. re-loads update targets under active Alliance;
5. checks stable-ID assumptions/current roster state; and
6. fails closed on material drift.

Then it calls the accepted roster/snapshot actions.

### Provenance and idempotency

`RosterImport` identity is active Alliance + schema version + SHA-256 file checksum.

- Re-previewing same uncommitted content refreshes dry-run classification.
- Re-uploading already committed content returns the committed import rather than reapplying.
- Re-confirming committed import is a no-op.
- Imported roster writes carry source `csv` and import ID in audit/outbox metadata.
- New snapshots carry source `csv` plus `roster_import_id`.

Successful confirmation stores committed row count, roster creates, roster updates, and newly created snapshots, and emits `kingdoms.roster_import_committed` evidence.

### Export

The active/tracked roster CSV export uses the same public schema and projects each roster profile's latest snapshot. Missing snapshot-derived fields are blank, never coerced to zero.

- **member export** — `alliance.view`, public schema only.
- **management export** — `kingdoms.manage`, adds `membership_id` and `manager_notes`.

Responses are private/no-store and `nosniff`.

Every string cell beginning with `=`, `+`, `-`, `@`, tab, carriage return, or line feed is prefixed with an apostrophe before CSV encoding. Quoting alone is not sufficient formula-injection protection.

## 6. Authorization, tenancy and privacy

Every import lookup is constrained by active Alliance before display/commit. Ordinary member export uses `alliance.view`; management export uses `kingdoms.manage` because it contains management fields.

Sharing Kingdom/KingdomPlayer identity never authorizes another Alliance's import, roster, snapshot, or export data.

## 7. Persistence and query semantics

Preview stores deterministic normalized rows and candidate resolution state. Commit revalidates assumptions against current persistence before applying one atomic batch.

Import provenance extends the accepted roster/snapshot models rather than creating a parallel current/history data source.

## 8. Events/integrations/background processing

Committed imports create audit/outbox evidence, including `kingdoms.roster_import_committed`, but all `kingdoms.*` events remain internal.

The workflow introduces no scheduler/background importer and no public API/webhook schema.

## 9. Failure, idempotency and concurrency

- Parser/schema/field/duplicate errors → rejected preview rows/no commit.
- Name ambiguity → explicit manager resolution required.
- Preview drift → whole commit fails closed.
- Already committed checksum → no duplicate application.
- Exact snapshot retry → no duplicate history through snapshot idempotency.
- Arbitrary/cross-tenant resolution ID → rejected.

## 10. Operations and observability

Import records preserve preview/error/result evidence and provenance. Operators can distinguish parse rejection, ambiguity, drift, committed state, and snapshot creation without inspecting private data across tenants.

See [Kingdoms roster intelligence operations](operations/kingdoms-roster-intelligence.md).

## 11. Tests and validation

Accepted validation covers:

- create/update/ambiguous/rejected classification;
- preview without roster persistence;
- explicit ambiguous-name resolution;
- rejected-batch refusal;
- preview drift failing closed;
- import/snapshot idempotency;
- provenance/audit/outbox evidence;
- tenant/permission isolation;
- management-field export gating;
- formula-injection neutralization;
- 500-row/1-MiB parser boundaries; and
- full end-to-end Kingdoms acceptance.

See the [KINGDOMS-001 exit report](product/kingdoms-roster-intelligence-exit-report.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Roster](roster.md)
- [Snapshots](snapshots.md)
- [Roster intelligence](intelligence.md)
- [KINGDOMS-001 security review](security/kingdoms-roster-intelligence-security-review.md)
