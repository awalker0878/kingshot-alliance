# Screenshot Intake: Transfer Evidence

Status: Current complete capability — verified 2026-08-30

Screenshot Intake: Transfer Evidence is the second supported `Intelligence/Evidence` evidence family after Bear Hunt battle reports. It extends the existing Evidence capability with five explicit, versioned Transfer screenshot schemas and a reviewed handoff into `GameWorld/KingdomTransfers`.

This document is the implementation source of truth. A delivery item is complete only when the documented behavior, ownership, authorization, persistence, provenance, duplicate semantics, queue/retry behavior, UX, accessibility, localization, fixtures, automated tests, visual proof, observability, operations guidance and repository release gates are complete and reconciled.

## Product outcome

An authorized Transfer manager can start from a Transfer participant, select **Add in-game evidence**, choose the screenshot class they expect, upload one supported screenshot, have the application independently classify it, extract only fixture-proven fields, review/correct the resulting candidates, understand confidence/source time/freshness/current conflicts, preview the destination facts and eligibility impact, then explicitly commit the reviewed meaning.

The supported journey is:

`Transfer participant → Add in-game evidence → select/expect screenshot class → upload → classify → extract → review/correct → duplicate check → preview destination facts and eligibility impact → commit`

All initial Transfer Evidence requires human review. There is no automatic commit path.

## Non-goals and hard prohibitions

This extension must not:

- create a Transfer OCR bounded context or any other Transfer-specific OCR context;
- create a generic `transfer_ocr` schema;
- create a generic bag-of-fields extraction model;
- create an unconstrained polymorphic Evidence target abstraction merely to support Transfer screenshots;
- infer unsupported game facts from arbitrary OCR text or nearby numbers;
- calculate `transfer_passes_required` from Transfer Score;
- treat any generic Transfer screenshot as proof that `in_game_rules_verified=true`;
- materialize or persist a derived `eligible` flag in Evidence;
- let Evidence own or directly mutate Transfer Windows, participants, official Transfer Groups, target-Kingdom conditions, transfer observations, freshness or eligibility;
- allow Eloquent models from one context to cross into another context's application contract.

## Capability ownership

### `Intelligence/Evidence` owns

- uploaded image objects on private storage;
- upload security scanning and source checksums;
- immutable source and derived representations;
- OCR/provider attempts and their implementation/model/ruleset provenance;
- expected screenshot class;
- independent classification attempts and confidence;
- schema-specific extraction attempts;
- raw and normalized candidates, confidence, bounds and warnings;
- review revisions and manual corrections/exclusions;
- exact, visual and semantic duplicate decisions;
- commit attempts, stable handoff idempotency keys and destination receipts;
- retry/recovery state;
- redaction, deletion and retention lifecycle;
- scalar Plan/participant/window/target references required to authorize and explain the handoff.

### `GameWorld/KingdomTransfers` owns

- Transfer Windows and phase boundaries;
- Transfer Plans and participants;
- official Transfer Groups and Kingdom membership history;
- target-Kingdom conditions and correction history;
- Governor transfer observations;
- observation freshness and validity semantics;
- current/conflicting fact evaluation;
- eligibility and requirement calculations;
- owner-side destination idempotency;
- audit/outbox semantics for accepted Transfer mutations.

Evidence may persist scalar Transfer identifiers, but those identifiers do not transfer aggregate ownership. Destination Actions always re-resolve owner state.

## Evidence scope

Persistence is generalized only as required for explicit Transfer participant Evidence.

### Valid Bear Hunt Evidence scope

- `occurrence_id` present;
- `transfer_plan_id` absent;
- `transfer_participant_id` absent.

### Valid Transfer participant Evidence scope

- `occurrence_id` absent;
- `transfer_plan_id` present;
- `transfer_participant_id` present;
- participant belongs to the Plan and Alliance at upload;
- current Transfer Window and target Kingdom, where relevant, are re-resolved at review, preview and commit.

Mixed or incomplete scope combinations fail closed at both application and database boundaries.

Exact duplicate lookup is tenant- and destination-safe. It must not disclose Evidence from another Alliance, Plan, participant or unauthorized scope.

## Schema-wide contract

There is no generic Transfer extractor. Each supported screenshot class is a separate `EvidenceKind`, classifier target, extractor, fixture corpus and versioned schema descriptor.

The following are review/scope metadata, not generic OCR fields: Alliance ID, Plan ID, participant ID, Transfer Window ID, owner target Kingdom ID, `observed_at`, and `valid_until`.

Every v1 schema independently defines:

