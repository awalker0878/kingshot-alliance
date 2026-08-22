# Screenshot Intake operations

Status: Current for the Screenshot Intake implementation on this branch.

This runbook covers private screenshot evidence, OCR/extraction workers, commit recovery, retention, and privacy-safe diagnostics. Product behavior is defined by `docs/product/screenshot-intake.md`; ownership is defined by ADR-0010 and `docs/architecture/contexts/intelligence/evidence.md`.

## Runtime dependencies

Screenshot Intake requires:

- a private Laravel filesystem disk configured by `EVIDENCE_DISK` (falling back to `FILESYSTEM_DISK`);
- PHP GD for perceptual image hashing;
- Tesseract OCR plus the configured language pack;
- the normal queue worker/Horizon path for classification and extraction jobs;
- PostgreSQL for evidence attempts, immutable review history and commit receipts.

The production image installs GD, Tesseract and the English Tesseract language data. Container startup/build verification must fail if required PHP extensions are absent. Evidence binaries must never be placed on a public disk.

## Configuration

`config/evidence.php` is the single application configuration surface. Relevant environment settings are:

- `EVIDENCE_DISK` — private evidence disk;
- `EVIDENCE_MAX_KB` — maximum screenshot size, default 12288 KB;
- `EVIDENCE_VISUAL_DUPLICATE_DISTANCE` — perceptual-hash warning threshold, default 8;
- `EVIDENCE_OCR_BINARY` — Tesseract executable, default `tesseract`;
- `EVIDENCE_OCR_LANGUAGE` — OCR language, default `eng`;
- `EVIDENCE_OCR_PSM` — Tesseract page segmentation mode, default 6;
- `EVIDENCE_REJECTED_RETENTION_DAYS` — rejected/duplicate/deleted uncommitted evidence retention, default 14 days;
- `EVIDENCE_FAILED_RETENTION_DAYS` — failed/unsupported evidence retention, default 30 days;
- `EVIDENCE_UNCOMMITTED_RETENTION_DAYS` — other uncommitted evidence retention, default 90 days;
- `EVIDENCE_COMMITTED_BINARY_RETENTION_DAYS` — committed source-binary retention, default 180 days.

Changing retention values changes future enforcement only; it must not rewrite accepted `Operations/Results` domain state.

## Processing queues

Upload persists the source and immutable provenance first, then dispatches classification. Classification persists its own immutable attempt and OCR document. Supported Bear Hunt classifications dispatch extraction, which persists a separate extraction attempt and field records.

Jobs use scalar evidence IDs and bounded retries. A failed terminal attempt moves the evidence to `failed`. An authorized manager can explicitly retry failed evidence; retry begins a new classification/extraction history rather than overwriting the failed attempt.

Use Horizon/queue diagnostics to inspect worker health. Do not manually update attempt rows to force progress.

## Commit recovery

The commit sequence is intentionally not a distributed database transaction:

1. Intelligence/Evidence creates or resumes a commit attempt for an approved review.
2. `app/Workflows/ScreenshotIntake/CommitBearHuntEvidence` calls the scalar `Operations/Results` owner action.
3. Operations records an idempotent Bear Hunt report and recomputes result projections.
4. Evidence records the returned destination receipt.

If the process stops after step 3 and before step 4, retry the commit through the normal application path. Operations recognizes the stable idempotency key and returns the existing receipt without adding damage again. Never repair this condition by directly inserting or editing `event_player_results`.

A failed Evidence commit attempt remains audit history. The reviewed evidence remains available for an authorized retry unless its source has been deleted by explicit lifecycle action.

## Duplicate handling

- SHA-256 exact duplicates are scoped to the same Alliance and Bear Hunt occurrence and are automatically reused/rejected without storing a second binary.
- Perceptual-hash similarity is an advisory warning only.
- Semantic fingerprints are computed from reviewed Bear Hunt meaning and block commit when they collide. A manager may mark the report distinct only through the explicit reviewed justification action.

Never expose hash-match information across Alliances.

## Deletion and retention

Evidence deletion and Bear Hunt result correction are separate operations.

User deletion removes the retained source image and sensitive OCR/raw field text, records an audited tombstone and moves the Evidence record to `deleted`. If the evidence already committed, its accepted Operations report remains unchanged.

The daily `evidence:enforce-retention` scheduler task runs at 03:20 application time with overlap protection:

- committed evidence older than the committed-binary window has the source image and sensitive OCR/raw source data redacted while stable provenance, normalized reviewed meaning and destination receipt remain;
- uncommitted rejected/duplicate/deleted, failed/unsupported, and other inactive evidence is fully purged after its configured window;
- active `classifying`, `extracting`, or `committing` evidence is not purged.

Backups may contain binaries that were retained at backup time. Existing backup/restore policy therefore remains part of the evidence lifecycle: restored environments must immediately resume the retention scheduler and must not expose restored evidence through public storage.

## Diagnostics

Run:

```text
evidence:diagnostics
```

The command reports aggregate lifecycle counts, failed classification/extraction/commit counts, the oldest processing timestamp, retained-binary count and redacted count. It intentionally emits no screenshot name, OCR text, Player name, Alliance name, hashes or review contents.

For a single incident, correlate application audit/outbox events using request/trace IDs and the non-secret evidence/commit identifiers already available to authorized operators. Relevant business audit events include upload, retry, delete/redaction, review, duplicate resolution, commit success/failure and Bear Hunt report record/remove events.

## Incident playbook

For a growing processing backlog:

1. Check worker/Horizon health and `evidence:diagnostics` oldest processing timestamp.
2. Confirm Tesseract is installed in the deployed image and callable by the application user.
3. Confirm the evidence disk is writable/private and has capacity.
4. Restore worker capacity or provider dependency first; do not mutate attempt statuses manually.
5. Retry only terminal `failed` Evidence through the supported action.

For suspected double-counting:

1. Identify the Evidence review and destination report receipt.
2. Confirm Operations contains one report for the idempotency key and semantic acceptance fingerprint.
3. Re-run the supported commit if Evidence lacks the success receipt; an idempotent replay must not change totals.
4. If the accepted report itself is wrong, use the audited Operations report-removal/correction path so aggregates are deterministically recomputed. Deleting the screenshot is not a result correction.

For privacy deletion:

1. Use the normal evidence deletion action so the binary and sensitive OCR/raw text are removed together and audit evidence remains.
2. Confirm the image endpoint returns gone/not available afterward.
3. Do not delete an accepted Operations report unless the result itself is incorrect.
