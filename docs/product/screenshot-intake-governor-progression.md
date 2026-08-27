# Screenshot Intake: Governor Progression

Status: Active delivery — implementation present; final immutable-candidate verification pending

This document is the single implementation source of truth for Screenshot Intake: Governor Progression. The capability is not complete until every requirement and delivery-ledger item below agrees with implementation, tests, UX, architecture/reference/operations documentation and all applicable repository gates on one immutable candidate SHA.

## Product outcome

Governor Progression Screenshot Intake turns narrow, fixture-proven KingShot Governor progression screenshots into reviewed, append-only `Intelligence/Roster` observations while preserving three separate truths:

1. `GameWorld/Progression` owns immutable, versioned factual catalogue truth.
2. `Intelligence/Evidence` owns screenshots, machine attempts, normalized candidates, review revisions, duplicate decisions and commit provenance.
3. `Intelligence/Roster` owns accepted, dated Governor progression observations and the current-state/history projection derived from append-only history.

Screenshot Intake must never create, modify, correct, infer or publish canonical `GameWorld/Progression` facts.

## Non-negotiable architecture rules

1. Governor Progression is an explicit Screenshot Intake family inside `Intelligence/Evidence`; there is no Governor Progression OCR bounded context.
2. Do not introduce a generic OCR ingestion framework, generic `target_type`/`target_id` Evidence target, or unconstrained bag-of-fields observation model.
3. `GameWorld/Progression` is read-only to this capability and is used only for normalization/validation against a pinned immutable dataset release.
4. `Intelligence/Roster` is the reviewed destination owner. Evidence never writes Roster persistence directly.
5. Cross-context writes carry scalar IDs/value objects only; foreign Eloquent models never cross the boundary.
6. All v1 screenshot classes require human review. Automatic commit is outside this contract.
7. Missing or unshown fields mean unknown/not observed; they never become zero or absence.
8. Partial screenshots never imply complete roster state. Completeness is an explicit reviewed fact supported only by Hero Roster.
9. A newer Progression dataset never rewrites or silently re-normalizes historical Evidence attempts, review revisions or Roster observations.
10. Evidence deletion/redaction never cascades into accepted Roster history.
11. Shared Evidence reference contracts remain family-neutral; Governor-specific provenance validation uses a dedicated Evidence-owned contract.

## Capability ownership

### `Intelligence/Evidence`

Owns private screenshot binaries, source metadata/checksum, upload validation, expected class, independent classification, OCR/provider attempts, raw OCR, schema/extractor/normalizer versions, extracted candidates/bounds/confidence, pinned normalization attempts, review revisions/corrections/exclusions, exact/visual/semantic duplicate decisions, commit attempts, stable destination idempotency key, retry/recovery state, destination receipt and Evidence retention/deletion lifecycle.

Evidence retains only the narrow Alliance, roster-entry and Player scalar scope needed to authorize and explain the handoff.

Evidence also owns the Governor-specific provenance lookup contract used by Roster to validate an approved review. The pre-existing general Evidence reference interface remains family-neutral and is not extended with Governor/Roster/dataset-specific methods.

### `GameWorld/Progression`

Owns canonical Hero and progression catalogue identities, immutable releases/checksums, factual progression bounds/reference relationships and catalogue provenance/conflicts. It exposes read/query contracts only. Screenshot normalization/review cannot add aliases, repair missing catalogue facts, mutate releases or publish inferred truth.

### `Intelligence/Roster`

Owns accepted append-only Governor progression observations, closed typed payloads, captured time, pinned dataset ID/checksum, Evidence/review provenance receipt, destination idempotency, owner authorization/scope validation and the current-state/history projection. Roster corrections/removals are explicit owner operations and are independent of Evidence retention.

## Authorization boundaries

- Read uses existing Intelligence view authority for the Alliance scope.
- Upload, review, duplicate resolution, retry, commit and Evidence deletion require existing Intelligence/Roster write authority (`IntelligencePermission::KingdomManage`).
- HTTP adapters resolve active Alliance/roster scope through owner queries and carry scalar references only.
- Every protected mutation reacquires authority at execution time rather than trusting page state.
- Every Roster destination action independently reacquires current authority and target scope before accepting reviewed meaning.
- Scope drift after review fails closed and requires new review; Evidence is never silently retargeted.
- Duplicate checks are tenant/scope constrained and never disclose cross-Alliance Evidence.
- Any future self-service exception requires an explicit product authorization rule; no accidental bypass is allowed.

## Cross-context provenance interface boundary

