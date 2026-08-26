# Screenshot Intake: Governor Progression Operations

Status: Active delivery — 2026-08-26

## Operational boundary

Governor Progression screenshots are private `Intelligence/Evidence` objects. Operators must not repair accepted observations by editing Evidence or Roster tables directly. Normal application Actions, retries and explicit owner correction/removal paths are the supported recovery mechanisms.

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

Exceptional states are unsupported, failed and deleted/redacted. Machine retries append attempts rather than overwriting earlier OCR/classification/extraction/normalization output.

## Triage checklist

For a failed or stuck Governor Progression intake, inspect in this order:

1. Confirm the user still has access to the Alliance and target roster entry.
2. Confirm the Evidence object is in the explicit Governor/Roster scope and has no occurrence/Transfer scope references.
3. Confirm private source storage and upload scan succeeded.
4. Inspect the latest classification attempt and expected-versus-detected kind.
5. Inspect extraction schema/version and field candidates; do not add unsupported fields manually.
6. Inspect the normalization attempt and pinned Progression dataset ID/checksum.
7. If normalization reports an unmatched Hero, require authorized human review/canonical selection; never create a Progression identity from OCR.
8. Confirm the approved review still targets the same roster entry and pinned dataset.
9. Inspect semantic duplicate state before retrying commit.
10. Inspect the destination idempotency key and receipt before assuming a failed acknowledgement means the Roster write failed.

## Retry behavior

Processing retry creates/resumes normal application attempts; it does not mutate historical attempts. Commit retry uses the stable destination idempotency key associated with the immutable approved review.

If Roster already committed and Evidence acknowledgement failed, the retry should receive the existing authorized Roster receipt and mark the Evidence commit acknowledged. Do not delete the Roster observation and re-import it.

## Dataset changes

A newer Progression release does not invalidate or rewrite an already-normalized screenshot. If the operator/user intentionally wants current normalization rules/data, start an explicit new normalization/review path. Historical observations remain pinned to their original dataset ID/checksum.

A checksum mismatch during destination validation is a fail-closed condition. Do not substitute `latest()` or remove the checksum to make the commit succeed.

## Duplicate handling

- Exact duplicate: block/reuse only within the authorized Governor/Roster Evidence scope.
- Visual duplicate: warning only; distinct Evidence remains reviewable.
- Semantic duplicate: require the supported explicit resolution before commit.
- Newer observation: remains importable when the reviewed meaning/observation boundary is genuinely newer.
- Destination idempotency: retry returns the existing receipt instead of creating duplicate owner history.

Do not expose cross-Alliance Evidence existence through duplicate diagnostics.

## Deletion and retention

Evidence deletion/redaction must not cascade into an accepted `GovernorProgressionObservation`. After commit, Evidence may retain only the minimum provenance/tombstone/receipt required by retention policy while source image/OCR material is purged.

If an accepted observation is wrong, use an explicit audited Roster correction/removal capability. Do not treat Evidence deletion as correction.

## Privacy-safe diagnostics

Logs/audit/outbox may identify lifecycle event names, schema/version, attempt/receipt IDs and privacy-safe failure codes. They must not emit screenshot pixels, OCR text, raw content hashes, Governor names, Player identity details or cross-tenant duplicate information.

## Verification before release

Do not mark the family complete until one immutable candidate passes:

- clean PostgreSQL migration/install;
- Governor schema/fixture tests;
- authorization/scope drift tests;
- Roster observation/idempotency/recovery tests;
- PHP test suite, Pint and PHPStan;
- frontend lint/format/type/build;
- accessibility and visual regression;
- architecture verification;
- CodeQL/dependency/security gates;
- documentation reconciliation.

Record the candidate SHA and workflow evidence in the product delivery ledger before changing the capability status to Complete.
