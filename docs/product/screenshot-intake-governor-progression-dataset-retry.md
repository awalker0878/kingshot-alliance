# Screenshot Intake: Governor Progression — Dataset Retry Pinning

**Status:** Normative product-contract amendment — 2026-08-26  
**Applies to:** `docs/product/screenshot-intake-governor-progression.md`  
**Source of truth:** This amendment is binding for the current implementation and must be consolidated into the primary Governor Progression product contract during final reconciliation.

## Requirement discovered during implementation verification

A processing retry must not silently reinterpret an existing Governor Progression screenshot under a newer `GameWorld/Progression` release. The first normalization attempt establishes the Evidence record's Progression dataset pin for the v1 automatic processing lifecycle, including when that attempt fails.

### GP-DATASET-RETRY-01 — Automatic retry preserves the original pin

When Governor Progression Evidence has any prior normalization attempt, every automatic processing retry MUST reuse the dataset ID and checksum from the earliest normalization attempt for that Evidence record. It MUST load that exact immutable release through `ProgressionDatasetQuery::require(...)`.

If the pinned release is unavailable or its checksum no longer matches, retry MUST fail closed. It MUST NOT fall forward to `latest()`.

### GP-DATASET-RETRY-02 — First normalization may use current latest

Only Evidence with no prior normalization attempt may select the current latest published Progression dataset. Once the first normalization attempt is created, its dataset ID/checksum becomes the automatic-processing pin for that Evidence record.

### GP-DATASET-RETRY-03 — Dataset migration is explicit, not retry behavior

Moving an existing Evidence record to a newer Progression dataset is a distinct explicit re-normalization product action. V1 does not provide that action. A normal retry, job retry, extraction retry, process restart or queue redelivery MUST therefore preserve the original dataset pin.

### GP-DATASET-RETRY-04 — Provenance and tests

Normalization attempts remain append-only and retain their normalizer version and pinned dataset. Verification MUST cover that a prior failed normalization attempt pins subsequent retry normalization to the same dataset and that automatic retry cannot silently use a newer release.

## Delivery-ledger reconciliation

This requirement is part of GP-06 (dataset-pinned normalization), GP-19 (automated tests) and GP-20 (repository-wide release verification). None of those items may be marked complete until automatic retry pin preservation is implemented and regression-protected.
