# Screenshot Intake: Transfer Evidence

Status: Implementation contract — 2026-08-25

Screenshot Intake: Transfer Evidence is the second supported `Intelligence/Evidence` family after Bear Hunt battle reports. It converts narrow, fixture-proven KingShot Transfer screenshots into reviewed scalar commands for `GameWorld/KingdomTransfers` without transferring ownership of Transfer domain facts into Evidence.

This document is the implementation source of truth for the extension. A delivery item is complete only when its behavior, authorization, persistence, idempotency/concurrency where applicable, provenance, UX, accessibility, localization, observability, fixtures, automated tests, visual proof and current-truth documentation are complete.

## Product outcome

An authorized Transfer manager can start from a Transfer participant, choose **Add in-game evidence**, upload one supported Transfer screenshot, have the application verify its screenshot class, extract only fixture-proven fields, review/correct every candidate, understand source time/freshness/conflicts, preview the exact destination facts and eligibility impact, and commit the approved meaning exactly once.

The resulting journey is:

`Transfer participant → Add in-game evidence → select/expect screenshot class → upload → classify → extract → review/correct → duplicate check → preview destination facts and eligibility impact → commit`

A screenshot never becomes domain truth merely because OCR or classification confidence is high. Initial Transfer Evidence always requires human review and explicit commit.

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

Invalid mixed or incomplete scope combinations fail closed at the application boundary and are protected by database constraints where supported by the repository migration conventions.

Exact duplicate disclosure remains Alliance- and destination-scope-safe. A duplicate lookup must never reveal Evidence from another Alliance, Plan, participant or unauthorized destination.

## Supported screenshot schemas

There is no generic Transfer extractor. Each screenshot kind is a separate, explicit Evidence kind with an independently versioned extraction contract.

Initial schema version for every class is `v1`.

### 1. `transfer_governor_status` v1

Purpose: record the Governor's currently displayed Power when the screenshot class proves that value.

Supported reviewed fields:

- `governor_power` — required, non-negative integer;
- `observed_at` — reviewer-confirmed observation time unless a fixture-proven visible in-game timestamp exists.

Not supported merely because text appears nearby:

- Transfer Score;
- Transfer Pass requirements;
- invitation status;
- `in_game_rules_verified`;
- target Power Cap.

Normalization:

- retain the exact raw OCR text;
- remove fixture-proven visual grouping separators only;
- normalize to an integer only when the full displayed value is unambiguous;
- abbreviated/partially obscured values are not promoted to precise integers unless a fixture explicitly defines a lossless normalization.

Semantic fingerprint meaning:

`schema_version + transfer_window_id + participant_id + governor_power + observed_at-boundary`

Destination:

`GameWorld/KingdomTransfers::RecordGovernorStatusEvidence`

### 2. `transfer_score_passes` v1

Purpose: atomically record the fixture-proven Transfer Score/pass facts shown on the same screen.

Supported reviewed fields:

- `transfer_score` — required, non-negative integer;
- `transfer_passes_available` — required, non-negative integer;
- `transfer_passes_required` — required, non-negative integer;
- `observed_at` — reviewer-confirmed or fixture-proven visible source time;
- `valid_until` — required for current eligibility use and governed by KingdomTransfers product rules/reviewer confirmation, not hidden Evidence TTL logic.

Rules:

- all three numeric facts commit atomically or none commit;
- required passes are observed from the game and are never calculated from Transfer Score;
- extraction may not fill a missing pass count from a formula or previous observation.

Semantic fingerprint meaning:

`schema_version + transfer_window_id + participant_id + target_kingdom_id + transfer_score + passes_available + passes_required + observed_at-boundary`

Destination:

`GameWorld/KingdomTransfers::RecordTransferScorePassEvidence`

### 3. `transfer_invitation` v1

Purpose: record the invitation state explicitly shown for the participant/target.

Supported reviewed fields:

- `invitation_status` — required enum value;
- `target_kingdom_id` — required destination scope, extracted only when fixture-proven and otherwise supplied by/reconciled against participant scope;
- `observed_at`;
- `valid_until` for current eligibility use.

Allowed normalized invitation values are only the owner enum values:

- `none`;
- `ordinary_received`;
- `special_pending`;
- `special_approved`.