Governor Progression review provenance validation is exposed through a dedicated Evidence-owned contract. The shared/family-neutral Evidence reference contract used by Transfer and other consumers must not gain Governor-specific methods.

The dedicated Governor provenance contract must allow Roster to validate before every destination write that the referenced approved review matches the exact:

- Evidence record;
- Alliance;
- Roster entry;
- Governor/Player;
- Governor Progression Evidence kind;
- schema version;
- Progression dataset ID;
- Progression dataset checksum; and
- approved review identifier.

Existing Transfer Evidence consumers must remain compilable/testable without implementing Governor-specific operations. Interface segregation must not weaken destination authorization, provenance, dataset pinning, duplicate handling or idempotency.

## Explicit Evidence scope

Governor Progression Evidence uses one explicit `game_evidence` scope shape:

- `alliance_id` present;
- `roster_entry_id` present;
- `occurrence_id`, `transfer_plan_id` and `transfer_participant_id` absent.

Application validation and database constraints enforce mutual exclusion among Bear Hunt, Transfer and Governor Progression scope shapes. This does not justify generic polymorphic targeting.

## Supported v1 screenshot classes

Only these six classes are supported. Each has an explicit `EvidenceKind`, schema version, executable fixture corpus, allowlisted fields, confidence thresholds and dedicated Roster destination action.

### `governor_profile`

May observe Governor name, Power, progression/Town Center level when reliably visible, Alliance tag when fixture-proven and Kingdom number when fixture-proven. It does not prove Hero roster completeness, equipment or unshown progression families.

### `governor_hero_roster`

May observe canonical Hero identity candidates, visible level, visible star state, visible Widget level and `complete_roster_capture` only when fixture plus reviewer explicitly establish complete roster meaning. Absence from partial capture never proves non-ownership.

### `governor_hero_detail`

May observe canonical Hero identity, level, unambiguous star/substar state and Widget level. Skill levels are outside v1.

### `governor_hero_gear`

May observe canonical Hero identity, explicit screen-local gear slot/type, visible quality/tier, enhancement level and Mastery facts when fixture-backed field separation proves the values belong to that slot.

### `governor_gear`

May observe explicit screen-local Governor gear slots and directly visible quality/tier, level and star state supported by the schema.

### `governor_charms`

May observe explicit screen-local Charm slots and directly visible level. An OCR-visible Charm name may be retained as Evidence provenance, but v1 does not create or commit a canonical `charm_id` because the current pinned Progression release publishes a Charm level ladder rather than Charm identity IDs.

### Deferred screenshot families

Pets, Masters and all other progression panels require an explicit contract addition, schema version, fixture corpus, typed Roster payload and destination behavior before support.

## Classification contract

The expected class selected by the user is a hint, not truth. Classification independently determines a supported Governor Progression class.

- Expected/detected mismatch is surfaced and cannot blindly route to the selected extractor.
- Unsupported or ambiguous screenshots fail closed.
- Classification reason/confidence and implementation version are retained.
- There is no generic Governor screenshot class.
- Only registered Governor Progression kinds enter this review flow.
- Generic `Charm`/`Charms` wording alone does not classify `governor_charms`; an explicit Governor/Chief Charm signature or equivalent fixture-backed structure is required.
- `Charms Collection`/inventory/statistics UI remains `unknown` unless supported Governor structure independently reaches threshold.
- User-selected expected kind never promotes unsupported Charm UI into the supported family.

## Extraction and executable fixture contract

An extractor may emit only fields declared by its schema and proven by its fixture corpus. Every v1 corpus includes canonical layout, alternate supported layout/resolution, safe crop, numeric grouping, low confidence, adjacent unrelated numbers, missing required field, unsupported UI, wrong screenshot class, visual duplicate, semantic-equal and genuinely newer semantic cases.

Every extracted candidate retains raw OCR text, normalized candidate, confidence, bounding region where available, warnings, provider and extractor/schema provenance.

### Compound field boundaries

Adjacent facts on one OCR line must remain separate candidates:

- Hero/Governor Gear `quality` contains only the visible quality/tier and terminates before `Level`/`Lv`, `Mastery`/`Mastery Forge`, `Star`/`Stars`, or end of line.
- `gear_level`, `mastery_level` and `gear_star` contain only their own numeric values.
- Charm `charm_name` contains only the visible name after a fixture-backed `Charm:`/`Charm Name:` label and terminates before `Level`/`Lv` or end of line.
- `charm_level` contains only its numeric value.
- Raw OCR keeps the complete source line even when normalized candidates are separated.

Fixtures assert semantic separation rather than accidental regex spans.

