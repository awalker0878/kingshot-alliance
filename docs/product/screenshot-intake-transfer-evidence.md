# Screenshot Intake: Transfer Evidence

Status: Implementation contract — 2026-08-26

Screenshot Intake: Transfer Evidence is the second supported `Intelligence/Evidence` family after Bear Hunt battle reports. It converts five narrow, fixture-proven KingShot Transfer screenshot classes into reviewed scalar commands for `GameWorld/KingdomTransfers` without transferring ownership of Transfer domain facts into Evidence.

This document is the implementation source of truth for the extension. A delivery item is complete only when its behavior, authorization, persistence, idempotency/concurrency where applicable, provenance, UX, accessibility, localization, observability, fixtures, automated tests, visual proof and current-truth documentation are complete.

## Product outcome

An authorized Transfer manager can start from a Transfer participant, choose **Add in-game evidence**, upload one supported Transfer screenshot, have the application independently verify its screenshot class, extract only fixture-proven fields, review/correct every candidate, understand source time/freshness/conflicts, preview the exact destination facts and eligibility impact, and commit the approved meaning exactly once.

The journey is:

`Transfer participant → Add in-game evidence → select/expect screenshot class → upload → classify → extract → review/correct → duplicate check → preview destination facts and eligibility impact → commit`

A screenshot never becomes domain truth merely because OCR or classification confidence is high. All v1 Transfer Evidence requires human review and explicit commit.

## Non-goals

This extension must not:

- create a `TransferOCR`, Transfer Evidence, or other new bounded context;
- create a generic `transfer_ocr` schema;
- create an unconstrained bag-of-fields extraction model;
- infer unsupported Transfer facts from nearby text or arbitrary OCR output;
- calculate `transfer_passes_required` from Transfer Score;
- automatically assert `in_game_rules_verified=true` from any initial Transfer screenshot class;
- persist a materialized `eligible` flag;
- let Evidence write Transfer observations, target conditions or official Transfer Groups directly;
- introduce a generic polymorphic Evidence target framework merely to support this extension;
- allow foreign Eloquent models to cross context boundaries.

## Capability ownership

### `Intelligence/Evidence` owns

- private uploaded image objects and immutable source metadata;
- upload security scanning and checksum/provenance records;
- source and derived-representation checksums;
- expected screenshot class and classification attempts;
- OCR/provider and extraction attempts, including implementation/model/ruleset/schema versions;
- raw extracted candidates, normalized candidates, confidence, bounding regions and warnings;
- immutable review revisions and manual corrections/exclusions;
- exact, visual and semantic duplicate decisions;
- commit attempts, stable destination idempotency keys and destination receipts;
- retry/recovery state;
- evidence redaction, deletion and retention lifecycle;
- scalar Transfer scope references needed to authorize and explain a handoff.

### `GameWorld/KingdomTransfers` owns

- Transfer Plans and participants;
- Transfer Windows and phase boundaries;
- official Transfer Groups and window-scoped Kingdom membership;
- target-Kingdom conditions and correction history;
- Governor transfer observations;
- observation validity and freshness semantics;
- conflict resolution/evaluation semantics;
- deterministic eligibility calculations;
- audit/outbox semantics for accepted Transfer mutations.

Evidence may retain `transfer_plan_id`, `transfer_participant_id` and the reviewed scope snapshot as scalar references. Those references do not transfer aggregate ownership. Every destination commit re-resolves current Alliance, Plan, participant, Transfer Window and target Kingdom state under `GameWorld/KingdomTransfers` authority.

## Evidence scope

The existing narrow Evidence model is generalized only enough to support explicit Transfer participant scope.

Valid source scopes are:

### Bear Hunt Evidence

- `occurrence_id` is present;
- `transfer_plan_id` is absent;
- `transfer_participant_id` is absent.

### Transfer participant Evidence

- `occurrence_id` is absent;
- `transfer_plan_id` is present;
- `transfer_participant_id` is present;
- the participant belongs to the Plan and Alliance at upload;
- the current Transfer Window and target Kingdom, when relevant, are captured as review/commit scope and revalidated later.

Invalid mixed or incomplete scope combinations fail closed at the application boundary and are protected by database constraints. Exact duplicate disclosure remains Alliance- and destination-scope-safe: duplicate lookup must never reveal Evidence from another Alliance, Plan, participant or unauthorized destination.

