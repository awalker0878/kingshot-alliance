# Screenshot Intake: Governor Progression

Status: Active delivery — implementation present; release verification incomplete (2026-08-26)

This document is the implementation source of truth for the Governor Progression Screenshot Intake family. Delivery is not complete until every requirement and delivery-ledger item below is implemented, reconciled with current-truth architecture/reference/operations documentation, and verified by all applicable repository gates on one immutable candidate.

## Product outcome

Governor Progression Screenshot Intake turns narrow, fixture-proven KingShot Governor progression screenshots into reviewed, append-only `Intelligence/Roster` observations without transferring ownership of canonical progression facts to Evidence or Roster.

The capability preserves three separate truths:

1. `GameWorld/Progression` owns immutable, versioned factual KingShot catalogue truth.
2. `Intelligence/Evidence` owns screenshots, machine attempts, normalized candidates, review revisions, duplicate decisions and commit provenance.
3. `Intelligence/Roster` owns accepted, dated Governor progression observations and the current-state projection derived from append-only history.

Screenshot Intake must never create, modify, correct, infer or publish canonical `GameWorld/Progression` facts.

## Non-negotiable architecture rules

1. Governor Progression is an explicit Screenshot Intake family inside `Intelligence/Evidence`; there is no Governor Progression OCR bounded context.
2. Do not introduce a generic OCR ingestion framework, generic `target_type`/`target_id` Evidence target, or unconstrained bag-of-fields observation model.
3. `GameWorld/Progression` is read-only to this capability and is used only for normalization and validation against a pinned immutable dataset release.
4. `Intelligence/Roster` is the reviewed destination owner. Evidence never writes Roster tables directly.
5. Cross-context writes carry scalar IDs/value objects only. Foreign Eloquent models never cross the boundary.
6. All v1 screenshot classes require human review. Automatic commit is outside this contract.
7. Missing or unshown fields mean unknown/not observed; they are never converted to zero or absence.
8. Partial screenshots never imply complete roster state. Completeness is an explicit reviewed fact supported only by the Hero Roster class.
9. Publishing a newer Progression dataset never rewrites or silently re-normalizes historical Evidence attempts, review revisions or Roster observations.
10. Deleting or redacting Evidence never cascades into accepted Roster history.

## Capability ownership

### `Intelligence/Evidence` owns

- private screenshot binaries, source metadata, source checksum and derived-representation provenance;
- upload validation/security scanning and retained-image lifecycle;
- expected screenshot class selected by the user;
- independent classification result, reason and confidence;
- OCR/provider attempts and retained raw OCR text;
- schema, extractor, normalizer and provider versions;
- extracted raw field candidates, bounds and confidence;
- normalization attempts against a concrete Progression dataset ID/checksum;
- canonical identity candidates and match confidence/warnings;
- immutable review revisions, corrections and exclusions;
- exact, visual and semantic duplicate decisions;
- commit attempts, stable destination idempotency key, retry/recovery state and destination receipt;
- Evidence deletion/redaction/retention lifecycle;
- only the narrow scalar Alliance, roster-entry and Player references required to authorize and explain the handoff.

### `GameWorld/Progression` owns

- canonical Hero, gear, charm and other progression catalogue identities and aliases;
- immutable dataset releases and checksums;
- factual progression bounds and reference relationships;
- catalogue source provenance/conflicts.

It exposes read/query contracts only. Screenshot normalization or review cannot add aliases, repair missing catalogue facts, mutate a release or publish inferred catalogue truth.

### `Intelligence/Roster` owns

- accepted append-only Governor progression observations;
- observation kind and closed typed payload;
- captured/observed time;
- pinned Progression dataset ID/checksum retained on each observation;
- Evidence/review provenance receipt;
- owner-side destination idempotency;
- owner authorization and roster-entry/Player scope validation;
- current-state/history projection over accepted observation history;
- explicit correction/removal operations for Roster-owned history.

## Authorization boundaries

- read access uses the existing Intelligence view authority for the Alliance scope;
- upload, review, duplicate resolution, retry, commit and Evidence deletion require the existing Intelligence/Roster write authority (`IntelligencePermission::KingdomManage` in the current model);
- the HTTP adapter resolves the active Alliance through an Alliance-owned query and receives only scalar scope references; it must not query foreign Alliance persistence models directly;
- all protected mutations reacquire authority at execution time rather than trusting page/controller state;
- the destination Roster action independently reacquires current authority and target scope before accepting reviewed meaning;
- scope drift after review fails closed and requires a new review; Evidence is never silently retargeted;
- exact/visual/semantic duplicate checks are scoped so they cannot disclose cross-Alliance Evidence;
- any future self-service exception must be an explicit product/authorization rule that proves the target Roster entry belongs to the active Player; it must not accidentally bypass the current write permission.

