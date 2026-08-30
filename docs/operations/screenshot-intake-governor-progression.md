# Screenshot Intake: Governor Progression Operations

Status: Current complete capability — verified 2026-08-30

## Operational boundary

Governor Progression screenshots are private `Intelligence/Evidence` objects. Operators must not repair accepted observations by editing Evidence, Roster or Progression persistence directly. Normal application Actions, retries and explicit Roster correction/removal paths are the supported recovery mechanisms.

## Expected lifecycle

```text
uploaded
  -> classifying
  -> classified
  -> extracting
  -> normalizing
  -> needs_review
  -> approved
  -> committing
  -> committed
```

Exceptional states include unsupported, failed and deleted/redacted. Machine retries append attempts rather than overwriting earlier classification/extraction/normalization output.

## Triage checklist

For failed/stuck intake:

1. Confirm the user still has access to the Alliance and target roster entry.
2. Confirm the Evidence object has Governor/Roster scope and no occurrence/Transfer scope references.
3. Confirm private source storage and upload scan succeeded.
4. Inspect latest classification and expected-versus-detected kind; generic Charm inventory/collection UI should fail closed.
5. Inspect extraction schema/version and candidates; verify compound Gear/Charm fields are separated and do not manually add unsupported fields.
6. Inspect normalization history and the earliest Progression dataset ID/checksum.
7. Confirm any retry reused that earliest pin rather than selecting a newer dataset.
8. If normalization reports an unmatched Hero, require authorized canonical selection; never create Progression identity from OCR.
9. Confirm the approved review still targets the same roster entry and pinned dataset.
10. Inspect semantic duplicate state before retrying commit.
11. Inspect destination idempotency key/receipt before assuming an Evidence acknowledgement failure means the Roster write failed.

## Processing retry behavior

The first normalization attempt establishes the automatic-processing Progression dataset pin, including when that attempt fails.

- Evidence with no normalization history may use the current latest dataset for its first attempt.
- Once history exists, every automatic retry, queue redelivery or process restart must reuse the dataset ID/checksum from the earliest normalization attempt.
- Retry must fail closed if that exact release is unavailable or checksum mismatches.
- Never substitute `latest()` for a pinned retry.
- Moving Evidence to a newer dataset is a distinct explicit re-normalization product action; v1 does not provide it.

Commit retry uses the stable destination idempotency key associated with the immutable approved review. If Roster already committed and Evidence acknowledgement failed, retry must receive the existing authorized Roster receipt and mark Evidence acknowledgement; do not delete/re-import the Roster observation.

## Provenance/interface boundary

Roster validates Governor-specific Evidence provenance through the dedicated Evidence-owned Governor Progression reference lookup. Do not add Governor-specific methods to the shared family-neutral Evidence reference contract to work around a failing destination write.

Before a destination write, verify exact Evidence/review, Alliance, roster entry, Player, Evidence kind, schema and pinned dataset ID/checksum. A provenance mismatch is a fail-closed condition.

## Catalogue-bound review checks

- Canonical Hero identity must exist in the pinned dataset.
- Hero Gear enhancement/Mastery values must fit maxima published by the pinned tables.
- Governor Gear tier/star values, when both reviewed, must represent a published pinned step.
- Charm level must fit the pinned Charm ladder.
- Gear/Charm slot IDs are structural observation keys, not canonical Progression identities.
- OCR-visible Charm names remain Evidence provenance; do not synthesize or manually enter a `charm_id` in v1.

A directly visible structural/screen value is not permission to modify `GameWorld/Progression`.

## Dataset changes

A newer Progression release does not invalidate or rewrite already-normalized Evidence or accepted observations. Historical state remains pinned to its original ID/checksum.

A checksum mismatch during destination validation is fail closed. Do not remove the checksum, change the dataset ID or substitute latest data to force a commit.

## Duplicate handling

- Exact duplicate: block/reuse only inside authorized Governor/Roster Evidence scope.
- Visual duplicate: warning only; distinct Evidence remains reviewable.
- Semantic duplicate: require supported explicit resolution before commit.
- Genuinely newer observation: remains importable when the observation boundary is newer.
- Destination idempotency: replay returns the existing receipt rather than creating duplicate owner history.

Do not expose cross-Alliance Evidence existence through duplicate diagnostics.

## Deletion and retention

Evidence deletion/redaction must not cascade into an accepted `GovernorProgressionObservation`. After commit, Evidence may retain only minimum provenance/tombstone/receipt required by retention policy while source image/OCR material is purged.

If an accepted observation is wrong, use an explicit audited Roster correction/removal capability. Evidence deletion is not correction.

## Privacy-safe diagnostics

Logs/audit/outbox may identify lifecycle event names, schema/version, attempt/receipt IDs and privacy-safe failure codes. They must not emit screenshot pixels, OCR text, raw content hashes, Governor names, Player identity details or cross-tenant duplicate information.

## Verification before release

Do not mark the family complete until the same immutable candidate passes:

- clean PostgreSQL migration/install;
- Governor schema/fixture/classification/extraction tests;
- dataset-retry pin regression tests;
- Governor provenance interface/Transfer Evidence regression tests;
- pinned catalogue-bound validation tests;
- authorization/scope-drift tests;
- Roster observation/idempotency/recovery tests;
- full PHP suite, Pint and PHPStan/Larastan;
- frontend lint/format/type/build;
- accessibility and visual regression;
- architecture verification;
- CodeQL/dependency/security gates;
- documentation reconciliation.

Record candidate SHA and workflow evidence in the product delivery ledger before changing status to Complete.