## Schema-wide rules

There is no generic Transfer extractor. Each supported screenshot class is a separate `EvidenceKind`, extractor, fixture corpus and schema descriptor. The schema version is a meaning contract, not merely a parser version.

The following are **review metadata/scope**, not generic OCR fields: `observed_at`, `valid_until`, Alliance ID, Plan ID, participant ID, Transfer Window ID, direction and owner target Kingdom ID. They are captured/reconciled by the review boundary and may not be emitted by an unrelated extractor merely because OCR found similar text.

For every v1 schema:

- minimum classification confidence is `0.55`;
- minimum supported field confidence is `0.55`;
- classification is independent of `expected_kind`; the selected class is a routing expectation only;
- every reviewed fact is explicitly confirmed by a human, even above threshold;
- below-threshold or ambiguous machine candidates require correction/confirmation and never auto-commit;
- a field absent from that schema's supported-field list cannot cross extraction, review or commit;
- machine output and confidence remain immutable provenance after correction;
- preview is advisory and reuses KingdomTransfers' eligibility evaluator; commit re-resolves owner truth again;
- preview never substitutes or synthesizes `in_game_rules_verified`.

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

No other Transfer fact is supported by this schema. In particular it must not extract Transfer Score, pass counts, invitation status, target Power Cap, Kingdom classification or `in_game_rules_verified`.

**Review metadata and requirements**

- `observed_at` — required reviewer-confirmed observation time unless a future schema version explicitly supports a fixture-proven visible in-game timestamp;
- `valid_until` — required for the owner observation used as current eligibility evidence. It is reviewer/KingdomTransfers policy input, not an Evidence-generated TTL;
- current participant/window scope is re-resolved at review and destination commit;
- the reviewer must explicitly confirm or correct `governor_power` regardless of machine confidence.

**Normalization**

- retain exact raw OCR text;
- remove only fixture-proven visual grouping separators;
- normalize only a fully displayed unambiguous integer;
- do not infer `K`, `M`, `B`, truncated or partially obscured values unless a future fixture/version proves a lossless rule;
- adjacent power caps or unrelated numbers are negative fixtures and must not be captured.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + governor_power + observed_at-boundary`

`valid_until` is not used to collapse two observations with different reviewed game meaning; destination validation still governs whether the observation is current.

**Commit preview**

- substitutes only Governor Power into the existing eligibility input;
- displays current owner Governor Power state/source/time versus reviewed Power;
- applies reviewed `observed_at`/`valid_until` to the hypothetical observation;
- leaves invitation, pass facts, target conditions, group facts and `in_game_rules_verified` unchanged;
- expired/missing validity cannot manufacture `met`.

### 2. Transfer Score & Passes — `transfer_score_passes`

**Schema version:** `transfer-score-passes/1`  
**Fixture corpus:** `transfer-score-passes-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferScorePassEvidence`

Purpose: atomically record the three explicitly displayed score/pass facts shown on one supported screen.

**Fixture-proven extracted fields**

- `transfer_score` — required, non-negative integer;
- `transfer_passes_available` — required, non-negative integer;
- `transfer_passes_required` — required, non-negative integer.

All three are required. A missing pass count is not completed from a formula, previous observation, target Power or any other fact. `transfer_passes_required` is observed game data and is never calculated from Transfer Score.

**Review metadata and requirements**

- `observed_at` — required;
- `valid_until` — required for current eligibility use and supplied under explicit KingdomTransfers/reviewer behavior, never by hidden Evidence TTL;
- current target Kingdom is required destination scope and is re-resolved/reconciled even though it is not a v1 extracted field;
- reviewer explicitly confirms/corrects all three numbers;
- one missing or invalid reviewed number blocks approval/commit.

**Normalization**

- each number uses only its own fixture-proven label/region;
- grouping separators may be removed only when lossless;
- abbreviated/partial values are unsupported unless fixture-proven by a new schema version;
- adjacent unrelated numbers must not fill any of the three fields.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + target_kingdom_id + transfer_score + transfer_passes_available + transfer_passes_required + observed_at-boundary`

**Commit preview**

