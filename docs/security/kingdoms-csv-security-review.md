# Kingdoms CSV migration security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-001` Slice D / `K1-P5` controlled CSV roster migration  
**Status:** Validated implementation candidate

This review covers the new CSV upload/preview/confirmation/export boundary. It does not approve automated game-data ingestion, public roster integrations, transfer planning or cross-alliance intelligence.

## Assets and trust boundaries

Protected assets include:

- alliance-owned roster state and historical snapshots;
- neutral `KingdomPlayer` identity references;
- application-membership linkage;
- private manager notes;
- import provenance and resolution choices;
- audit/outbox evidence; and
- exported roster data.

Trust boundaries are:

1. an untrusted user-supplied CSV file entering the application;
2. a privileged manager reviewing a persisted dry run;
3. password-confirmed confirmation crossing from preview into tenant mutations;
4. spreadsheet software consuming exported CSV; and
5. the active Alliance tenant boundary around every import/export query and mutation.

## Threats and controls

### Cross-alliance object-ID tampering

**Threat:** A manager submits another alliance's import ID, candidate roster ID, or other identifier during preview/confirmation.

**Controls:**

- import records are loaded with `alliance_id = active alliance`;
- update targets are re-loaded under the active Alliance during confirmation;
- ambiguous resolutions can reference only candidate roster IDs captured in that tenant's stored preview;
- roster/snapshot actions re-establish their own Alliance authorization and tenant constraints; and
- tests use two alliances in the same Kingdom to prove shared global reference identity does not weaken isolation.

**Residual risk:** Future bulk tooling must continue using tenant-first queries rather than treating Kingdom or `KingdomPlayer` as authorization boundaries.

### Identity confusion and accidental merge

**Threat:** Duplicate/similar display names cause one player to overwrite another player's roster/history.

**Controls:**

- `game_player_id` is the only automatic identity-match key;
- duplicate stable IDs within one file are rejected;
- display-name matches are always classified ambiguous, including a single candidate;
- explicit `create` remains available when the same display name represents a different person;
- candidate IDs are fixed in the preview and validated again at confirmation; and
- no name-based `KingdomPlayer` merge action is introduced.

**Residual risk:** Incorrect human resolution is still possible. The stored preview, resolution payload and audit record preserve explainability for later correction without deleting snapshot history.

### Preview/confirmation time-of-check/time-of-use drift

**Threat:** Roster state changes between dry run and confirmation, invalidating the preview.

**Controls:**

- confirmation locks the import record;
- previewed update targets are tenant-reloaded;
- stable-ID assumptions are checked again;
- a previewed stable-ID create fails if that player has since appeared on the roster; and
- the batch fails closed instead of silently reclassifying rows during commit.

**Residual risk:** Not every unrelated roster field is frozen by the preview. CSV updates intentionally preserve membership linkage/private notes rather than treating their changes as conflicts.

### Partial batch persistence

**Threat:** Some rows are written before a later row fails, leaving a spreadsheet migration half-applied.

**Controls:**

- rejected previews cannot be confirmed;
- every ambiguity must be resolved first;
- confirmation runs inside one database transaction; and
- the existing roster/snapshot actions participate in that transaction.

**Residual risk:** Outbox publication remains asynchronous after transaction commit, consistent with the application's transactional-outbox architecture.

### Replay and duplicate confirmation

**Threat:** Browser retries, double clicks or re-uploading the same accepted file multiplies roster/snapshot history.

**Controls:**

- imports are uniquely keyed by Alliance + schema version + SHA-256 file checksum;
- committed import re-upload returns the existing committed record;
- confirmation of an already committed record is a no-op;
- batch outbox uses import-derived idempotency;
- snapshot idempotency remains observation-based; and
- tests verify no duplicate snapshot/batch audit record on confirmation retry.

**Residual risk:** Deliberately altered file bytes create a new checksum and therefore a new preview; normal validation and explicit confirmation still apply.