Unknown, novel or ambiguous wording remains unsupported/needs correction and is never coerced to the nearest enum.

Semantic fingerprint meaning:

`schema_version + transfer_window_id + participant_id + target_kingdom_id + invitation_status + observed_at-boundary`

Destination:

`GameWorld/KingdomTransfers::RecordTransferInvitationEvidence`

### 4. `transfer_target_kingdom_rules` v1

Purpose: record fixture-proven target-Kingdom conditions for the current Transfer Window.

Supported reviewed fields:

- `target_kingdom_id` — required;
- `power_cap` — required non-negative integer when the supported fixture class displays it;
- `kingdom_classification` — optional enum `ordinary|leading|unknown` only when explicitly visible and fixture-proven;
- `observed_at` — required.

Rules:

- the screenshot does not create a timeless Kingdom property;
- the condition is always scoped to one Transfer Window + target Kingdom;
- Phase-II-and-later Power Cap correction invariants remain owned and enforced by KingdomTransfers;
- a screenshot cannot bypass the existing sourced-correction rule.

Semantic fingerprint meaning:

`schema_version + transfer_window_id + target_kingdom_id + power_cap + kingdom_classification + observed_at-boundary`

Destination:

`GameWorld/KingdomTransfers::RecordTransferKingdomRulesEvidence`

### 5. `transfer_official_group` v1

Purpose: record an official, Transfer-Window-scoped Transfer Group and its explicitly visible Kingdom membership.

Supported reviewed fields:

- `official_group_identifier` — required normalized string;
- `kingdom_ids` — required non-empty sorted unique list of explicitly visible Kingdom IDs;
- `observed_at` — required.

Rules:

- membership is scoped to one Transfer Window;
- hidden/off-screen Kingdoms must not be inferred;
- a partial screenshot must be represented as partial/unsupported for a complete membership commit unless the schema/fixture explicitly defines a supported partial update action;
- v1 supports complete fixture-proven membership commits only.

Semantic fingerprint meaning:

`schema_version + transfer_window_id + official_group_identifier + sorted_unique_kingdom_ids + observed_at-boundary`

Destination:

`GameWorld/KingdomTransfers::RecordOfficialTransferGroupEvidence`

## Schema registry contract

Every supported kind is registered through an explicit schema descriptor containing:

- Evidence kind;
- schema version;
- supported field definitions;
- required/optional field set;
- normalizer implementation identifier/version;
- classifier acceptance threshold;
- field confidence thresholds;
- fixture corpus identifier/version;
- semantic fingerprint builder;
- review validator;
- commit-preview builder;
- destination Action identifier.

Adding a field to a schema changes its explicit schema contract. Meaning-changing normalization or destination semantics require a new schema version rather than silently rewriting v1.

## Fixture corpus contract

Each screenshot class owns a fixture corpus containing positive, negative and ambiguity cases. At minimum each corpus covers:

- canonical supported screenshot(s);
- alternate supported resolution/aspect ratio;
- safe crop/scale variants where supported;
- common numeric grouping presentation;
- low-confidence/blurred text;
- adjacent unrelated numbers that must not be extracted;
- missing required field;
- unsupported UI variant;
- wrong screenshot class;
- recompressed/visually similar duplicate;
- semantically equal screenshot meaning;
- semantically newer/different meaning.

A field is not considered supported until fixture tests prove classification, extraction, normalization, review validation and semantic fingerprint behavior for it.

Fixture files must not contain real private user evidence. Synthetic/redacted deterministic fixtures are used for automated and visual regression tests.

## Classification contract

The participant journey asks the user which screenshot class they intend to add. That value is stored as `expected_kind`; it is a routing hint, not truth.

Classification must:

- independently identify the actual supported kind;
- record immutable classifier/version/confidence provenance;
- reject/flag a mismatch between expected and classified kind;
- never run an extractor merely because the user selected that class;
- route unsupported/ambiguous screenshots to explicit unsupported/needs-review behavior;
- never reinterpret arbitrary OCR fields as another schema's supported values.

## Extraction and normalization

Extraction is schema-specific. OCR/provider output is evidence input, not a generic Transfer record.

For every extracted field Evidence retains:

- schema kind/version;
- field key and ordinal where applicable;
- raw observed text;
- normalized candidate value;
- data type;
- machine confidence;
- optional bounding region;
- normalization warnings;
- extraction attempt identity/version.

Human correction appends review meaning and does not rewrite machine output or machine confidence.

## Confidence and review

All v1 Transfer Evidence requires human review.

Each schema defines:

- minimum classification confidence for supported routing;
- per-field confidence bands;
- fields that require explicit confirmation regardless of confidence;
- conditions that require correction or prevent approval.

Eligibility-critical values are always present in the immutable approved review revision, even when machine confidence is high.

Confidence is never promoted to `1.0` because a reviewer corrected a value.

## Observation time and freshness

Evidence distinguishes:

- upload time;
- available trusted image metadata time;
- fixture-proven visible in-game timestamp, if present;
- reviewer-confirmed `observed_at`;
- destination `valid_until`.

Evidence does not invent a global freshness TTL.

`GameWorld/KingdomTransfers` remains authoritative for whether an observation is usable as current eligibility evidence. Mutable screenshot-derived Governor facts must receive validity under explicit owner rules/reviewer-confirmed behavior. Missing validity remains historical-only where the current KingdomTransfers contract requires it.

A stale screenshot may still be retained as historical Evidence and may be committed when the owner Action permits historical observations, but it must not manufacture a current `met` eligibility requirement.

## `in_game_rules_verified` boundary

None of the five v1 screenshot schemas may emit `in_game_rules_verified=true`.

Governor status, score/pass, invitation, target rules and official group screenshots each prove only their explicitly supported facts. They do not prove that no unpublished game restriction exists.

A future screenshot class may support `in_game_rules_verified` only if its own explicit schema and fixture corpus prove a complete in-game eligibility result. That is outside this delivery contract.

## Review scope snapshot and stale-scope protection

When a review is approved, Evidence records enough scalar scope meaning to detect a material destination change before commit, including as applicable:

- Alliance ID;
- Transfer Plan ID;
- participant ID;
- Transfer Window ID;
- participant direction;
- target Kingdom ID;
- schema kind/version.

The destination Action re-resolves the current owner state. If the participant was withdrawn, moved to another Plan/window, changed target Kingdom where the evidence is target-specific, or otherwise no longer matches the approved scope, commit fails with a re-review-required result. It does not silently retarget the evidence.

## Semantic duplicates versus destination idempotency

These are separate protections.

### Semantic duplicate

A deterministic fingerprint describes stable reviewed game meaning for one schema and correct Transfer scope. A collision prevents accidental recommit of equivalent game state through another screenshot unless the documented review resolution explicitly permits it.

A genuinely newer observation remains importable because its observation boundary and/or reviewed meaning differs.

### Destination idempotency

A stable destination idempotency key identifies one immutable approved review meaning. It protects retries and crash recovery.

If KingdomTransfers commits successfully and Evidence fails before recording the receipt, retrying the same approved revision causes the owner Action to return the existing receipt rather than append duplicate observations/conditions/group membership.

## Destination Action contract

Evidence never loops over `RecordTransferObservation` from outside the owner context as its commit protocol. Each screenshot class has a dedicated owner Action:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Owner Actions may reuse shared owner-internal writers so authorization, validation, fingerprints, audit/outbox and locking are not duplicated.

Every destination Action must:

- accept scalar IDs/value objects only;
- reacquire current Player/Alliance authority inside the destination transaction;
- re-resolve Plan/window/participant/target scope;
- validate Evidence ID/review provenance without transferring Evidence ownership;
- reject foreign/unapproved/deleted or scope-incompatible Evidence;
- validate schema-specific typed values;
- serialize related multi-field changes atomically;
- append observations/corrections/history rather than silently overwriting truth;
- preserve owner correction invariants;
- enforce a unique stable destination idempotency key at the database/application boundary;
- record privacy-safe audit/outbox events;
- return a scalar receipt describing accepted destination identities/counts and resulting eligibility summary data needed for the commit receipt.

## Atomic destination semantics

`transfer_score_passes` commits score, available passes and required passes in one KingdomTransfers transaction. No partial subset may survive destination validation failure.