- shows Transfer Score before/after for explanation;
- substitutes only passes available and passes required into eligibility inputs because those are the score/pass facts consumed by the current evaluator;
- uses reviewed time/validity for those hypothetical observations;
- leaves Governor Power, invitation, target/group facts and `in_game_rules_verified` unchanged;
- all three destination observations commit in one KingdomTransfers transaction or none survive.

### 3. Transfer Invitation — `transfer_invitation`

**Schema version:** `transfer-invitation/1`  
**Fixture corpus:** `transfer-invitation-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferInvitationEvidence`

Purpose: record the invitation state explicitly shown for the participant and current target.

**Fixture-proven extracted fields**

- `invitation_status` — required;
- `target_kingdom_number` — optional extraction candidate only when explicitly visible in supported fixtures.

The owner destination target Kingdom is mandatory scope. When `target_kingdom_number` is present, review must resolve it to the same current owner target. When it is not fixture-proven/present, Evidence uses the already-authorized participant target scope; it does not invent a number.

Allowed normalized invitation values are exactly:

- `none`;
- `ordinary_received`;
- `special_pending`;
- `special_approved`.

Unknown, novel or ambiguous wording remains unsupported/needs correction and is never coerced to the nearest enum.

**Review metadata and requirements**

- `observed_at` — required;
- `valid_until` — required for current eligibility use under KingdomTransfers/reviewer behavior;
- current target Kingdom and window are re-resolved at review and commit;
- reviewer explicitly confirms/corrects the invitation enum.

**Normalization**

- only fixture-proven invitation phrases map to the four owner enum values;
- target Kingdom normalization accepts only explicit supported `Kingdom #N`/equivalent fixture forms;
- unrelated Kingdom numbers are negative fixtures.

**Semantic fingerprint**

`schema_version + transfer_window_id + participant_id + target_kingdom_id + invitation_status + observed_at-boundary`

**Commit preview**

- substitutes only invitation status with reviewed time/validity;
- leaves passes, Governor Power, group/target conditions and `in_game_rules_verified` unchanged;
- an expired invitation remains stale/needs verification according to owner rules rather than becoming eligible.

### 4. Target Kingdom transfer rules — `transfer_target_kingdom_rules`

**Schema version:** `transfer-target-kingdom-rules/1`  
**Fixture corpus:** `transfer-target-kingdom-rules-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordTransferKingdomRulesEvidence`

Purpose: record fixture-proven target-Kingdom conditions for the current Transfer Window.

**Fixture-proven extracted fields**

- `target_kingdom_number` — required;
- `power_cap` — required, non-negative integer;
- `kingdom_classification` — optional, only `ordinary|leading|unknown` when the supported fixture explicitly displays the classification.

**Review metadata and requirements**

- `observed_at` — required;
- no Evidence-global `valid_until` is invented for this window-scoped condition schema;
- the reviewed Kingdom number must resolve to the participant's current owner target Kingdom;
- reviewer explicitly confirms/corrects Power Cap and any classification candidate;
- Phase-II-and-later sourced correction invariants remain entirely owner-enforced.

**Normalization**

- target number must come from a fixture-proven target-Kingdom label/region;
- Power Cap uses only its explicit fixture-proven label/region and lossless integer grouping normalization;
- classification maps only explicit supported phrases to owner enum values;
- nearby Governor Power or score values must not be captured as `power_cap`.

**Semantic fingerprint**

`schema_version + transfer_window_id + target_kingdom_id + power_cap + kingdom_classification + observed_at-boundary`

**Commit preview**

- substitutes only target Power Cap and target classification for the current window/target;
- does not reinterpret the screenshot as a timeless Kingdom property;
- leaves Governor/pass/invitation/group facts and `in_game_rules_verified` unchanged;
- owner correction/phase rules are rechecked at actual commit.

### 5. Official Transfer Group — `transfer_official_group`

**Schema version:** `transfer-official-group/1`  
**Fixture corpus:** `transfer-official-group-v1`  
**Classifier threshold:** `0.55`  
**Field threshold:** `0.55`  
**Destination Action:** `GameWorld/KingdomTransfers::RecordOfficialTransferGroupEvidence`

Purpose: record an official Transfer-Window-scoped group and its explicitly visible complete Kingdom membership.

**Fixture-proven extracted fields**