### Malicious or malformed CSV input

**Threat:** Oversized files, invalid encodings, malformed rows or spreadsheet constructs exhaust resources or bypass field validation.

**Controls:**

- upload is limited to 1 MiB;
- parser is limited to 500 nonblank data rows;
- UTF-8 and NUL-byte validation is required;
- exact header and exact per-field constraints are enforced;
- power is integer-only and range checked;
- dates/timestamps use documented formats;
- duplicate stable IDs are rejected; and
- parsing is text-only with PHP CSV parsing; spreadsheet formulas/macros are never executed.

**Residual risk:** CSV complexity is intentionally capped rather than supporting arbitrary spreadsheet dialects. Operators must convert unsupported source spreadsheets to the documented schema first.

### Spreadsheet formula injection on export

**Threat:** A player name, role, alliance tag or private manager note beginning with a spreadsheet formula trigger executes when the CSV is opened.

**Controls:**

- every string cell, including management-only fields, passes through formula neutralization before CSV encoding;
- cells beginning with `=`, `+`, `-`, `@`, tab, carriage return or line feed are apostrophe-prefixed; and
- regression tests include hostile values in player name, role, tag and manager notes.

**Residual risk:** Spreadsheet applications vary in parsing behavior. The contract deliberately sanitizes before quoting rather than relying on CSV quoting alone.

### Private manager data disclosure

**Threat:** Ordinary members export manager notes or receive management import metadata.

**Controls:**

- import workspace requires `kingdoms.manage`;
- ordinary member export contains only the public `kingdoms-roster.v1` columns;
- management export requires `kingdoms.manage` and is the only export including `membership_id` / `manager_notes`;
- responses use `private, no-store` and `nosniff`; and
- tests assert the ordinary member payload omits management headers/data.

**Residual risk:** Authorized managers can intentionally download sensitive CSV data. Endpoint authorization cannot control where an authorized user subsequently stores that local file.

### Forged capture timestamps and misleading history

**Threat:** Imported observations claim future timestamps or confirmation changes an omitted timestamp, undermining trend history.

**Controls:**

- explicit timestamps must include timezone and cannot exceed the accepted five-minute future tolerance;
- blank capture time is normalized once during preview and persisted in the preview payload;
- confirmation uses the stored normalized time, not a new current time; and
- snapshots retain `source=csv` and import provenance.

**Residual risk:** The application cannot independently prove that a manager-entered historical timestamp reflects when the game observation was actually collected. Provenance makes that limitation visible rather than hiding it.

## Authentication and authorization

- Import preview/confirmation requires authenticated, verified, active-Alliance context plus `kingdoms.manage`.
- Privileged preview/confirmation routes also require recent password confirmation.
- Member export requires `alliance.view`.
- Management export requires `kingdoms.manage`.
- No public or API route is added by Slice D.

## Audit and privacy

Audit events record import/export identifiers, checksum/schema, row counts, scope and result summaries. They do not need to duplicate full CSV contents or private manager notes.

Imported snapshots retain actor and import provenance under the existing snapshot privacy contract. Ordinary member snapshot/history payloads do not gain import-manager metadata through Slice D.

## Verification evidence for the Slice D gate

Repository validation covers:

- migration up/down ordering with snapshot import provenance;
- strict parser/file/row boundaries;
- no roster mutation during preview;
- stable-ID matching and ambiguous-name handling;
- rejected-batch refusal and drift fail-closed behavior;
- atomic commit/idempotent retry;
- tenant/permission isolation;
- private export-field gating;
- formula-injection neutralization;
- audit/outbox/provenance assertions;
- frontend lint/format/types/build; and
- the inherited container/staging/recovery/security scan.

Those protected checks passed on the Slice D implementation head, so this review is validated-candidate evidence. Whole-increment security acceptance remains part of `K1-P6`; this document does not approve a real production cutover.
