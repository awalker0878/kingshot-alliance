# Screenshot Intake operations

Status: Current for the Screenshot Intake implementation on this branch.

This runbook covers private screenshot evidence, OCR/extraction workers, commit recovery, duplicate handling, retention, and privacy-safe diagnostics. Product behavior is defined by `docs/product/screenshot-intake.md`; ownership is defined by ADR-0010 and `docs/architecture/contexts/intelligence/evidence.md`.

## Runtime dependencies

Screenshot Intake requires:

- a private Laravel filesystem disk configured by `EVIDENCE_DISK` (falling back to `FILESYSTEM_DISK`);
- PHP GD for perceptual image hashing;
- Tesseract OCR plus the configured language pack;
- the normal queue worker/Horizon path for classification and extraction jobs;
- PostgreSQL for evidence attempts, review history and commit receipts.

The production image installs GD, Tesseract and the English Tesseract language data. Container build/startup verification must fail if required PHP extensions or Tesseract are absent. Evidence binaries must never be placed on the `public` disk.

## Configuration

`config/evidence.php` is the single application configuration surface. Relevant environment settings are:

- `EVIDENCE_DISK` — private evidence disk;
- `EVIDENCE_MAX_KB` — maximum screenshot size, default 12288 KB;
- `EVIDENCE_VISUAL_DUPLICATE_DISTANCE` — perceptual-hash warning threshold, default 8;
- `EVIDENCE_OCR_BINARY` — Tesseract executable, default `tesseract`;
- `EVIDENCE_OCR_LANGUAGE` — OCR language, default `eng`;
- `EVIDENCE_OCR_PSM` — Tesseract page-segmentation mode, default 6;
- `EVIDENCE_DELETED_RETENTION_DAYS` — uncommitted user-deleted evidence tombstone retention, default 14 days;
- `EVIDENCE_FAILED_RETENTION_DAYS` — failed/unsupported uncommitted evidence retention, default 30 days;
- `EVIDENCE_UNCOMMITTED_RETENTION_DAYS` — other inactive uncommitted evidence retention, default 90 days;
- `EVIDENCE_COMMITTED_BINARY_RETENTION_DAYS` — committed source-binary retention, default 180 days.

There are no deprecated aliases for these names. The application is not deployed, so configuration and documentation use one contract only. Changing retention values affects future enforcement; it never rewrites accepted `Operations/Results` state.

## Upload and processing queues

Upload authorization is checked before expensive processing and reacquired inside the persistence transaction. The application validates configured size/MIME, verifies the actual image MIME/dimensions, runs the shared security scanner, computes SHA-256, computes perceptual similarity when GD is available, and writes to a generated Alliance-scoped private path.

A rejected/unsafe/spoofed/oversized upload fails before an Evidence row is created. An exact duplicate within the authorized Alliance and Bear Hunt occurrence reuses the existing Evidence identity instead of creating a second lifecycle row or binary. Therefore `rejected` and `duplicate` are outcomes, not persisted Evidence lifecycle states.

A newly accepted upload persists source/provenance first, then dispatches classification. Classification persists an immutable attempt and OCR document. Supported Bear Hunt classifications dispatch extraction, which persists a separate immutable extraction attempt and field records.

Jobs carry scalar evidence IDs and use bounded retries. A terminal processing failure moves Evidence to `failed`; unsupported input moves it to `unsupported`. An authorized manager can retry terminal processing through the supported action. Retry creates new attempt history and never overwrites the failed attempt.

Use Horizon/queue diagnostics to inspect worker health. Do not manually update lifecycle or attempt rows to force progress.

## Persisted lifecycle

The persisted Evidence lifecycle is:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Exceptional persisted states are `unsupported`, `failed`, and `deleted`.

There are intentionally no `delete_pending`, `purge_pending`, or `purged` rows. User deletion synchronously redacts the retained source and transitions to `deleted`. Retention of uncommitted evidence physically deletes the row after redaction, while committed evidence retains its minimal provenance tombstone after the binary is removed.

## Commit recovery

The commit sequence is intentionally not a distributed database transaction:

1. Intelligence/Evidence creates or resumes a commit attempt for an approved review.
2. `Intelligence/Evidence/Actions/CommitReviewedBearHuntEvidence` builds reviewed scalar meaning and calls the `Operations/Results` owner Action.
3. Operations records an idempotent Bear Hunt report and deterministically recomputes result projections.
4. Evidence records the returned destination receipt.