- `official_group_identifier` — required normalized string;
- `kingdom_number` — required repeatable field; reviewed result is a non-empty sorted unique list of explicitly visible Kingdom numbers.

**Review metadata and requirements**

- `observed_at` — required;
- no hidden global TTL is applied; group meaning is scoped to one Transfer Window and supersession/history is owner-controlled;
- v1 supports complete fixture-proven group membership only. A partial/off-screen list is unsupported and cannot be committed as complete membership;
- reviewer explicitly confirms/corrects group identifier and complete visible membership.

**Normalization**

- normalize group identifier only according to fixture-proven label extraction and whitespace trimming; do not infer aliases;
- normalize each Kingdom number only from explicit supported Kingdom-number forms;
- deduplicate and sort reviewed Kingdom numbers deterministically before fingerprint/commit;
- hidden/off-screen Kingdoms are never inferred.

**Semantic fingerprint**

`schema_version + transfer_window_id + official_group_identifier + sorted_unique_kingdom_numbers + observed_at-boundary`

**Commit preview**

- substitutes only the reviewed official group relationship relevant to the participant source/target comparison;
- if the reviewed complete group does not explicitly prove both relevant Kingdom memberships, preview remains unknown rather than inferring membership;
- leaves target conditions, participant observations and `in_game_rules_verified` unchanged.

## Schema registry contract

Every supported kind is registered through an explicit schema descriptor containing:

- Evidence kind;
- schema version;
- supported and required extraction fields;
- classifier acceptance threshold;
- field-confidence threshold;
- fixture corpus identifier/version;
- destination Action identifier.

Schema-specific normalizers, review validation, fingerprint construction and preview mapping remain explicit implementations selected by kind, not generic field dispatch. Adding a supported field or changing normalization/destination meaning requires a versioned contract change and fixture proof rather than silently rewriting v1.

## Fixture corpus contract

Each screenshot class owns a fixture corpus containing positive, negative and ambiguity cases. At minimum each corpus covers:

- canonical supported screenshot/OCR document;
- alternate supported resolution/aspect ratio representation;
- safe crop/scale variant where supported;
- common numeric grouping presentation;
- low-confidence/blurred text;
- adjacent unrelated numbers that must not be extracted;
- missing required field;
- unsupported UI variant;
- wrong screenshot class;
- recompressed/visually similar duplicate;
- semantically equal screenshot meaning;
- semantically newer/different meaning.

A field is not supported until deterministic fixture tests prove classification, extraction, normalization, review validation and semantic fingerprint behavior for it. Fixtures must be synthetic/redacted and must not contain real private user evidence.

## Classification contract

The participant selects the screenshot class they intend to add. It is stored as `expected_kind`; it is an expectation and routing hint, not truth.

Classification must independently identify the actual supported kind, preserve classifier/version/confidence provenance, reject a mismatch between expected and actual kind, never run an extractor merely because the user selected that class, route unsupported/ambiguous screenshots to explicit failure/review behavior and never reinterpret arbitrary OCR fields as another schema's supported values.

## Extraction, provenance and review

For every machine candidate Evidence retains schema kind/version, field key/ordinal, raw observed text, normalized candidate, data type, machine confidence, optional bounding region, warnings and extraction attempt identity/version. Human correction appends a review revision; it never rewrites machine output or machine confidence.

All v1 Transfer Evidence requires human review. Eligibility-critical values are present explicitly in the immutable approved review revision. Confidence is never promoted to `1.0` because a reviewer corrected a value.

## Observation time and freshness

Evidence distinguishes upload time, available trusted image metadata time, fixture-proven visible in-game timestamp when a schema supports one, reviewer-confirmed `observed_at`, and owner/reviewer `valid_until` where that schema requires it. Evidence does not invent a global freshness TTL.

`GameWorld/KingdomTransfers` remains authoritative for whether an observation is usable as current eligibility evidence. Governor Power, score/pass observations and invitation observations require explicit validity for current v1 commits. Window-scoped target conditions and official group observations use owner window/history/supersession semantics rather than an Evidence TTL. A stale screenshot may remain historical Evidence, but missing/stale/conflicting/non-authoritative facts must never manufacture a current `met` requirement.

## `in_game_rules_verified` boundary