## Normalization and Progression dataset pinning

Normalization is a separate append-only auditable attempt after extraction. Every attempt pins `progression_dataset_id`, `progression_dataset_checksum`, normalizer key/version, normalized canonical/value candidates, match confidence and warnings.

Canonical identity resolution uses `GameWorld/Progression` query contracts against the exact pinned release. Normalization may match identities/aliases exposed by that release but cannot create identities or aliases. Low/ambiguous matches remain review-required. Historical attempts do not silently change when newer releases publish. Re-normalization, if supported, appends a new attempt/revision. Destination commit requires the exact pinned dataset ID/checksum to remain loadable and fails closed on mismatch.

### Automatic retry pinning

The first normalization attempt establishes the automatic-processing Progression pin for that Evidence record, including when the first attempt fails.

- If no prior normalization attempt exists, the first attempt may select the current latest published Progression dataset.
- If any prior normalization attempt exists, every automatic processing retry, queue redelivery, extraction retry or process restart must reuse the dataset ID/checksum from the earliest normalization attempt.
- Retry must load that exact release through the Progression query contract and fail closed if the release is unavailable or checksum mismatches.
- Automatic retry must never fall forward to `latest()` once an Evidence record has a normalization history.
- Migrating an existing Evidence record to a newer dataset is a distinct explicit re-normalization product action. V1 does not provide that action.
- Normalization attempts remain append-only and retain normalizer version plus dataset pin.

## Catalogue-backed validation versus screen-local structure

The destination validates every reviewed fact against the pinned Progression release to the extent that release exposes authoritative meaning. It must not manufacture catalogue identities for structural labels the dataset does not model as identities.

- Hero identity must resolve in the pinned release.
- Hero level/star/substar/Widget values obey the closed progression bounds.
- Hero Gear `slot_id`, Governor Gear `slot_id` and Charm `slot_id` are reviewed screen-local structural keys, not invented Progression entity IDs.
- The current schema-v2 release publishes Hero Gear enhancement through level 200 and Mastery Forging through level 20; validator maxima are derived from those pinned tables rather than broad guesses such as `1000`.
- If a pinned release lacks a required Hero Gear factual bound, that reviewed field fails closed.
- Governor Gear quality/tier and star, when both present, must reconcile to a tier/star step published by the pinned `governor_gear` catalogue. Tier text is canonicalized only to an existing pinned tier.
- A directly visible Governor Gear numeric `level` may remain a screen observation when fixture-proven; it is not promoted into canonical Progression truth merely because a number is visible.
- Charm level is bounded by the pinned `governor_charms` ladder; a synthetic `charm_id` is rejected.

No screen-local structural key is ever written back into `GameWorld/Progression`.

## Evidence and provenance semantics

The original screenshot is immutable Evidence. Machine attempts are append-only and retain implementation/provider/schema versions. Human corrections create immutable reviewed meaning and never rewrite machine output/confidence.

Handoff provenance includes Evidence ID, review/revision ID, screenshot kind/schema version, Alliance/Roster/Player scalar scope, captured time, pinned dataset ID/checksum, destination idempotency key, Roster receipt/observation IDs and accepted-by/accepted-at metadata.

Roster current facts expose source observation and captured date so consumers can distinguish observed state from canonical Progression truth.

## Review workflow

All v1 screenshots require human review. Review exposes target Governor/Roster entry, expected/detected class/confidence, schema/fixture corpus, pinned release/checksum, authorized retained screenshot access, raw OCR/normalized candidates, per-field confidence/warnings, canonical Hero correction restricted to the pinned dataset, captured time, partial/complete roster meaning, duplicate state/resolution, before/after Roster preview, explicit commit and destination receipt/recovery state.

Review cannot create canonical Progression identities or facts.

## Closed reviewed payloads

The approved revision is a closed union keyed by screenshot kind:

```text
governor_profile
  observed_name?
  power?
  progression_level?
  observed_alliance_tag?
  kingdom_number?

governor_hero_roster
  heroes[] { hero_id, level?, star?, widget_level? }
  complete_roster_capture: bool

governor_hero_detail
  hero_id
  level?
  star?
  substar?
  widget_level?

governor_hero_gear
  hero_id
  gear[] { slot_id, quality?, level?, mastery_level? }

governor_gear
  gear[] { slot_id, quality?, level?, star? }

governor_charms
  charms[] { slot_id, level }
```

JSON persistence is allowed only behind validation that enforces these kind-specific closed shapes.

## Roster destination handoff

