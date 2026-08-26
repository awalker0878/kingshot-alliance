# Screenshot Intake operations

Status: Current for Bear Hunt and Transfer participant Screenshot Intake.

This runbook covers private screenshot evidence, OCR/extraction workers, commit recovery, duplicate handling, retention, and privacy-safe diagnostics for both supported Evidence families. Product behavior is defined by `docs/product/screenshot-intake.md` and `docs/product/screenshot-intake-transfer-evidence.md`; ownership is defined by ADR-0010 and `docs/architecture/contexts/intelligence/evidence.md`.

## Runtime dependencies

Screenshot Intake requires:

- a private Laravel filesystem disk configured by `EVIDENCE_DISK` (falling back to `FILESYSTEM_DISK`);
- PHP GD for perceptual image hashing;
- Tesseract OCR plus the configured language pack;
- the normal queue worker/Horizon path for classification and extraction jobs;
- PostgreSQL for evidence attempts, review history, commit attempts and destination receipts.

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

There is deliberately no Evidence-wide freshness TTL for Transfer facts. `valid_until` is reviewed per mutable Transfer observation and interpreted by `GameWorld/KingdomTransfers`.

Changing retention values affects future Evidence enforcement; it never rewrites accepted `Operations/Results` or `GameWorld/KingdomTransfers` state.

## Upload scopes and security

Upload authorization is checked before expensive processing and reacquired inside persistence transactions. The application validates configured size/MIME, verifies actual image MIME/dimensions, runs the shared security scanner, computes SHA-256, computes perceptual similarity when GD is available, and writes to a generated Alliance-scoped private path.

Evidence supports only two persistence scope shapes:

- Bear Hunt: one `occurrence_id`, no Transfer references;
- Transfer participant: `transfer_plan_id` + `transfer_participant_id`, no occurrence.

Mixed or incomplete scope combinations are rejected at the application boundary and by the database constraint/trigger used for the active database engine. Do not insert Evidence rows manually to bypass this constraint.

A rejected/unsafe/spoofed/oversized upload fails before an Evidence row is created.

Exact duplicates are tenant/scope safe:

- Bear Hunt exact matching is limited to the same authorized Alliance + occurrence;
- Transfer exact matching is limited to the same authorized Alliance + Plan + participant + expected screenshot schema.

A duplicate lookup must never disclose another tenant/participant's Evidence identity or hash result.

## Processing queues

A newly accepted upload persists source/provenance first, then dispatches classification. Classification persists an immutable OCR/classification attempt. A supported classification dispatches extraction, which persists a separate immutable extraction attempt and field records.

For Transfer Evidence, the selected expected screenshot kind is never trusted as classification truth. The classifier independently chooses one of the five supported Transfer kinds or `unknown`. An expected/detected mismatch does not become approved evidence.

Transfer extraction is routed to one of five schema-specific extractors. It does not use a generic Transfer field parser. The schema registry supplies the exact version, supported/required fields, classification and field-confidence thresholds, fixture corpus and destination Action.

Jobs carry scalar evidence IDs and use bounded retries. A terminal processing failure moves Evidence to `failed`; unsupported/ambiguous input moves it to `unsupported`. An authorized manager can retry terminal failed processing through the supported family-specific action. Retry creates new attempt history and never overwrites the failed attempt.

Use Horizon/queue diagnostics to inspect worker health. Do not manually update lifecycle or attempt rows to force progress.

## Persisted lifecycle

The common Evidence lifecycle is:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Exceptional persisted states are `unsupported`, `failed`, and `deleted`.

All initial Transfer Evidence requires human review. There is no auto-commit path regardless of classifier or field confidence.

There are intentionally no `delete_pending`, `purge_pending`, or `purged` rows. User deletion synchronously redacts the retained source and transitions to `deleted`. Retention of uncommitted evidence physically deletes the row after redaction, while committed evidence retains its minimal provenance tombstone after the binary is removed.

## Transfer review operations

The participant journey is:

`Transfer participant → Add in-game evidence → select expected class → upload → classify → extract → review/correct → duplicate check → preview destination facts/eligibility → explicit commit`

During review, operators should compare:

- raw OCR observation;
- normalized candidate;
- machine confidence and warnings;
- reviewer correction;
- source observation time;
- explicit validity boundary when required;
- current owner value/source/time;
- current conflicts/staleness/unknown requirements;
- previewed post-commit eligibility and remaining action.

Do not use Evidence review to manufacture unsupported facts. In particular:

- never calculate required Transfer Passes from Transfer Score;
- never mark `in_game_rules_verified=true` from the five v1 Transfer screenshot classes;
- never infer hidden official-group membership;
- never coerce novel invitation wording into an enum;
- never substitute Governor Power for target Power Cap or vice versa.

For mutable Governor status, score/pass and invitation screenshots, `valid_until` is required by the review Action. It is reviewer/owner product meaning, not an Evidence TTL.

If a participant's material Plan/window/target scope changes after review, do not force the old review through. The destination Action will reject it; re-review or capture newer evidence against the current scope.

## Bear Hunt commit recovery

The Bear Hunt commit sequence is intentionally not a distributed database transaction:

1. Evidence creates/resumes a commit attempt for an approved review.
2. `CommitReviewedBearHuntEvidence` builds reviewed scalar meaning and calls the `Operations/Results` owner Action.
3. Operations records an idempotent Bear Hunt report and deterministically recomputes result projections.
4. Evidence records the returned destination receipt.

If execution stops after step 3 and before step 4, retry through the normal application path. The reviewed meaning produces the same destination idempotency key. Operations returns the existing report receipt without adding damage again, and Evidence records the recovered receipt.

## Transfer commit recovery

The Transfer sequence follows the same cross-context pattern but the receipt is owned by `GameWorld/KingdomTransfers`:

1. Evidence creates/resumes an `evidence_transfer_commit_attempt` for one approved Transfer review.
2. `CommitReviewedTransferEvidence` builds only the typed reviewed scalar command for that schema.
3. The matching dedicated owner Action reacquires current authority and scope and opens the KingdomTransfers transaction.
4. The owner appends observations/condition/group history and stores one `transfer_evidence_receipt` under the stable destination idempotency key.
5. Evidence records only that returned scalar receipt and marks its acknowledgement attempt successful.

If execution stops after step 4 and before step 5, retry through the normal participant Evidence commit path. After current actor authorization, KingdomTransfers finds the existing destination receipt and returns it without appending observations again. Evidence records the recovered receipt.

A failed Evidence acknowledgement remains immutable attempt history. Do not repair an interrupted handoff by editing `transfer_observations`, target-condition/group tables, destination receipts or Evidence commit tables.

### Atomic score/pass handoff

`RecordTransferScorePassEvidence` appends Transfer Score, passes available and passes required inside one outer KingdomTransfers database transaction. The receipt is created only after all three owner writes succeed. A failure in any field/invariant rolls back all three writes and the receipt.

## Duplicate handling

Duplicate protections remain distinct:

- **Exact:** binary SHA-256 equality within the authorized evidence source scope. The existing Evidence record is reused and a second binary is not retained.
- **Visual:** perceptual-hash similarity is advisory. A visually similar screenshot remains reviewable and may represent newer game state.
- **Semantic:** an approved review produces a deterministic schema/scope/meaning fingerprint. Equivalent reviewed game state is blocked until an authorized manager explicitly resolves why it should be accepted.
- **Destination idempotency:** one immutable approved review uses one stable destination key; this protects retries/crash recovery, not semantic equivalence between different screenshots.

A newer screenshot should be reviewed with its real observation time/meaning. It remains importable when its semantic fingerprint differs.

Never expose hash matches, Evidence IDs, duplicate existence or semantic details across Alliances or unauthorized Transfer scopes.

## Owner-specific Transfer invariants

Transfer Evidence does not bypass existing owner rules:

- target Power Cap changes in protected later phases continue through the authoritative correction rules in `RecordTransferKingdomCondition`;
- official-group membership conflicts/revisions continue through `SaveTransferGroup`;
- source type is `evidence`, with the approved Evidence reference validated by the owner guard;
- observation selectors decide freshness/conflict semantics from owner history;
- eligibility is always derived by the KingdomTransfers evaluator and is never persisted by Evidence.