None of the five v1 screenshot schemas may emit or commit `in_game_rules_verified=true`. Governor status, score/pass, invitation, target rules and official group screenshots prove only their explicitly supported facts; none proves that no unpublished game restriction exists. A future class may support that fact only through its own new explicit schema and fixture corpus.

## Review scope snapshot and stale-scope protection

An approved review records enough scalar scope meaning to detect a material destination change before commit, including applicable Alliance ID, Plan ID, participant ID, Transfer Window ID, participant direction, target Kingdom ID and schema kind/version.

Every protected mutation re-resolves active Player/Alliance authority and the relevant owner scope. Destination commit does so again inside the owner transaction. If the participant was withdrawn, moved to another Plan/window, changed target where target-specific evidence is involved, or otherwise no longer matches approved meaning, commit fails and requires re-review. It never silently retargets approved Evidence.

## Semantic duplicates versus destination idempotency

These are separate protections.

A **semantic fingerprint** describes stable reviewed game meaning for one schema and correct Transfer scope. Equivalent reviewed meaning is blocked from repeated commit unless the supported duplicate-resolution flow explicitly approves proceeding. A genuinely newer observation remains importable because its observation boundary and/or reviewed meaning differs.

A **destination idempotency key** identifies one immutable approved review meaning. If KingdomTransfers commits successfully and Evidence crashes before recording acknowledgement, retrying that approved review returns the existing owner receipt rather than appending duplicate observations, conditions or group state.

Exact SHA duplicate matching remains Alliance/Plan/participant/kind scoped. Visual duplicates are warnings requiring review; they are not destination idempotency and do not silently discard a newer observation.

## Destination Action contract

Evidence does not mutate KingdomTransfers tables. Each class has a dedicated owner Action:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Owner Actions use shared owner-internal authority/validation/writer behavior rather than duplicating authorization or allowing Evidence to orchestrate low-level owner writes.

Every destination Action must accept only scalar IDs/value objects, reacquire current Player/Alliance authority inside its transaction, re-resolve Plan/window/participant/target state, validate approved Evidence provenance, reject foreign/unapproved/deleted/scope-incompatible Evidence, validate schema-specific typed values/invariants, append history rather than silently overwrite truth, preserve owner correction invariants, enforce stable unique destination idempotency, record privacy-safe audit/outbox events and return a scalar receipt.

`transfer_score_passes` commits score, available passes and required passes atomically in one KingdomTransfers transaction. Target rules go through owner condition/correction semantics. Official Transfer Group goes through owner group/membership semantics and owner supersession/conflict rules.

## Commit handshake and recovery

The cross-context sequence is:

`Evidence BeginCommitAttempt → Evidence CommitReviewedTransferEvidence → KingdomTransfers schema-specific owner Action → Evidence MarkCommitSucceeded`

The approved review determines a stable destination idempotency key. Failed acknowledgement remains historical Evidence commit-attempt state. A retry can recover an already accepted owner receipt without a duplicate owner mutation. Evidence stores only the scalar destination receipt and provenance needed to explain the handoff.

Deletion of Evidence never cascades into accepted KingdomTransfers facts. Correcting/removing accepted Transfer facts requires explicit audited KingdomTransfers owner behavior.

## Commit preview

Before commit, review shows current owner value/state, reviewed value, source/observation time, validity/freshness consequence, current/stale/conflicting authoritative facts, deterministic eligibility before/after and the highest-priority remaining requirement. Preview uses the same owner eligibility evaluator as current truth and is advisory; commit revalidates current owner state.

Importing score/pass evidence, for example, may resolve missing pass requirements but still leave **Needs verification — verify additional in-game rules** because no v1 schema supplies `in_game_rules_verified`.

## Participant UX

The primary entry point is the Transfer participant/readiness surface, not a generic Evidence administration page. **Add in-game evidence** presents:

- Governor status;
- Transfer Score & Passes;
- Invitation;
- Target Kingdom rules;
- Official Transfer Group.

Required UX states include no active Plan/window, participant withdrawn, missing target for target-specific evidence, locked/closed/read-only Plan, unauthorized mutation, upload progress/failure/retry, exact duplicate reuse, classifying, expected/actual mismatch, unsupported/ambiguous screenshot, extracting/failure/retry, required field missing, low confidence, manual correction, source/observation-time confirmation, missing/invalid validity for mutable observations, current/stale/conflicting fact disclosure, visual duplicate warning, semantic duplicate block/resolution, preview ready, scope-changed/re-review required, committing, committed, recovered already-committed receipt, destination validation failure and deletion/redaction confirmation.