Target rules commit through the owner condition/correction path under the existing phase/correction invariants.

Official Transfer Group evidence commits through the owner group/membership path under one window lock. v1 complete membership is replaced/recorded only according to the documented owner correction semantics; Evidence does not mutate membership rows itself.

## Commit handshake and recovery

The cross-context sequence is:

`Evidence BeginCommitAttempt → Evidence CommitReviewedTransferEvidence → KingdomTransfers schema-specific owner Action → Evidence MarkCommitSucceeded`

The approved review revision determines a stable destination idempotency key. Failed acknowledgement remains historical Evidence commit-attempt state. A later retry can recover the already accepted owner receipt.

Deletion of Evidence does not cascade into accepted KingdomTransfers facts. Correcting/removing accepted Transfer facts requires explicit audited KingdomTransfers owner behavior.

## Commit preview

Before commit, the participant review shows concrete destination impact, not merely an extracted-field count.

Where applicable it displays:

- current owner value/state;
- reviewed evidence value;
- source/observation time;
- freshness/validity consequence;
- whether an existing fresh authoritative fact conflicts;
- the deterministic eligibility outcome before commit;
- the predicted eligibility outcome after applying the reviewed facts;
- the highest-priority remaining requirement after commit.

Example: importing valid score/pass observations may change **Needs verification** because pass counts were missing into a state that still says **Needs verification — verify additional in-game rules**. A screenshot never implies full eligibility beyond the facts it proves.

Preview is advisory. Destination commit revalidates owner state and may reject stale preview scope.

## Participant UX

The primary entry point is the Transfer participant/readiness surface, not a generic AI or Evidence administration page.

The **Add in-game evidence** flow presents the five supported classes in Transfer language:

- Governor status;
- Transfer Score & Passes;
- Invitation;
- Target Kingdom rules;
- Official Transfer Group.

Required UX states include:

- no active Transfer Plan/window;
- participant withdrawn;
- missing target Kingdom for target-specific evidence;
- locked/closed/read-only Plan;
- unauthorized mutation;
- upload progress/failure/retry;
- exact duplicate reuse;
- classifying;
- expected/actual class mismatch;
- unsupported/ambiguous screenshot;
- extracting/failure/retry;
- required field missing;
- low confidence;
- manual correction;
- source/observation-time confirmation;
- missing/invalid validity for current Governor observations;
- existing current/stale/conflicting fact disclosure;
- visual duplicate warning;
- semantic duplicate block/resolution;
- preview ready;
- destination scope changed/re-review required;
- committing;
- committed;
- recovered already-committed receipt;
- destination validation failure;
- deletion/redaction confirmation.

The review surface remains usable without image-only interaction, confidence never relies on color alone, and screenshot preview has accessible alternative context. Keyboard and screen-reader users can inspect/correct every field and understand source/freshness/conflict state.

On mobile, screenshot class, participant, target, primary reviewed values, warning state and commit action remain usable without a wide table.

## Authorization and privacy

- Transfer view permission may expose authorized Evidence status/committed provenance according to existing participant visibility rules.
- Transfer manage permission is required to upload/review/commit participant Transfer Evidence unless the existing Evidence policy defines an equally restrictive owner-authorized role.
- Every protected write re-resolves active Player + Alliance authority.
- Evidence scope IDs from another Alliance/Plan/participant return non-disclosing authorization/not-found behavior.
- raw screenshot/OCR text never appears in logs, telemetry or audit metadata;
- transfer values such as Governor Power, Transfer Score/pass counts and Player names are excluded from diagnostic dimensions;
- exact/visual/semantic duplicate checks never disclose cross-Alliance or unauthorized-scope Evidence.

## Audit, outbox and observability

Material lifecycle events include privacy-safe metadata for:

- Transfer Evidence upload accepted/rejected;
- classification attempted/mismatch/unsupported;
- extraction attempted/failed;
- review approved/corrected;
- exact/visual/semantic duplicate detected;
- commit preview generated;
- commit started/succeeded/failed/recovered;
- destination idempotency reuse;
- destination stale-scope rejection;
- Evidence deletion/redaction/retention.