- schema version;
- fixture corpus;
- supported and required extracted fields;
- normalization rules;
- classification threshold;
- field-confidence threshold;
- review requirements;
- semantic duplicate fingerprint;
- destination owner Action;
- preview behavior.

For all v1 schemas:

- minimum classification confidence is `0.55`;
- minimum supported field confidence is `0.55`;
- expected kind is only a routing expectation; actual classification is independent;
- expected/actual mismatch is unsupported and cannot proceed to commit;
- all extracted facts require human review even above threshold;
- machine extraction/confidence is immutable provenance after human correction;
- unsupported fields may not cross extraction, review or commit;
- preview reuses owner eligibility semantics and does not persist hypothetical state;
- preview and commit never synthesize `in_game_rules_verified`.

## Supported screenshot schemas

### 1. Transfer Governor status — `transfer_governor_status`

**Schema version:** `transfer-governor-status/1`  
**Fixture corpus:** `transfer-governor-status-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordGovernorStatusEvidence`

Purpose: record the Governor's explicitly displayed current Power.

**Fixture-proven extracted fields**

- `governor_power` — required, non-negative integer.

No other Transfer fact is supported by this schema.

**Normalization**

- preserve exact raw OCR text;
- remove only fixture-proven visual grouping separators;
- normalize only a fully displayed unambiguous integer;
- no `K`/`M`/`B` magnitude inference in v1;
- adjacent Power Caps and unrelated numbers are negative fixtures.

**Review requirements**

- `observed_at` required;
- `valid_until` required for current-use eligibility evidence and supplied under explicit KingdomTransfers/reviewer behavior, never hidden Evidence TTL;
- current participant/window scope is re-resolved at review and commit;
- reviewer explicitly confirms/corrects `governor_power`.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + governor_power + observed_at-boundary`

**Preview**

Substitute only Governor Power plus reviewed observation time/validity into the existing eligibility input. Leave invitation, passes, target/group facts and `in_game_rules_verified` unchanged.

### 2. Transfer Score & Passes — `transfer_score_passes`

**Schema version:** `transfer-score-passes/1`  
**Fixture corpus:** `transfer-score-passes-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferScorePassEvidence`

Purpose: atomically record the three explicitly displayed score/pass facts from one supported screen.

**Fixture-proven extracted fields**

- `transfer_score` — required, non-negative integer;
- `transfer_passes_available` — required, non-negative integer;
- `transfer_passes_required` — required, non-negative integer.

All three are required. Missing `transfer_passes_required` is never calculated from Transfer Score or any other fact.

**Normalization**

- each number is extracted only from its own fixture-proven label/region;
- remove grouping separators only when lossless;
- abbreviated/partial values are unsupported in v1;
- adjacent unrelated numbers never satisfy a required field.

**Review requirements**

- `observed_at` required;
- `valid_until` required for current eligibility use;
- current target Kingdom required as owner scope and re-resolved at review/commit;
- reviewer explicitly confirms/corrects all three numbers;
- one missing/invalid number blocks approval.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + target_kingdom_id + transfer_score + transfer_passes_available + transfer_passes_required + observed_at-boundary`

**Preview**

Display Transfer Score before/after and substitute only pass facts consumed by eligibility. All three owner observations commit atomically in one KingdomTransfers transaction or none survive.

### 3. Transfer Invitation — `transfer_invitation`

**Schema version:** `transfer-invitation/1`  
**Fixture corpus:** `transfer-invitation-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferInvitationEvidence`

**Fixture-proven extracted fields**

- `invitation_status` — required;
- `target_kingdom_number` — optional only when explicitly visible in a supported fixture.

Allowed normalized invitation values are exactly:

- `none`;
- `ordinary_received`;
- `special_pending`;
- `special_approved`.

Unknown wording is unsupported and not coerced.

**Normalization**

- only fixture-proven invitation phrases map to the four owner enum values;
- target Kingdom accepts only explicit supported Kingdom-number forms;
- unrelated Kingdom numbers are negative fixtures.

**Review requirements**