The review surface remains usable without image-only interaction. Confidence/warnings cannot rely on color alone. Screenshot preview has accessible alternative context. Keyboard and screen-reader users can inspect/correct every field and understand source/freshness/conflict state. Mobile users can access screenshot class, participant, target, primary values, warnings and commit action without a wide-table-only interaction.

## Authorization and privacy

- Transfer manage permission is required for upload/review/commit unless an existing Evidence policy is equally restrictive;
- every protected write re-resolves active Player + Alliance authority and applicable Transfer scope;
- cross-Alliance/Plan/participant references fail without disclosure;
- raw screenshots/OCR text never appear in logs, telemetry or audit metadata;
- Governor Power, score/pass values, Player names and reusable evidence hashes are excluded from diagnostic dimensions;
- exact/visual/semantic duplicate checks never disclose another tenant or unauthorized scope.

## Audit, outbox and observability

Material lifecycle events cover upload accepted/rejected, classification attempted/mismatch/unsupported, extraction attempted/failed, review approved/corrected, duplicate detection/resolution, preview, commit started/succeeded/failed/recovered, destination idempotency reuse, stale-scope rejection and Evidence deletion/redaction/retention.

Metrics may report counts/rates/latency by schema/version and lifecycle state, including classification mismatch, extraction failure, correction, low confidence, duplicate, review abandonment, commit failure/recovery and stale-scope rejection. They must not contain raw screenshot/OCR content, Player identity, Alliance/Kingdom names, power/score/pass values or reusable hashes.

## Deletion and retention

Existing Screenshot Intake retention semantics apply. Deleting Evidence does not delete accepted owner observations/conditions/groups. Committed Evidence may have binary/raw sensitive material redacted after the configured retention window while retaining the minimum provenance tombstone, approved review meaning and destination receipt needed to explain the handoff. Expired uncommitted Evidence is redacted/purged under existing policy. Retention scans remain bounded and cannot be starved by long-lived tombstones.

## Localization

All new player-facing strings are localized. Transfer-participant entry labels belong to the transfer localization domain; reusable Evidence lifecycle/review language remains in the Screenshot Intake localization domain. No supported locale may expose raw localization keys. Dates, times, integers and Kingdom identifiers use existing locale-formatting utilities.

## Test contract

Completion requires automated proof for at least:

- all five schema classes/versions and only their supported fields;
- wrong expected kind versus independently classified kind;
- unsupported/ambiguous classification;
- per-schema canonical/alternate/crop/grouping/low-confidence/adjacent-number/missing-field/unsupported/wrong-class/visual-duplicate/semantic-equal/semantic-newer fixtures;
- fixture-proven normalization and negative adjacent-number behavior;
- required-field and confidence enforcement;
- human correction preserving machine provenance/confidence;
- no automatic commit;
- no v1 path emitting `in_game_rules_verified=true`;
- no Transfer Score → required-pass calculation;
- valid Evidence scope combinations and invalid mixed scope rejection;
- cross-Alliance/Plan/participant authorization and non-disclosure;
- foreign/unapproved/deleted Evidence rejection at destination;
- participant withdrawn and Plan/window/target drift between upload/review/commit;
- Governor Power append/idempotency;
- atomic score/pass three-observation commit and rollback on one invalid field;
- invitation enum validation/target scoping/validity;
- target Power Cap owner correction/phase invariants;
- official Transfer Group window scope/complete membership/deterministic ordering;
- exact duplicate tenant/scope safety;
- visual duplicate warning behavior;
- per-schema semantic duplicate block/resolution;
- genuinely newer observation remaining importable;
- stable destination idempotency under retry;
- crash after owner commit/before Evidence acknowledgement recovering the same receipt;
- Evidence deletion not cascading into committed owner facts;
- missing/stale/conflicting screenshot facts producing existing requirement states;
- eligibility reevaluation after successful owner commit;
- commit preview before/after and remaining-action disclosure;
- privacy-safe audit/outbox/diagnostics;
- retention behavior;
- responsive desktop/mobile review;
- keyboard/screen-reader semantics and non-color-only confidence/warnings;
- localization/no raw keys;
- architecture boundaries proving no Evidence persistence write into KingdomTransfers and no foreign Eloquent contracts;
- bounded/no-N+1 participant Evidence summaries;
- deterministic visual regression for upload/review/preview/receipt states.