Narrow screenshot observations use a dedicated append-only Governor Progression observation ledger in `Intelligence/Roster`; they are not forced into the broader `PlayerSnapshot` contract.

Each observation retains Alliance ID, Roster entry ID, Player ID, kind, typed payload, captured time, pinned dataset ID/checksum, `source=screenshot_evidence`, Evidence/review provenance, destination idempotency key and acceptance metadata.

Evidence commits through six explicit Roster actions:

- `RecordGovernorProfileEvidence`
- `RecordHeroRosterEvidence`
- `RecordHeroDetailEvidence`
- `RecordHeroGearEvidence`
- `RecordGovernorGearEvidence`
- `RecordGovernorCharmsEvidence`

Every action reacquires current authority, re-resolves target scope, validates exact approved-review provenance and pinned dataset, validates canonical IDs/factual bounds, appends owner history atomically, enforces stable destination idempotency and returns a scalar receipt.

## Duplicate and idempotency semantics

Four controls remain distinct:

1. **Exact duplicate** — source binary identity inside the authorized Governor/Roster Evidence scope.
2. **Visual duplicate** — perceptual-similarity warning; distinct Evidence remains reviewable.
3. **Semantic duplicate** — deterministic fingerprint over schema version, target scope, captured-time meaning and normalized reviewed payload; equivalent meaning is blocked until an explicit supported resolution.
4. **Destination idempotency** — one stable key per immutable approved review; replay returns the existing Roster receipt without duplicate history.

A genuinely newer captured observation remains importable.

## Cross-context commit recovery

Evidence coordinates the handshake; Roster owns the destination transaction:

```text
approved Evidence review
  -> stable destination key
  -> Roster observation + receipt commit
  -> Evidence acknowledgement/receipt
```

If Roster succeeds and Evidence acknowledgement fails, retry uses the same destination key. Roster returns the existing authorized receipt and Evidence records recovery without appending duplicate owner state.

## Current-state and history projection

`Intelligence/Roster` owns the Governor Progression current-state/history query.

- Latest accepted observation wins only for the same scoped fact.
- Missing fields never erase previous facts.
- Partial Hero roster capture cannot establish absence.
- An explicitly reviewed complete Hero roster capture may update only membership presence/absence semantics.
- Older Hero facts remain current when a newer partial observation did not observe them.
- Same-time/conflicting history remains inspectable rather than silently deleted.
- Every current fact retains observation, Evidence/review and captured-date provenance.

Consumers read this Roster projection, never Evidence candidate tables as domain truth.

## UX states and integration

Governor Progression exposes **Update from screenshot** inside the owning Governor Progression/Roster workflow; there is no generic OCR page.

Responsive/mobile-first states include choose class, upload/scanning, classifying, expected/detected mismatch, extracting/normalizing, unsupported/failed/retry, low-confidence review, canonical identity correction, partial/complete roster confirmation, exact duplicate blocked, visual duplicate warning, semantic duplicate resolution, destination preview, approved/committing, committed receipt, recovered retry, deleted/redacted Evidence with retained destination provenance and permission-denied/target-scope-changed states.

Controls are keyboard usable, semantically labelled, localized, responsive and non-colour-dependent. Retained screenshot access remains private and authorized.

The owning Governor page mounts the Screenshot Intake workspace when the actor has manage permission and the Governor has a Roster entry. Roster current-state/history remains readable according to Intelligence view authority when manage actions are unavailable.

## Downstream read integration

Authorized consumers use Roster owner queries/read models only, including Governor Progression history/current state, Alliance roster intelligence, Transfer/Event planning and Alliance Assistant where relevant. Alliance Assistant cites the Roster observation/Evidence provenance used and never treats machine Evidence candidates as accepted state.

## Audit, outbox, diagnostics and retention

Material transitions are audit/outbox observable where applicable: upload accepted/rejected, classification/extraction/normalization attempts/failures, review approval/correction, duplicate blocked/resolved, commit started/succeeded/failed/recovered, destination deduplication and Evidence deletion/redaction/retention actions.

Diagnostics are privacy-safe and do not expose screenshot content, raw hashes, private OCR text, Player identity or cross-tenant duplicate details.

Evidence retention may remove image/OCR/raw sensitive material after policy boundaries while retaining minimum review/commit/receipt provenance. Accepted Roster observations are owner history and do not cascade-delete with Evidence.

## Acceptance criteria

The family is complete only when all are true:

1. Six v1 screenshot kinds are explicit `EvidenceKind` cases with registered schemas and executable fixture corpora.
2. Independent classification rejects mismatches/unsupported UI safely, including generic Charm screens.
3. Extraction emits only allowlisted fixture-proven fields, preserves provenance and respects compound-field boundaries.
4. Normalization pins immutable Progression dataset ID/checksum and preserves match provenance.
5. Automatic retry reuses the earliest normalization pin and never silently falls forward to a newer dataset.
6. Newer datasets cannot silently alter historical meaning.
7. Review cannot create canonical Progression identities/facts and preserves machine output when corrected.
8. Application/database enforce explicit Governor/Roster Evidence scope without generic polymorphism.
9. Governor review provenance uses a dedicated Evidence-owned contract; shared Evidence contracts remain family-neutral.
10. Six destination actions reacquire authority and validate scope, provenance and exact pinned dataset.
11. Accepted observations are append-only, closed/typed and owner-idempotent.
12. Hero identity and catalogue-backed facts/bounds validate against the pinned release; screen-local slots remain non-canonical structure.
13. Partial observations do not erase unobserved state; complete roster semantics are explicit and Hero-Roster-only.
14. Exact, visual, semantic duplicate controls and destination idempotency remain distinct and tenant-safe.
15. Owner-success/Evidence-acknowledgement crash recovery cannot duplicate Roster history.
16. Evidence deletion/redaction cannot cascade into accepted Roster history.
17. Governor Progression exposes accessible/mobile-first upload-review-preview-commit-receipt UX with localization.
18. Current-state/history composition exposes provenance/captured dates and is mounted in Governor Progression.
19. Authorized downstream consumers use Roster reads rather than Evidence candidate tables or Progression mutation.
20. Product/architecture/reference/operations current-truth documentation agrees with implementation.
21. Clean PostgreSQL install plus applicable PHP, frontend, architecture, accessibility/visual, security and repository-wide gates pass on one immutable candidate.

## Verification requirements

The release candidate must pass on the same immutable SHA, as applicable:

- clean PostgreSQL `migrate:fresh`;
- Governor fixture/schema/classification/extraction/dataset-retry/catalogue-bound/authorization/provenance/interface-boundary/duplicate/idempotency/projection/destination tests;
- pre-existing Transfer Evidence boundary regression tests;
- full PHP test suite;
- Pint;
- Larastan/PHPStan;
- frontend lint, Prettier, typecheck and production build;
- Architecture V3 verification including no foreign-context persistence models in Evidence;
- accessibility/visual regression for the integrated Governor Screenshot Intake states;
- CodeQL;
- Dependency Review;
- all other required repository checks.

## Delivery ledger

Status values: `Planned`, `In progress`, `Complete`, `Blocked`. `Complete` requires implementation, tests/documentation agreement and applicable verification on the final immutable candidate; code presence alone is insufficient.

| ID | Deliverable | Status |
| --- | --- | --- |
| GP-01 | Product contract, ownership and acceptance criteria | Complete |
| GP-02 | Explicit Governor Evidence kinds and schema registry | In progress |
| GP-03 | Executable fixture corpora for all six v1 schemas | In progress |
| GP-04 | Independent fail-closed classification support | In progress |
| GP-05 | Schema-bound extraction + compound-field boundaries | In progress |
| GP-06 | Dataset-pinned normalization + automatic retry pin preservation | In progress |
| GP-07 | Explicit Governor/Roster Evidence persistence scope | In progress |
| GP-08 | Dedicated Governor provenance interface + shared-contract isolation | In progress |
| GP-09 | Immutable typed Governor review revisions | In progress |
| GP-10 | Exact/visual/semantic duplicate semantics | In progress |
| GP-11 | Roster append-only Governor progression observation ledger | In progress |
| GP-12 | Six Roster destination actions + owner writer | In progress |
| GP-13 | Pinned catalogue-bound destination validation | In progress |
| GP-14 | Destination receipt/idempotency + crash recovery | In progress |
| GP-15 | Roster current-state/history query/projection | In progress |
| GP-16 | Governor Progression upload/review/preview/commit UX | In progress |
| GP-17 | Responsive/accessibility/localization/visual coverage | In progress |
| GP-18 | Audit/outbox/privacy-safe diagnostics/retention | In progress |
| GP-19 | Downstream authorized-read integration hooks | In progress |
| GP-20 | Architecture/reference/operations/current-truth reconciliation | In progress |
| GP-21 | Unit/feature/fixture/authorization/idempotency/interface/catalogue-bound tests | In progress |
| GP-22 | Repository-wide release verification on one immutable candidate | In progress |

The family remains **Active delivery** until every ledger row is `Complete` and the final documentation-only completion commit also passes required repository gates.