Metrics may report counts/rates/latency by schema/version and state, including classification mismatch rate, extraction failure rate, field correction rate, low-confidence rate, duplicate rate, review abandonment, commit failure/recovery and stale-scope rejection. They must not expose raw screenshot content, OCR text, Player identity, Kingdom/Alliance names, Power/Score/pass values or reusable evidence hashes.

## Deletion and retention

Existing Screenshot Intake retention semantics apply.

- deleting Evidence does not delete accepted Transfer observations/conditions/groups;
- committed Transfer Evidence may have its binary and sensitive raw extraction redacted after the configured window while retaining the minimum provenance tombstone/review meaning/destination receipt required to explain the owner handoff;
- expired uncommitted Evidence is redacted and physically purged under existing Evidence policy;
- retention scans remain bounded and cannot be starved by long-lived committed tombstones.

## Localization

All new player-facing strings are localized. Transfer-participant entry labels belong to the transfer localization domain; reusable Evidence lifecycle/review language remains in the Screenshot Intake localization domain. No supported locale may expose raw localization keys. Dates, times, integers and Kingdom identifiers use existing locale-formatting utilities.

## Test contract

Completion requires automated proof for at least:

- each supported schema class/version and only its supported fields;
- wrong expected kind versus independently classified kind;
- unsupported/ambiguous classification;
- fixture-proven normalization and negative adjacent-number cases;
- missing required fields;
- low-confidence review requirements;
- human correction preserving machine provenance/confidence;
- no automatic commit;
- no v1 path emitting `in_game_rules_verified=true`;
- no Transfer Score → required-pass calculation;
- valid Evidence scope combinations and invalid mixed scope rejection;
- cross-Alliance/Plan/participant authorization and non-disclosure;
- foreign/unapproved/deleted Evidence rejection at destination;
- participant withdrawn between upload/review/commit;
- target Kingdom/window changed after approval causing re-review-required failure;
- Governor Power append/idempotency;
- atomic score/pass three-observation commit and rollback on one invalid field;
- invitation enum validation and target scoping;
- target Power Cap owner correction/phase invariants;
- official Transfer Group window scoping and deterministic membership ordering;
- exact duplicate tenant/scope safety;
- visual duplicate warning behavior;
- per-schema semantic duplicate block;
- genuinely newer observation remaining importable;
- stable destination idempotency under retry;
- crash after destination commit/before Evidence acknowledgement recovering the same receipt;
- Evidence deletion not cascading into committed Transfer facts;
- missing/stale/conflicting screenshot-derived observations producing the existing KingdomTransfers requirement states;
- eligibility reevaluation immediately reflecting a successful owner commit;
- commit preview before/after behavior and remaining-action disclosure;
- privacy-safe audit/outbox/diagnostics;
- retention behavior;
- responsive desktop/mobile review;
- keyboard/screen-reader semantics and non-color-only confidence/warnings;
- localization coverage/no raw keys;
- architecture boundary tests proving no Evidence persistence write into KingdomTransfers tables and no foreign Eloquent model contracts;
- query budget/no-N+1 behavior for participant Evidence summaries;
- deterministic visual regression for the participant upload/review/preview/receipt states.

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

Transfer Evidence is complete only when:

- all five v1 screenshot classes are explicitly implemented and fixture-proven;
- unsupported fields cannot cross from OCR into approved/committed meaning;
- classification verifies the selected expected class;
- review preserves raw/machine provenance and requires explicit human approval;
- stale/missing/conflicting facts remain honest in eligibility;
- participant/window/target scope drift prevents stale approved meaning from being silently committed;
- score/pass facts commit atomically;
- all destination mutations enter KingdomTransfers through owner Actions;
- replay/crash recovery cannot append duplicate facts;
- same-meaning screenshots are semantically duplicate while genuinely newer observations remain importable;
- no v1 path manufactures in-game verification or a pass formula;
- Evidence deletion/retention preserves required committed provenance without cascading owner deletion;
- the participant workflow is responsive, accessible, localized and visually regression-tested;
- privacy-safe audit/outbox/metrics cover the full lifecycle;
- relevant PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging, clean-database install and backup/restore checks pass on one immutable implementation candidate;
- all current-truth documentation and delivery ledgers match the implementation with no planned/partial/placeholder item remaining.