## Delivery ledger

A phase is `Complete` only when all applicable behavior, persistence, authorization, UX, accessibility, localization, observability, tests and current-truth docs are complete and repository gates pass on one immutable candidate.

| Phase | Status | Exit condition |
| --- | --- | --- |
| TE-1 | In progress | Product contract is current and ownership/non-goals/schema boundaries are explicit. |
| TE-2 | Planned | Evidence persistence supports explicit Transfer participant scope with valid-scope constraints and tenant-safe duplicate scope. |
| TE-3 | Planned | Five explicit Evidence kinds/schema v1 descriptors/classifier contracts are implemented. |
| TE-4 | Planned | Five fixture corpora and schema-specific extraction/normalization/confidence rules are implemented and tested. |
| TE-5 | Planned | Transfer review revisions, source-time/validity confirmation, scope snapshot and semantic fingerprints are implemented. |
| TE-6 | Planned | Five dedicated KingdomTransfers destination Actions and shared owner-internal writers provide atomic/idempotent owner mutation. |
| TE-7 | Planned | Commit orchestration, stale-scope rejection, crash recovery and scalar receipts are implemented. |
| TE-8 | Planned | Transfer-aware duplicate behavior and before/after eligibility preview are implemented. |
| TE-9 | Planned | Participant **Add in-game evidence** upload/review/preview/receipt UX is complete, responsive and accessible. |
| TE-10 | Planned | Audit/outbox/diagnostics, privacy constraints, deletion/retention and operational support are complete. |
| TE-11 | Planned | Localization, unit/feature/architecture/query-budget/accessibility/visual regression coverage is complete. |
| TE-12 | Planned | `/docs/product`, `/docs/architecture`, `/docs/reference`, `/docs/operations`, catalogue/gap/delivery-ledger current truth is reconciled to implementation. |
| TE-13 | Planned | Repository-wide spec→code, code→spec and UX→backend audit finds no remaining Transfer Evidence gap and all applicable release gates pass on one immutable candidate. |

## Cross-phase invariants

1. Evidence owns evidence/provenance; KingdomTransfers owns Transfer facts and eligibility.
2. There is no generic Transfer OCR/bag-of-fields schema.
3. Every supported field is schema- and fixture-proven.
4. Every v1 screenshot requires human review.
5. No v1 Transfer screenshot asserts `in_game_rules_verified=true`.
6. Required Transfer Passes are observed, never calculated from Transfer Score.
7. Public cross-context contracts use scalar IDs/value objects only.
8. Destination owner Actions reacquire authority and current scope inside their transaction.
9. Multi-field screenshot meaning is atomic at the destination boundary.
10. Semantic duplicate meaning and destination idempotency remain separate protections.
11. Machine output/confidence remains immutable historical provenance after human correction.
12. Evidence does not own or invent freshness; KingdomTransfers remains authoritative.
13. Deleting Evidence never silently rewrites accepted Transfer history.
14. No compatibility shim, dual schema, dual read/write or undocumented legacy path is introduced.

## Definition of done

Transfer Evidence is complete only when all five v1 classes are explicitly implemented and fixture-proven; unsupported fields cannot cross from OCR into approved/committed meaning; classification verifies expected class; human review/provenance is preserved; stale/missing/conflicting facts remain honest; scope drift cannot silently retarget approved meaning; score/pass is atomic; all destination mutations enter KingdomTransfers through owner Actions; crash/replay cannot duplicate facts; semantic duplicates remain distinct from idempotency; no v1 path manufactures in-game verification or a pass formula; Evidence deletion/retention does not cascade owner truth; participant UX is responsive, accessible, localized and visually tested; lifecycle telemetry is privacy-safe; relevant PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging, clean-database install and backup/restore checks pass on one immutable candidate; and all current-truth docs/ledgers contain no planned, partial or placeholder Transfer Evidence item.