If execution stops after step 3 and before step 4, retry through the normal application path. The reviewed meaning produces the same destination idempotency key. Operations returns the existing report receipt without adding damage again, and Evidence records the recovered receipt in a new successful attempt if the previous acknowledgement attempt was marked failed.

A failed Evidence commit attempt remains immutable audit history. Never repair interrupted acknowledgement by directly inserting or editing `event_player_results`, Bear Hunt report rows, or Evidence commit rows.

## Duplicate handling

- **Exact:** SHA-256 equality is checked only inside the same authorized Alliance and Bear Hunt occurrence. The existing Evidence record is reused and a second binary is not retained.
- **Visual:** perceptual-hash similarity is an advisory review warning only. Different binaries remain separate Evidence records.
- **Semantic:** the approved review produces a deterministic Bear Hunt fingerprint. A collision blocks commit until an authorized manager explicitly records why the report is distinct.

Never expose hash-match, evidence-ID, or duplicate existence information across Alliances.

## Deletion and retention

Evidence deletion and Bear Hunt result correction are separate operations.

User deletion removes the retained source image plus sensitive OCR/raw field text, records an audited tombstone and moves Evidence to `deleted`. If the evidence already committed, the accepted Operations report remains unchanged.

The scheduled `evidence:enforce-retention` task runs daily at 03:20 application time with overlap protection:

- committed Evidence older than `EVIDENCE_COMMITTED_BINARY_RETENTION_DAYS` has the source binary and sensitive OCR/raw data redacted while stable source metadata, reviewed normalized meaning, commit history and destination receipt remain;
- uncommitted `deleted` Evidence is physically purged after `EVIDENCE_DELETED_RETENTION_DAYS`;
- uncommitted `failed`/`unsupported` Evidence is physically purged after `EVIDENCE_FAILED_RETENTION_DAYS`;
- other inactive uncommitted Evidence is physically purged after `EVIDENCE_UNCOMMITTED_RETENTION_DAYS`;
- active `classifying`, `extracting`, and `committing` Evidence is never purged by the retention pass.

Committed tombstones whose binary is already gone are excluded from subsequent retention candidate scans. This prevents long-lived committed history from starving newer expired evidence when the command processes a bounded batch.

Backups may contain binaries that were still inside their retention window when the backup was created. Restored environments must immediately resume the retention scheduler and must not expose restored evidence through public storage.

## Diagnostics

Run:

```text
evidence:diagnostics
```

The command reports:

- lifecycle counts;
- classification/extraction attempt counts, failure rates and average latency;
- reviewed/corrected row counts and correction rate;
- exact/visual duplicate event counts and semantic duplicate rate;
- commit attempts, failures and failure rate;
- retention failures;
- oldest active-processing age;
- retained-binary count;
- redacted-evidence count.

It intentionally emits no screenshot filename, OCR text, Player name, Alliance name, hash, or review contents.

For a single incident, correlate application audit/outbox events using request/trace IDs and non-secret Evidence/commit identifiers already visible to authorized operators. Relevant events include upload accepted/rejected, exact/visual duplicate detection, classification/extraction start/completion/failure, retry, review approval, semantic duplicate detection/resolution, commit start/success/failure, destination idempotent replay, evidence deletion/redaction/purge, retention failure, and Bear Hunt report record/remove events.

## Incident playbook

For a growing processing backlog:

1. Check worker/Horizon health and `evidence:diagnostics` oldest-processing age.
2. Confirm Tesseract is installed in the deployed image and callable by the application user.
3. Confirm the Evidence disk is writable, private, and has capacity.
4. Restore worker capacity or the failed runtime dependency first; do not mutate attempt statuses manually.
5. Retry only terminal failed processing through the supported product action.

For suspected double-counting:

1. Identify the Evidence review and destination receipt.
2. Confirm Operations contains one accepted report for the destination idempotency key and acceptance fingerprint.
3. Re-run the supported commit if Evidence lacks a success receipt; an idempotent replay must not change totals.
4. If the accepted report itself is wrong, use the audited Operations report-removal/correction action so aggregates are recomputed. Deleting the screenshot is not result correction.

For privacy deletion:

1. Use the normal Evidence deletion action so the binary and sensitive OCR/raw text are removed together and audit evidence remains.
2. Confirm the private image endpoint returns unavailable afterward.
3. Do not delete an accepted Operations report unless the result itself is incorrect.