- `observed_at` required;
- `valid_until` required for current eligibility use;
- current target/window re-resolved at review and commit;
- if target number is extracted, it must resolve to the same current owner target;
- reviewer explicitly confirms/corrects invitation status.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + target_kingdom_id + invitation_status + observed_at-boundary`

**Preview**

Substitute only invitation status/time/validity. Leave all other eligibility facts unchanged.

### 4. Target Kingdom transfer rules — `transfer_target_kingdom_rules`

**Schema version:** `transfer-target-kingdom-rules/1`  
**Fixture corpus:** `transfer-target-kingdom-rules-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferKingdomRulesEvidence`

**Fixture-proven extracted fields**

- `target_kingdom_number` — required;
- `power_cap` — required, non-negative integer;
- `kingdom_classification` — optional and only when the supported fixture explicitly displays one of the versioned recognized phrases.

Absence of a classification candidate means **no classification fact is proved**. Review and commit must not manufacture `unknown` merely because the owner enum has an `unknown` value. The destination Action receives/changes classification only when a fixture-proven reviewed classification is present.

**Normalization**

- target number comes only from the fixture-proven target-Kingdom label/region;
- Power Cap comes only from its explicit label/region;
- classification maps only explicit recognized phrases;
- nearby Governor Power/score numbers are negative fixtures.

**Review requirements**

- `observed_at` required;
- no Evidence-global `valid_until` is invented for window-scoped conditions;
- reviewed target number must resolve to the participant's current owner target;
- reviewer confirms/corrects Power Cap and any classification candidate;
- owner correction/phase invariants are revalidated at commit.

**Semantic fingerprint**

`schema_version + transfer_window_id + target_kingdom_id + power_cap + optional_fixture_proven_classification + observed_at-boundary`

The fingerprint distinguishes “classification not proved” from an explicitly fixture-proven classification value.

**Preview**

Substitute only facts actually proved by the review. If classification is absent, current classification remains unchanged/unknown according to owner truth; do not synthesize a replacement.

### 5. Official Transfer Group — `transfer_official_group`

**Schema version:** `transfer-official-group/1`  
**Fixture corpus:** `transfer-official-group-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordOfficialTransferGroupEvidence`

**Fixture-proven extracted fields**

- `official_group_identifier` — required normalized string;
- `kingdom_number` — required repeatable field; reviewed meaning is a non-empty sorted unique list.

v1 supports complete fixture-proven membership only. Partial/off-screen membership cannot be committed as complete membership.

**Normalization**

- trim only fixture-proven label whitespace for the group identifier;
- normalize Kingdom numbers only from explicit supported forms;
- reviewed membership is deterministically deduplicated/sorted;
- hidden/off-screen Kingdoms are never inferred.

**Review requirements**

- `observed_at` required;
- no hidden global TTL;
- reviewer explicitly confirms/corrects group identifier and complete membership.

**Semantic fingerprint**

`schema_version + transfer_window_id + official_group_identifier + sorted_unique_kingdom_numbers + observed_at-boundary`

**Preview**

Substitute only explicitly proved current-window group membership. If both relevant Kingdom memberships are not proved, preview remains unknown rather than inferring membership.

## Fixture corpus contract

Each schema owns a synthetic/redacted fixture corpus. At minimum every corpus must include executable coverage for:

- canonical supported screenshot/OCR document;
- alternate supported resolution/aspect-ratio representation;
- safe crop/scale representation where supported;
- common numeric grouping presentation;
- low-confidence/blurred text;
- adjacent unrelated numbers that must not be extracted;
- missing required field;
- unsupported UI variant;
- wrong screenshot class;
- recompressed/visually similar duplicate;
- semantically equal meaning;
- semantically newer/different meaning.

A field is not supported until deterministic fixture tests prove classification, extraction, normalization, review validation and semantic fingerprint behavior for it.

## Classification and extraction

The user-selected kind is stored as `expected_kind`. Classification independently produces an actual kind/confidence/reason. A mismatch fails closed and remains reviewable as unsupported/mismatch evidence; the system never silently trusts the expected kind.

Extraction is routed only to the actual explicit supported schema. Extractors may emit only fields registered for that schema. Unsupported OCR text is retained as provenance but never promoted into a candidate merely because it resembles a Transfer value.

Each extraction attempt records extractor key/version/schema version/input checksum/overall confidence and immutable field candidates with raw text, normalized value, confidence, row/bounds where available and warnings.

## Human review

All v1 Transfer Evidence requires review. Review creates immutable revisions; corrections never overwrite machine extraction provenance.

The review surface must make understandable:

- expected and detected screenshot class;
- classification confidence/reason;
- raw observation;
- normalized candidate;
- field confidence and warnings;
- reviewer correction;
- upload time/source metadata/visible timestamp if supported;
- reviewer-confirmed `observed_at`;
- `valid_until` when required by owner semantics;
- current owner fact/source/time/validity/conflict state;
- visual duplicate warning;
- semantic duplicate decision;
- previewed destination facts and eligibility impact.

Review must re-resolve current Alliance, Plan, participant, window and target scope. If material destination scope changed since upload/review, the reviewed meaning cannot silently retarget. It is rejected or requires a new review against the new scope.

## Duplicate semantics

Three duplicate layers remain distinct.

### Exact duplicate

SHA-based duplicate lookup is scoped to the authorized Alliance/Plan/participant/expected kind and never discloses cross-tenant evidence.

### Visual duplicate

Perceptual similarity is a warning/review aid, not an automatic same-meaning decision.

### Semantic duplicate

Each schema has its deterministic reviewed-meaning fingerprint described above. Same meaning in the same Transfer scope is blocked from repeated commit unless the supported duplicate-resolution flow records an explicit reviewer decision/justification.

A genuinely newer observation remains importable because observation time/scope is part of stable reviewed meaning. Semantic duplicate detection is not destination idempotency.

## Destination handoff and owner Actions

Evidence coordinates the cross-context handshake but does not mutate Transfer state itself.

There are five dedicated owner Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

They are backed by shared KingdomTransfers owner-internal observation/condition/group writers so authorization/validation is not duplicated across public Actions.

Every destination Action must:

- reacquire current actor/Alliance authority;
- resolve and lock current Plan/participant/window/target scope;
- validate expected window/target scope against reviewed Evidence;
- validate Evidence provenance and schema version through scalar contracts;
- validate typed owner values/invariants;
- append history rather than overwrite prior observations;
- preserve owner correction history;
- use stable destination idempotency;
- emit owner audit/outbox events;
- return a scalar receipt only.

Score/pass commit is atomic: Transfer Score, available passes and required passes are accepted in one owner transaction or all roll back.

### Crash recovery

Evidence creates a stable destination idempotency key from the approved review meaning, starts a commit attempt, calls the owner Action, then records the scalar receipt.

If the owner transaction succeeds but the Evidence acknowledgement fails/crashes, retry uses the same owner idempotency key. KingdomTransfers returns the existing receipt without appending duplicate observations, after current actor authorization. Evidence can then mark the attempt succeeded.

Failed Evidence attempts remain historical; retry creates/reuses the correct handoff state without changing reviewed meaning.

## Freshness and time semantics

KingdomTransfers owns freshness. Evidence distinguishes:

- upload time;
- file/source metadata time when available;
- fixture-proven visible in-game timestamp when a future/current schema explicitly supports one;
- reviewer-confirmed `observed_at`;
- owner/reviewer `valid_until` for mutable observations when required.

Evidence has no hidden global Transfer TTL.

Mutable Governor Power, score/pass and invitation observations require explicit validity for current-use eligibility. Window-scoped target conditions/groups follow owner window/history semantics instead.

Missing, stale, conflicting or non-authoritative facts remain `needs_verification`/corresponding requirement states. Evidence never manufactures eligibility.

## Preview contract

Preview is read-only and owner-evaluated. It starts from current authoritative KingdomTransfers facts and creates a hypothetical eligibility input by substituting only the facts actually proved by the approved review.

Preview must display current versus after-review outcome/primary action and the exact facts being proposed. It must never write owner state and must never set `in_game_rules_verified`.

Actual commit re-resolves all owner state again; a successful preview is not a commit authorization token.

## Authorization and security

Upload/review/duplicate resolution/preview/commit/retry/image access/deletion are protected by Alliance/Transfer authorization appropriate to the operation.

Protected mutations re-resolve relevant scope instead of trusting route-bound foreign models or stale request state. Cross-context contracts contain scalar IDs/enums/value objects only.

Raw OCR, transfer values and image hashes must not be copied into privacy-unsafe logs. Audit/outbox metadata uses IDs, kind/schema/action/status/counts and privacy-safe failure codes.

Images are private/no-store. Deletion redacts binary/OCR/raw extraction according to Evidence retention policy but never cascades into already accepted KingdomTransfers historical state.

## Queue, retry and observability

Classification/extraction use the existing Evidence queue/provider machinery. Jobs are retry-safe against immutable Evidence/source checksums and versioned attempts.

Operational evidence includes:

- classification/extraction attempt state;
- commit attempt state and privacy-safe failure code;
- owner receipt ID and destination IDs;
- audit/outbox events for upload/classification mismatch/review/duplicate resolution/commit start/success/failure/deletion and owner acceptance;
- bounded plan-scoped Evidence summaries without per-row query explosion.

## Participant UX

Transfer participant cards expose **Add in-game evidence** without creating a separate OCR workflow.

Required responsive states include:

- empty/no Evidence;
- choose class/upload;
- scanning/classifying/extracting;
- unsupported/mismatch;
- needs review;
- low-confidence warning;
- possible visual duplicate;
- semantic duplicate blocked/resolution;
- approved/preview ready;
- committing;
- committed with destination receipt;
- failed/retryable;
- deleted/redacted;
- Plan/participant no longer mutable/read-only scope-change state.

The UI is mobile-first, keyboard operable, has explicit labels and accessible status text, does not rely on color alone, and avoids wide review tables on small screens.

All new user-facing strings must be localized through repository conventions.

## Persistence contract

The narrow Evidence persistence extension includes only:

- nullable occurrence scope to allow the mutually exclusive Transfer scope;
- scalar `transfer_plan_id` and `transfer_participant_id` on Evidence;
- database/application scope constraints;
- Transfer-specific typed review revisions;
- explicit reviewed group-membership rows;
- Transfer Evidence commit attempts;
- owner-side Transfer Evidence receipts keyed by stable idempotency.

There is no generic `evidence_targets` polymorphic table and no generic Transfer JSON bag of reviewed fields.

## Test contract

Completion requires automated proof of at least:

- five schema registry definitions and versions;
- classifier positive/negative/ambiguity/mismatch cases;
- every documented fixture corpus category for every schema;
- extractor allowlists and negative adjacent-number cases;
- no score-to-required-pass inference;
- no implicit `in_game_rules_verified` path;
- required/optional field behavior and typed normalization;
- low-confidence/manual correction while machine provenance remains immutable;
- review validity requirements for mutable schemas;
- target/window/participant scope drift rejection/re-review;
- exact duplicate tenant/destination isolation;
- visual duplicate warning behavior;
- semantic duplicate block/resolution and genuinely newer import;
- database invalid-scope constraints;
- five destination Actions and provenance validation;
- atomic score/pass rollback;
- append-only observation/condition/group history and correction semantics;
- stable owner idempotency and crash-after-owner-success recovery;
- deletion/redaction without owner cascade;
- preview owner evaluator reuse and no manufactured eligibility;
- route authorization/non-disclosure;
- bounded summary query budget;
- cross-context architecture tests proving no foreign Eloquent model boundary leak;
- responsive/accessibility/localization coverage;
- deterministic desktop/mobile visual regression of the Transfer Evidence participant journey;
- PHP formatting/static analysis/tests, frontend checks, architecture/intelligence checks, dependency/security analysis, container/staging/recovery and other applicable repository release gates.

## Documentation reconciliation

Before completion, reconcile this contract with:

- `docs/product/screenshot-intake.md`;
- `docs/product/kingdom-transfer-planning.md`;
- product catalogue/gap/delivery-ledger references;
- Evidence and KingdomTransfers architecture docs;
- reference docs for routes/schema/receipts;
- operations docs for retry/failure/deletion/retention/diagnostics.

## Delivery ledger

| ID | Deliverable | Status |
| --- | --- | --- |
| TE-1 | Product contract and cross-doc product reconciliation | Complete |
| TE-2 | Five explicit versioned schema registry/classifier/extractor implementations | Complete |
| TE-3 | Narrow Transfer participant Evidence persistence + DB scope enforcement | Complete |
| TE-4 | Human review, correction, source-time/freshness and scope-drift protections | Complete |
| TE-5 | Exact/visual/semantic duplicate behavior and supported resolution | Complete |
| TE-6 | Five KingdomTransfers destination Actions + shared owner writers | Complete |
| TE-7 | Atomic score/pass commit, stable idempotency and crash recovery | Complete |
| TE-8 | Owner-evaluated destination/eligibility preview with no manufactured facts | Complete |
| TE-9 | Transfer participant routes/API and responsive accessible localized UX | Complete |
| TE-10 | Retry/redaction/deletion/retention/observability/query-budget behavior | Complete |
| TE-11 | Complete executable fixture corpora and unit/feature/architecture coverage | Complete |
| TE-12 | Deterministic desktop/mobile visual regression | Complete |
| TE-13 | Architecture/reference/operations reconciliation and repository-wide release gates | Complete |

No ledger item may be marked Complete while any acceptance criterion, fixture family, schema field rule, destination Action, UX state, security requirement, test, documentation update or applicable gate remains partial or unverified.

## Definition of done

Screenshot Intake: Transfer Evidence is complete only when all five schemas are implemented exactly as versioned here, all handoffs preserve Evidence/KingdomTransfers ownership, no unsupported fact can be extracted or committed, all initial Evidence requires human review, duplicate/idempotency/freshness semantics remain distinct and owner-correct, the participant journey is fully usable on desktop/mobile with accessibility/localization, all documentation is reconciled, every delivery-ledger item is Complete, and all applicable immutable-candidate release gates pass.
