# Screenshot Intake application contracts

Status: Current for the first Bear Hunt Screenshot Intake implementation.

Screenshot Intake is an authenticated web capability. It does not expose a public API or webhook contract in the first release. The routes below are application boundaries over owner actions; they do not transfer persistence ownership to controllers or read models.

## Ownership boundary

`Intelligence/Evidence` owns uploaded source evidence, provenance, classification/extraction attempts, extracted-field confidence, review revisions, duplicate decisions, commit-attempt history, and retention.

`Operations/Results` owns accepted Bear Hunt battle reports, report entries, result baselines, and the recomputed Event Player result projection.

`app/Workflows/ScreenshotIntake` coordinates the scalar handoff and owns no business persistence.

## Web routes

All routes require authentication, authenticated-session validation, verified email, an active Player context, and Bear Hunt management authorization. Mutations that resolve a semantic collision, commit domain state, or delete retained evidence additionally require recent password confirmation.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/events/{occurrence}/screenshot-intake` | Render the authorized Screenshot Intake workspace for one Bear Hunt occurrence. |
| `POST` | `/events/{occurrence}/screenshot-intake` | Upload one private screenshot and start classification. |
| `GET` | `/events/{occurrence}/screenshot-intake/{evidence}/image` | Stream the retained private source image to an authorized manager with no-store headers. |
| `PUT` | `/events/{occurrence}/screenshot-intake/{evidence}/review` | Persist a new immutable review revision over one completed extraction. |
| `POST` | `/events/{occurrence}/screenshot-intake/{evidence}/retry` | Retry terminal failed processing by creating new attempt history. |
| `POST` | `/events/{occurrence}/screenshot-intake/reviews/{review}/resolve-duplicate` | Record an audited justification that a semantic collision is a distinct report. |
| `POST` | `/events/{occurrence}/screenshot-intake/reviews/{review}/commit` | Commit an approved review through the Screenshot Intake workflow into `Operations/Results`. |
| `DELETE` | `/events/{occurrence}/screenshot-intake/{evidence}` | Delete/redact retained evidence without deleting accepted Bear Hunt result state. |

Resource IDs are ULIDs. Route bindings do not authorize access by themselves; every owner action revalidates current scope/authority and relevant state.

## Review command

The review boundary accepts:

- `extraction_attempt_id` — ULID of a completed extraction belonging to the Evidence;
- optional `report_timestamp_text`, maximum 64 characters;
- one to 100 rows containing:
  - `row_ordinal`;
  - `included`;
  - nullable resolved scalar `player_id`;
  - reviewed `player_name`;
  - nullable `reported_rank` from 1 to 999;
  - nullable non-negative `damage_points`;
  - optional correction reason.

The review action does not overwrite extracted fields. It creates a revision preserving the machine value/confidence separately from the reviewed resolution. Every included row must resolve to an active eligible Player before the review can become approved.

## Operations scalar command

The cross-context destination contract is equivalent to:

```text
RecordBearHuntBattleReport(
  actorPlayerId,
  occurrenceId,
  sourceEvidenceId,
  sourceCommitAttemptId,
  idempotencyKey,
  reportFingerprint,
  reportTimestampText,
  entries[] { player_id, reported_rank?, damage_points }
) -> BearHuntBattleReportReceipt
```

Only scalar IDs, primitive values, arrays and an immutable receipt cross the context boundary. No Eloquent model crosses from Evidence to Operations or back.

Operations revalidates:

- current actor management authority inside the transaction;
- Results capability availability;
- Alliance scope and `bear-hunt` Event type;
- occurrence ownership under the locked Event;
- Player identity, active roster presence, Event eligibility and uniqueness;
- non-negative damage and optional valid rank;
- destination idempotency and report-fingerprint uniqueness.

## Idempotency and receipt contract

The Evidence commit attempt derives a stable SHA-256 idempotency key from immutable reviewed meaning. Operations stores it with the accepted report.

A replay using the same key and same report fingerprint returns the existing report receipt and recomputes from the ledger; it does not create another report. Reusing the key for different meaning is rejected.

The destination receipt contains only scalar result data needed by the coordinator, including the report identifier, entry count, replay indicator and recomputed Player result values. Evidence persists the receipt for recovery and diagnostics.

## Error semantics

Validation failures use normal Laravel validation errors. Authorization failures are denied by the owner services. A retained image that has been lifecycle-deleted returns a gone/not-available response rather than recreating or exposing the binary.

Processing failures are durable Evidence attempt outcomes with a privacy-safe failure code. Raw provider/OCR payloads must not be returned in validation messages or diagnostics.
