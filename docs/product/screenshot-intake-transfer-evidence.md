# Screenshot Intake: Transfer Evidence

Status: Current complete capability — 2026-09-07

Owner of evidence provenance: `Intelligence/Evidence`  
Owner of accepted transfer truth: `GameWorld/KingdomTransfers`

Product contract: [Kingdom Transfer Planning](kingdom-transfer-planning.md)  
Official-rule source matrix: [Kingdom Transfer official-rules source matrix](kingdom-transfer-official-rules-source-matrix.md)

## Outcome

An authorized Transfer manager can open a participant's **Add in-game evidence** panel, choose the expected screenshot class, upload a private screenshot, review independent classification/extraction, correct only supported fields, preview the impact through the owner evaluator, resolve supported duplicate cases and explicitly commit the reviewed meaning.

Journey:

`participant → upload → classify → extract → review/correct → duplicate check → preview → commit`

All Transfer Evidence requires human review. There is no automatic commit path.

## Ownership and prohibitions

Evidence owns image storage, security/provenance, OCR/provider attempts, classification/extraction attempts, immutable review revisions, duplicate decisions, commit attempts/retries and retention.

KingdomTransfers owns Transfer Windows, participants, official groups, target conditions/capacity, accepted observations, freshness/conflict rules, capacity planning and eligibility.

The implementation must not:

- create a Transfer OCR bounded context or generic `transfer_ocr` schema;
- use a generic bag-of-fields destination contract;
- infer unsupported facts from arbitrary OCR text or nearby numbers;
- calculate required Transfer Passes from Transfer Score;
- create `in_game_rules_verified=true` from any screenshot class;
- persist Evidence-side eligibility truth;
- let Evidence mutate KingdomTransfers models directly;
- pass foreign Eloquent models across the context boundary.

## Scope

Transfer Evidence is valid only when:

- it is same-Alliance;
- `transfer_plan_id` and `transfer_participant_id` are present;
- the participant belongs to the Plan at upload;
- Plan/window/participant/target meaning is re-resolved at review, preview and commit.

Material scope drift never silently retargets reviewed evidence. It requires a new/revalidated review.

## Schema-wide contract

Each screenshot class is an explicit `EvidenceKind`, classifier target, extractor, fixture corpus and versioned schema descriptor.

Expected kind is only a routing expectation; classification remains independent. Expected/actual mismatch fails closed. Extractors may emit only schema-whitelisted fields. Machine extraction/confidence remains immutable provenance after human correction.

Reviewer-confirmed `observed_at` is required. `valid_until` is required only for facts whose owner freshness contract needs it; Evidence does not invent a global TTL.

Preview invokes `TransferEligibilityEvaluator` using hypothetical reviewed values in memory and persists nothing.

## Supported schemas

### Transfer Governor status

- kind: `transfer_governor_status`
- schema: `transfer-governor-status/1`
- destination: `RecordGovernorStatusEvidence`
- required reviewed field: `governor_power`
- reviewer supplies `observed_at` and current-use `valid_until`.

No unrelated transfer fact is proved by this schema.

### Transfer Score & Passes

- kind: `transfer_score_passes`
- schema: `transfer-score-passes/1`
- destination: `RecordTransferScorePassEvidence`
- required reviewed fields: `transfer_score`, `transfer_passes_available`, `transfer_passes_required`.

All three owner observations plus destination receipt commit atomically. Missing required Passes is never calculated from Transfer Score. The owner validates the current official observed-required range of 1–50.

### Transfer invitation

- kind: `transfer_invitation`
- schema: `transfer-invitation/1`
- destination: `RecordTransferInvitationEvidence`
- required reviewed field: `invitation_status`
- optional target Kingdom only when explicitly fixture-proven.

Allowed normalized invitation values are `none`, `ordinary_received`, `special_pending`, and `special_approved`. Unknown wording is unsupported rather than coerced.

### Target Kingdom transfer rules

- kind: `transfer_target_kingdom_rules`
- schema: `transfer-target-kingdom-rules/2`
- fixture corpus: `transfer-target-kingdom-rules-v2`
- destination: `RecordTransferKingdomRulesEvidence`

Supported reviewed fields are explicitly whitelisted:

- `target_kingdom_number` — required;
- `power_cap` — required;
- optional fixture-proven `kingdom_classification`;
- optional fixture-proven `hero_generation`;
- optional fixture-proven `truegold_level`;
- optional fixture-proven `character_age_threshold_days`.

The v2 schema does not infer missing classification/Hero/Truegold/age values. Absence means the review does not prove that field, so preview/commit preserves existing owner truth/unknown state rather than manufacturing a replacement.

Age-threshold review must satisfy the owner's current supported official range. Owner correction/phase invariants are revalidated at commit.

Semantic fingerprint includes schema version, window/target identity, every reviewed supported fact and observation boundary. v1 and v2 reviewed meanings therefore cannot collide.

### Official Transfer Group

- kind: `transfer_official_group`
- schema: `transfer-official-group/1`
- destination: `RecordOfficialTransferGroupEvidence`
- required reviewed fields: official group identifier and complete explicitly visible Kingdom membership list.

Hidden/off-screen membership is never inferred.

## Review model

All reviews are immutable revisions. The review surface exposes expected/detected kind, classification confidence/reason, raw/normalized candidates, field confidence/warnings, reviewer corrections, observation/freshness boundary where applicable, current owner fact/provenance, duplicate status and previewed eligibility impact.

Target-rules v2 review persists only the v2 whitelist fields. Commit commands carry scalar typed values plus Evidence/review/scope IDs.

## Duplicate semantics

Exact, visual and semantic duplicate concepts stay separate.

- exact duplicate: checksum-level, tenant/destination safe;
- visual duplicate: review warning only;
- semantic duplicate: deterministic reviewed-meaning fingerprint and explicit supported resolution.

Destination replay is not semantic duplication. Replaying the same immutable approved review uses the stable KingdomTransfers destination idempotency key and returns the existing receipt without appending owner truth.

## Destination boundary

Five dedicated owner Actions are supported:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Each destination reacquires current transfer-manage authority, checks existing owner receipt/idempotency, locks/re-resolves current Plan/participant/window/target scope for a new write, validates same-Alliance Evidence provenance/review, validates typed owner invariants, appends owner history/audit/outbox and returns only scalar receipt identity.

Evidence deletion/redaction after commit never removes accepted owner history.

## Preview contract

Preview and live reads use the same `TransferEligibilityEvaluator`.

Preview substitutes only facts actually reviewed by that schema. It cannot erase an unrelated current conflict or fill a required fact the schema does not prove. Target-rules v2 can therefore change Hero Generation, Truegold or character-age requirement outcomes only when the corresponding reviewed field is present.

No schema can set the final `in_game_rules_verified` gate or resource-protection verification unless explicitly supported by a future versioned schema/fixture contract.

## Authorization

All Evidence routes require current Transfer management authority and password confirmation for mutation. Evidence re-resolves participant scope, and owner commit reacquires authorization again.

Cross-Alliance Evidence/Plan/participant IDs must not disclose source existence.

## Retry/recovery

- processing retry is allowed only from supported terminal failure states;
- review revisions are immutable;
- destination commit attempts record success/failure/receipt;
- retry after owner commit but before Evidence acknowledgement returns the existing receipt;
- score/pass owner commit remains all-or-nothing.

## Fixtures/tests

Every schema has deterministic positive and negative fixture coverage for supported representations, low confidence, wrong class, missing required fields, adjacent unrelated values, duplicates and semantically changed meaning.

Target-rules v2 fixture/test coverage must prove exact v2 schema selection; Hero Generation, Truegold and character-age threshold extraction/review; optional-field absence remains absence; owner preview/commit carries only reviewed fields; target/window scope mismatch fails closed; and replay remains idempotent.

A field is unsupported until fixture tests prove classification, extraction, normalization, review validation and destination behavior.

## Release acceptance

Transfer Evidence is current complete only when schema registry/fixtures match the documented versions; review UI exposes all supported fields/provenance; target-rules v2 preview/commit tests are green; cross-context architecture tests remain green; no generic Transfer OCR/schema exists; frontend and visual checks pass; Intelligence Verification, Architecture V3, CodeQL and Dependency Review pass; and operations/reference/product docs agree with implementation.