The participant preview uses the same evaluator with an in-memory substitution of reviewed facts. It leaves current `in_game_rules_verified` unchanged, so a screenshot cannot preview itself into manufactured eligibility.

## Deletion and retention

Evidence deletion and destination-domain correction are separate operations.

User deletion removes the retained source image plus sensitive OCR/raw field text, records an audited tombstone and moves Evidence to `deleted`. If the evidence already committed, the accepted Bear Hunt report or KingdomTransfers history remains unchanged.

The scheduled `evidence:enforce-retention` task runs daily at 03:20 application time with overlap protection:

- committed Evidence older than `EVIDENCE_COMMITTED_BINARY_RETENTION_DAYS` has the source binary and sensitive OCR/raw data redacted while stable source metadata, reviewed normalized meaning, commit history and destination receipt remain;
- uncommitted `deleted` Evidence is physically purged after `EVIDENCE_DELETED_RETENTION_DAYS`;
- uncommitted `failed`/`unsupported` Evidence is physically purged after `EVIDENCE_FAILED_RETENTION_DAYS`;
- other inactive uncommitted Evidence is physically purged after `EVIDENCE_UNCOMMITTED_RETENTION_DAYS`;
- active `classifying`, `extracting`, and `committing` Evidence is never purged by the retention pass.

Committed tombstones whose binary is already gone are excluded from subsequent retention candidate scans. This prevents long-lived committed history from starving newer expired evidence when the command processes a bounded batch.

Backups may contain binaries that were still inside their retention window when the backup was created. Restored environments must immediately resume the retention scheduler and must not expose restored evidence through public storage.

## Diagnostics and observability

Run:

```text
evidence:diagnostics
```

The command reports aggregate lifecycle, attempt, review/correction, duplicate, commit, retention and oldest-processing metrics. It intentionally emits no screenshot filename, OCR text, Player name, Alliance name, source hash, reviewed value or private Transfer fact.

For a single incident, correlate application audit/outbox events using request/trace IDs and non-secret identifiers already visible to authorized operators. Relevant events include upload accepted/rejected, exact/visual duplicate detection, classification/extraction start/completion/failure, retry, review approval, semantic duplicate detection/resolution, commit start/success/failure, destination idempotent replay, evidence deletion/redaction/purge, retention failure and destination owner record events.

Transfer destination events should be inspected in the `kingdoms.transfer_*` owner stream, not reconstructed from Evidence rows.

## Incident playbook

### Processing backlog

1. Check worker/Horizon health and `evidence:diagnostics` oldest-processing age.
2. Confirm Tesseract is installed in the deployed image and callable by the application user.
3. Confirm the Evidence disk is writable, private, and has capacity.
4. Restore worker capacity/runtime dependency first; do not mutate attempt statuses manually.
5. Retry only terminal failed processing through the supported product action.

### Transfer scope changed after review

1. Confirm the current Plan, participant, Transfer Window and target Kingdom.
2. Compare them with the review scope shown in the authorized Evidence panel/audit trail.
3. Do not retarget the existing approved review by editing IDs.
4. Re-review only if the existing screenshot still proves the current destination meaning; otherwise capture newer evidence.

### Suspected duplicate Transfer observations

1. Identify the approved Evidence review and destination idempotency key/receipt.
2. Confirm exactly one `transfer_evidence_receipt` exists for that destination key.
3. Re-run the supported commit if Evidence lacks acknowledgement; a replay must return the same receipt without adding owner history.
4. Distinguish that from a semantic duplicate across two screenshots; resolve semantic collisions only through the explicit review action.

### Wrong accepted Transfer fact

Deleting the screenshot is not domain correction. Use the relevant audited KingdomTransfers owner correction/history behavior. Power Cap corrections must continue to obey the phase/authoritativeness rules; official-group revisions must continue to obey membership conflicts.

### Privacy deletion

1. Use the normal family-specific Evidence deletion action so the binary and sensitive OCR/raw text are removed together and audit evidence remains.
2. Confirm the private image endpoint returns unavailable afterward.
3. Do not delete accepted destination history merely to satisfy Evidence retention/deletion.