## Explicit Evidence scope

Governor Progression Evidence adds one explicit scope shape to `game_evidence`:

- `alliance_id` present;
- `roster_entry_id` present;
- `occurrence_id`, `transfer_plan_id` and `transfer_participant_id` absent.

Application validation and database constraints enforce mutual exclusion among Bear Hunt, Transfer and Governor Progression scope shapes. Adding this family does not justify a generic polymorphic target abstraction.

## Supported v1 screenshot classes

Only these six classes are supported. Each has an explicit `EvidenceKind`, schema version, fixture corpus, allowlisted fields, confidence thresholds and dedicated Roster destination action.

### `governor_profile`

May produce only fixture-proven visible profile observations:

- observed Governor name;
- Power;
- progression/Town Center level when reliably visible;
- Alliance tag when fixture-proven;
- Kingdom number when fixture-proven.

It does not prove Hero roster completeness, equipment or any unshown progression family.

### `governor_hero_roster`

May produce:

- canonical Hero identity candidate per visible row/card;
- visible level;
- visible star state when fixture-proven;
- visible Widget level when fixture-proven;
- `complete_roster_capture` only when the fixture and reviewer explicitly establish complete roster meaning.

Absence from a partial capture never proves non-ownership.

### `governor_hero_detail`

May produce:

- canonical Hero identity candidate;
- level;
- star/substar state only when unambiguous;
- Widget level.

Skill levels remain outside v1 unless a future schema/fixture contract proves reliable field separation.

### `governor_hero_gear`

May produce:

- canonical Hero identity;
- explicit gear slot/type;
- visible quality/tier/level;
- Mastery facts only when the fixture proves the value belongs to that slot.

### `governor_gear`

May produce explicit Governor gear slots and directly visible quality/tier, level and star state supported by the schema.

### `governor_charms`

May produce explicit charm slots, canonical charm identity when available, and directly visible charm level supported by the schema.

### Deferred screenshot families

Pets, Masters and any other progression panels are not generic fallbacks. They require an explicit product-contract addition, schema version, fixture corpus, typed Roster payload and destination behavior before support.

## Classification contract

The expected class selected by the user is a hint, not truth. Classification independently determines a supported Governor Progression kind.

- expected/detected mismatch is surfaced and cannot be routed blindly to the selected extractor;
- unsupported or ambiguous screenshots fail safely;
- classifier confidence/reason are retained;
- there is no generic Governor screenshot class;
- only registered Governor Progression kinds enter this review flow.

## Extraction and fixture contract

An extractor may emit only fields declared by its schema and proven by that schema's fixture corpus. Fixture coverage is an executable allowlist proof, not illustrative sample data.

Every v1 schema fixture corpus covers at least:

- canonical layout;
- alternate supported resolution/layout;
- safe crop;
- numeric grouping;
- low-confidence candidate;
- adjacent unrelated numbers;
- missing required field;
- unsupported UI variant;
- wrong screenshot class;
- visually duplicated/recompressed image meaning;
- semantic-equal meaning;
- genuinely newer semantic meaning.

Every extracted candidate retains raw OCR text, normalized candidate, confidence, bounding region where available, warnings and extractor/schema/provider provenance. Adjacent numbers, decorative labels and unrelated panels must not leak into supported fields.

## Normalization and Progression dataset pinning

Normalization is a separate auditable attempt after extraction.

Every attempt pins:

- `progression_dataset_id`;
- `progression_dataset_checksum`;
- normalizer key/version;
- normalized canonical IDs/value candidates;
- identity-match confidence and warnings.

Canonical identity resolution uses `GameWorld/Progression` query contracts against the pinned release. Normalization may match known aliases/identities exposed by that release but cannot create an identity or alias and cannot mutate catalogue truth.

A low or ambiguous identity match remains review-required. A later Progression release does not change existing normalization/review history. Re-normalization, if supported, appends a new attempt/revision rather than mutating the old one. Destination commit requires the exact pinned dataset ID/checksum to remain loadable; mismatch fails closed.

## Evidence and provenance semantics

The original screenshot is immutable Evidence. Machine attempts are append-only and retain implementation/provider/schema versions. Human corrections create immutable reviewed meaning and never rewrite machine output or confidence.

Provenance retained through the handoff includes:

- Evidence ID;
- review/revision ID;
- screenshot kind and schema version;
- target Alliance/Roster/Player scalar scope;
- captured/observed time;
- pinned Progression dataset ID/checksum;
- destination idempotency key;
- Roster receipt/observation IDs;
- accepted-by and accepted-at metadata.

Retained Roster facts expose the source observation and captured date so consumers can distinguish current derived state from canonical Progression truth.

## Review workflow

All v1 screenshots require human review. The review experience must expose:

- target Governor/Roster entry;
- expected and detected class plus classification confidence;
- schema version/fixture corpus;
- pinned Progression release/checksum;
- retained screenshot access when the binary still exists;
- raw OCR and normalized candidates;
- per-field confidence/warnings;
- canonical identity correction restricted to identities in the pinned dataset;
- captured/observed time;
- partial versus complete Hero roster meaning where applicable;
- exact/visual/semantic duplicate state and required resolution;
- before/after Roster preview;
- explicit commit;
- destination receipt/recovery state after commit.

Review cannot create canonical Progression facts or identities.

## Closed reviewed payloads

The approved revision is a closed union keyed by screenshot kind, not an arbitrary field bag:

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
  charms[] { slot_id, charm_id?, level? }
```

JSON persistence is allowed only behind application validation enforcing those closed kind-specific shapes.

## Roster destination handoff

Narrow screenshot observations use the dedicated append-only Governor Progression observation ledger in `Intelligence/Roster`; they are not forced into the broader `PlayerSnapshot` contract.

Every observation retains Alliance ID, Roster entry ID, Player ID, kind, typed payload, captured time, pinned dataset ID/checksum, `source=screenshot_evidence`, Evidence/review provenance, destination idempotency key and acceptance metadata.

Evidence commits through six explicit Roster actions:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

Every action must reacquire current write authority, re-resolve target scope, validate the exact approved review provenance, require the exact pinned dataset, validate canonical IDs/factual bounds, append owner history atomically, enforce stable destination idempotency and return a scalar receipt.

## Duplicate and idempotency semantics

Four controls remain distinct:

1. **Exact duplicate** — source binary identity inside the authorized Governor/Roster Evidence scope.
2. **Visual duplicate** — perceptual-similarity warning; distinct Evidence remains reviewable.
3. **Semantic duplicate** — deterministic fingerprint over schema version, target scope, captured-time meaning and normalized reviewed payload. Equivalent reviewed meaning is blocked until an explicit supported resolution.
4. **Destination idempotency** — one stable key for one immutable approved review. Replay returns the existing Roster receipt without appending duplicate history.

A genuinely newer captured observation remains importable.

## Cross-context commit recovery

Evidence coordinates the handshake; Roster owns the destination transaction.

```text
approved Evidence review
  -> stable destination key
  -> Roster observation + receipt commit
  -> Evidence acknowledgement/receipt
```

If the Roster transaction succeeds and Evidence acknowledgement fails, retry uses the same destination key. Roster returns the existing authorized receipt and Evidence records recovery without appending duplicate owner state.

## Current-state and history projection

`Intelligence/Roster` provides the owner query for Governor Progression history/current state.

Projection rules:

- latest accepted observation wins only for the same scoped fact;
- missing fields never erase previously observed facts;
- partial Hero roster capture cannot establish absence;
- an explicitly reviewed complete Hero roster capture may update only roster membership presence/absence semantics;
- older facts for a Hero can remain current when a newer partial observation did not observe those fields;
- conflicting/same-time history remains inspectable rather than silently deleted;
- every current fact retains observation, Evidence/review and captured-date provenance.

Consumers read this Roster-owned projection, never Evidence tables as domain truth.

## UX states and integration

Governor Progression exposes **Update from screenshot** inside the owning Governor Progression/Roster workflow. There is no generic OCR page.

Required responsive/mobile-first states:

- choose supported screenshot class;
- upload/scanning;
- classifying;
- expected/detected mismatch;
- extracting/normalizing;
- unsupported/failed/retry;
- low-confidence review;
- canonical identity correction;
- partial/complete roster confirmation;
- exact duplicate blocked;
- visual duplicate warning;
- semantic duplicate resolution;
- destination preview;
- approved/committing;
- committed receipt;
- recovered retry;
- deleted/redacted Evidence with retained destination provenance;
- permission denied / target scope changed.

Controls are keyboard usable, semantically labelled, localized, responsive and non-colour-dependent for confidence/error meaning. Retained screenshot access stays private and authorized.

The owning Governor page must mount the Screenshot Intake workspace whenever the current actor has manage permission and the Governor has a Roster entry. Read-only/current-state history remains available according to Intelligence view authority even when manage actions are unavailable.

## Downstream read integration

Authorized consumers use Roster owner queries/read models only, including Governor Progression history/current state, Alliance roster intelligence, Transfer/Event planning when progression facts are relevant, and Alliance Assistant. Alliance Assistant cites the Roster observation/Evidence provenance used and never treats machine Evidence candidates as accepted game state.

## Audit, outbox and diagnostics

Material transitions are audit/outbox observable where applicable: upload accepted/rejected, classification/extraction/normalization attempts and failures, review approval/correction, duplicate blocked/resolved, commit started/succeeded/failed/recovered, destination deduplication and Evidence deletion/redaction/retention actions.

Diagnostics are privacy-safe and must not expose screenshot content, raw hashes, private OCR text, Player identity or cross-tenant duplicate details.

## Retention and correction

Evidence retention may remove image/OCR/raw sensitive material after the configured boundary while retaining the minimum review/commit/receipt provenance required to explain historical handoffs. Accepted Roster observations are owner history and are not cascaded by Evidence deletion. Roster correction/removal is a separate explicit audited owner operation.

## Acceptance criteria

The family is complete only when all are true:

1. All six v1 screenshot kinds are explicit `EvidenceKind` cases with registered schemas and executable fixture corpora.
2. Classification independently verifies the expected class and rejects mismatch/unsupported screenshots safely.
3. Extraction emits only schema-allowlisted fixture-proven fields with raw OCR/confidence/bounds/warnings retained.
4. Normalization pins immutable Progression dataset ID/checksum and preserves identity-match provenance.
5. A newer Progression release cannot silently alter historical normalization/review/observation meaning.
6. Review cannot create canonical Progression identities/facts and preserves machine output when corrected.
7. Application/database enforce the explicit Governor/Roster Evidence scope without generic polymorphism.
8. All six destination actions reacquire current Roster authority and validate target scope plus pinned dataset.
9. Accepted observations are append-only, closed/typed and owner-idempotent.
10. Partial observations do not erase unobserved state; complete roster semantics are explicit and Hero-Roster-only.
11. Exact, visual, semantic duplicate controls and destination idempotency remain distinct and tenant-safe.
12. The owner-success/Evidence-acknowledgement crash window recovers without duplicate Roster history.
13. Evidence deletion/redaction cannot cascade into accepted Roster history.
14. Governor Progression exposes accessible/mobile-first upload-review-preview-commit-receipt UX with localization.
15. Current-state/history composition exposes provenance/captured dates and is mounted in Governor Progression.
16. Authorized downstream consumers use Roster reads rather than Evidence candidate tables or Progression mutation.
17. Product/architecture/reference/operations current-truth documentation agrees with implementation.
18. Clean PostgreSQL install plus applicable PHP, frontend, architecture, accessibility/visual, security and repository-wide gates pass on one immutable candidate.

## Verification requirements

A release candidate is not complete until the same immutable SHA passes, as applicable:

- clean PostgreSQL `migrate:fresh`;
- Governor fixture/schema, authorization, provenance, duplicate, idempotency, projection and destination behavior tests;
- full PHP test suite;
- Pint;
- Larastan/PHPStan;
- frontend lint, Prettier, typecheck and production build;
- Architecture V3 verification, including no foreign-context persistence models in Evidence;
- accessibility/visual regression covering the integrated Governor Screenshot Intake states;
- CodeQL;
- Dependency Review;
- all other required repository checks.

## Delivery ledger

Status values: `Planned`, `In progress`, `Complete`, `Blocked`.

`Complete` requires implementation, tests/documentation agreement and applicable verification on the release candidate; code presence alone is insufficient.

| ID | Deliverable | Status |
| --- | --- | --- |
| GP-01 | Product contract, ownership and acceptance criteria | Complete |
| GP-02 | Explicit Governor Evidence kinds and schema registry | In progress |
| GP-03 | Fixture corpora for all six v1 schemas | In progress |
| GP-04 | Independent classification support | In progress |
| GP-05 | Schema-bound extraction support | In progress |
| GP-06 | Dataset-pinned normalization + canonical identity matching | In progress |
| GP-07 | Explicit Governor/Roster Evidence persistence scope | In progress |
| GP-08 | Immutable typed Governor review revisions | In progress |
| GP-09 | Exact/visual/semantic duplicate semantics | In progress |
| GP-10 | Roster append-only Governor progression observation ledger | In progress |
| GP-11 | Six Roster destination actions + owner writer | In progress |
| GP-12 | Destination receipt/idempotency + crash recovery | In progress |
| GP-13 | Roster current-state/history query/projection | In progress |
| GP-14 | Governor Progression upload/review/preview/commit UX | In progress |
| GP-15 | Responsive/accessibility/localization/visual coverage | In progress |
| GP-16 | Audit/outbox/privacy-safe diagnostics/retention | In progress |
| GP-17 | Downstream authorized-read integration hooks | In progress |
| GP-18 | Architecture/reference/operations/current-truth reconciliation | In progress |
| GP-19 | Unit/feature/fixture/authorization/idempotency tests | In progress |
| GP-20 | Repository-wide release verification on immutable candidate | In progress |

The family remains **Active delivery** until every ledger row is `Complete` and the final documentation-only completion commit also passes the required repository gates.