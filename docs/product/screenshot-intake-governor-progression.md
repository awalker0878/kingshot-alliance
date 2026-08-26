# Screenshot Intake: Governor Progression

Status: Active delivery — 2026-08-26

This document is the implementation source of truth for the Governor Progression Screenshot Intake family. Delivery is not complete until every requirement and delivery-ledger item in this document is implemented, reconciled with current-truth architecture/reference/operations documentation, and verified by the applicable repository gates.

## Product outcome

Governor Progression Screenshot Intake turns narrow, fixture-proven KingShot Governor progression screenshots into reviewed, append-only `Intelligence/Roster` observations without transferring ownership of canonical progression facts to Evidence or Roster.

The product outcome is a faster and more trustworthy way to maintain observed Governor state so Governor progression history, Alliance roster intelligence, Transfer planning, Event planning and Alliance Assistant can consume fresher authorized observations.

The capability preserves three separate truths:

1. `GameWorld/Progression` owns immutable, versioned factual KingShot catalogue truth.
2. `Intelligence/Evidence` owns the screenshot, OCR/classification/extraction attempts, normalized candidates, review revisions, duplicate decisions and commit provenance.
3. `Intelligence/Roster` owns accepted, dated Governor progression observations and the current-state projection derived from that append-only history.

Screenshot Intake must never create, modify, correct, infer or publish canonical `GameWorld/Progression` facts.

## Non-negotiable architecture rules

1. Governor Progression is an explicit Screenshot Intake family inside `Intelligence/Evidence`; there is no Governor OCR bounded context.
2. Do not introduce a generic OCR ingestion framework, generic `target_type`/`target_id` evidence target, or unconstrained bag-of-fields observation model.
3. `GameWorld/Progression` is read-only to this capability and is used only for normalization/validation against a pinned immutable dataset release.
4. `Intelligence/Roster` is the destination owner. Evidence never writes Roster tables directly.
5. Cross-context writes carry scalar IDs/value objects only. Foreign Eloquent models never cross the boundary.
6. All v1 screenshot classes require human review. Automatic commit is outside this contract.
7. Missing/unshown fields mean `unknown`/`not observed`; they are never converted to zero or absence.
8. Partial screenshots never imply complete roster state. Completeness is an explicit reviewed fact supported only by the Hero Roster schema.
9. Published Progression dataset changes never rewrite or silently re-normalize historical Evidence attempts or Roster observations.
10. Deleting/redacting Evidence never cascades into an accepted Roster observation.

## Capability ownership

### Intelligence/Evidence owns

- private screenshot binary, metadata, source checksum and derived-representation provenance;
- upload security scanning and retained-image lifecycle;
- expected screenshot class selected by the user;
- independent classification result and confidence;
- OCR/provider attempts and retained raw OCR text;
- schema/extractor/provider versions;
- extracted raw field candidates, bounding regions and field confidence;
- normalization attempts against a concrete Progression dataset release/checksum;
- canonical identity candidates and match confidence/warnings;
- immutable review revisions and human corrections/exclusions;
- exact, visual and semantic duplicate Evidence decisions;
- commit attempts, stable destination idempotency key, retry/recovery state and destination receipt;
- Evidence deletion/redaction/retention lifecycle;
- the narrow scalar Alliance/roster-entry/Player references required to authorize and explain the handoff.

### GameWorld/Progression owns

- canonical Hero/Gear/Charm/Pet/Master identities and aliases;
- immutable dataset releases and checksums;
- factual progression bounds and reference relationships;
- source provenance/conflicts for catalogue truth.

It exposes read/query contracts only. Screenshot review cannot add an alias, repair a missing catalogue fact or mutate a release.

### Intelligence/Roster owns

- accepted append-only Governor progression observations;
- observation kind and typed observed payload;
- observed/captured time;
- pinned Progression dataset ID/checksum retained on the observation;
- Evidence/review provenance receipt;
- owner-side destination idempotency;
- owner authorization and roster-entry/Player scope validation;
- the derived current-state projection over observation history;
- explicit correction/removal operations for Roster-owned history.

## Evidence scope

Governor Progression Evidence adds one explicit scope shape to `game_evidence`:

- `alliance_id` present;
- `roster_entry_id` present;
- `occurrence_id`, `transfer_plan_id` and `transfer_participant_id` absent.

The application and database must enforce mutual exclusion between Bear Hunt, Transfer and Governor Progression scope shapes.

The Roster entry and its Player/Alliance relationship are re-resolved at every protected upload/review/commit/retry/delete boundary. Approved Evidence snapshots the scalar target scope. A material scope change after approval rejects commit and requires re-review; Evidence is never silently retargeted.

## Supported v1 screenshot classes

Only the following v1 classes are supported. Each class has a versioned schema, fixture corpus, allowlisted fields, confidence thresholds and destination action.

### `governor_profile`

May emit only fixture-proven visible profile facts such as:

- observed Governor name;
- Power;
- progression/Town Center level where reliably visible;
- Alliance tag and Kingdom number only where fixture-proven.

A profile screenshot does not prove Hero roster completeness, Hero equipment or any unshown progression family.

### `governor_hero_roster`

May emit:

- canonical Hero identity candidate for each visible row/card;
- Hero level where visible;
- star state where visible and fixture-proven;
- Widget level where visible and fixture-proven;
- `complete_roster_capture` only when the screenshot fixture and reviewer explicitly establish complete roster meaning.

Absence of a Hero from a partial capture does not prove non-ownership.

### `governor_hero_detail`

May emit:

- canonical Hero identity candidate;
- level;
- stars/substars/tier only where unambiguously fixture-proven;
- Widget level;
- skill levels only after fixture evidence demonstrates reliable field separation.

### `governor_hero_gear`

May emit:

- canonical Hero identity;
- canonical Hero Gear slot/type;
- visible gear quality/tier/level;
- enhancement/Mastery facts only when the fixture proves the value belongs to that slot.

### `governor_gear`

May emit:

- canonical Governor Gear slot;
- visible tier/quality/star/enhancement state;
- factual values only where directly visible.

### `governor_charms`

May emit:

- canonical Governor Charm slot/family;
- visible charm level/tier;
- directly visible factual state only.

### Deferred classes

Pets and Masters are not generic fallback extraction targets. They may be added later only as explicit schema versions with their own fixture corpora, product-contract additions and typed Roster payloads.

## Classification contract

The expected class selected by the user is a hint, not truth. Classification independently determines a supported Governor Progression kind.

- expected/detected mismatch is surfaced and cannot be routed blindly through the selected extractor;
- unsupported/ambiguous screenshots fail safely;
- classifier confidence is retained;
- no generic Governor screenshot class exists;
- only registered Governor Progression kinds may enter the family review flow.

## Extraction and fixture contract

An extractor may emit only fields declared by its registered schema and proven by that schema's fixture corpus.

Fixture coverage is an allowlist proof, not illustrative sample data. Adjacent numbers, decorative labels, hidden/occluded fields, unrelated panels and ambiguous icons must have negative fixtures where they could otherwise be confused with a supported field.

Every extracted field retains:

- raw OCR text/value;
- normalized candidate if one exists;
- extractor confidence;
- bounding region when provided;
- warning/reason code;
- extractor/schema/provider version.

## Progression normalization and dataset pinning

Normalization is a separate auditable attempt after extraction.

Every normalization attempt pins:

- `progression_dataset_id`;
- `progression_dataset_checksum`;
- normalization implementation/version;
- normalized canonical IDs/value objects;
- identity-match confidence and warnings.

Canonical identity resolution uses `ProgressionDatasetQuery`/Progression-owned query contracts and explicit source aliases. It must not perform fuzzy-name overwrite of canonical truth.

A machine may propose a close canonical identity when normalization can explain the candidate, but a low/ambiguous identity match remains review-required and cannot be silently accepted at commit.

If a new Progression release appears after normalization, existing machine/review history remains pinned. Re-normalization is an explicit new attempt/revision; it never mutates the old attempt.

Destination commit requires the exact pinned dataset ID/checksum to remain loadable. Checksum mismatch fails closed.

## Review contract

All v1 Governor Progression screenshots require human review.

The review surface must show:

- target Governor/roster entry;
- expected and detected screenshot class;
- classification confidence;
- schema version and fixture corpus;
- pinned Progression dataset version/checksum;
- raw OCR text/candidate;
- normalized canonical identity/value;
- field confidence and warnings;
- manual corrections/exclusions;
- captured/observed time;
- complete-vs-partial roster meaning where applicable;
- exact/visual/semantic duplicate state;
- before/after Roster destination preview;
- explicit approval/commit action.

Human corrections append immutable reviewed meaning. They do not rewrite the machine OCR, normalized candidate or machine confidence that preceded the correction.

Review may select only canonical Progression identities present in the pinned dataset. Review cannot create new catalogue identities or aliases.

## Typed reviewed payloads

The approved revision uses a closed union keyed by evidence kind rather than an arbitrary JSON bag.

Conceptually:

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

The persisted reviewed payload may use JSON for storage convenience only if application validation enforces this closed kind-specific shape. A generic user-defined field map is prohibited.

## Roster observation model

Do not force narrow screenshots into the existing broad `PlayerSnapshot` requirements. Introduce a typed append-only Governor Progression observation ledger owned by `Intelligence/Roster`.

Each observation retains:

- Alliance ID;
- roster-entry ID;
- Player ID;
- observation kind;
- typed payload;
- captured/observed time;
- pinned Progression dataset ID/checksum;
- source = `screenshot_evidence`;
- Evidence ID + approved review ID/revision;
- destination idempotency key;
- accepted-by Player and accepted-at timestamp.

A shared owner-internal writer validates/persists the closed payload. Public Evidence destination actions remain schema-specific so ownership and authorization are explicit.

## Destination actions

Governor Progression Evidence commits through dedicated `Intelligence/Roster` actions:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

Every action must:

1. reacquire current Intelligence write authorization;
2. re-resolve Alliance + roster entry + Player scope;
3. validate the immutable approved Evidence review/provenance;
4. load the exact pinned Progression dataset/checksum;
5. validate all canonical IDs and factual bounds represented by the typed payload;
6. append owner history atomically;
7. enforce a stable owner-side destination idempotency key;
8. return a scalar receipt.

Evidence records only the returned receipt/provenance.

## Duplicate and idempotency contract

Four controls remain distinct:

1. **Exact duplicate** — source binary identity inside the authorized Governor/roster Evidence scope.
2. **Visual duplicate** — perceptual-similarity warning; distinct Evidence remains reviewable.
3. **Semantic duplicate** — deterministic fingerprint over schema version, pinned target scope, captured-time meaning and normalized reviewed payload. Equivalent reviewed meaning is blocked until an explicit supported resolution.
4. **Destination idempotency** — one stable key for one immutable approved review revision. Retry returns the existing Roster receipt without appending duplicate history.

A newer observation remains importable when its captured/observed boundary or reviewed meaning is genuinely different.

## Cross-context commit recovery

Evidence coordinates the handshake; Roster owns the destination transaction.

```text
Evidence approved
  -> stable destination key created
  -> Roster destination action commits observation + receipt
  -> Evidence records receipt
```

If Roster succeeds and Evidence acknowledgement fails, a normal retry uses the same destination key. Roster returns the existing authorized receipt and Evidence records the recovered acknowledgement. Operators do not repair this window by editing owner tables.

## Current-state projection

`Intelligence/Roster` exposes an owner query that composes the latest accepted fact per typed progression field while retaining provenance and captured time.

The projection must not simply replace the entire Governor state with the newest partial screenshot. For example, a new Hero Detail screenshot may update Amadeus level/Widget while older still-current Hero Gear facts remain independently visible with their own observation dates.

Projection rules:

- latest accepted observation wins only for the same scoped fact;
- missing fields do not erase previously observed fields;
- complete Hero roster capture may establish current membership/absence semantics only for the roster membership dimension;
- partial roster captures cannot establish absence;
- conflicting same-time observations remain inspectable rather than silently overwritten;
- every displayed current fact can identify its observation and captured date.

## Authorization

- upload/review/commit/delete requires the existing Intelligence/Roster write authority for the target Alliance scope;
- read access uses existing Intelligence view authority;
- self-service Governor progression may be enabled only through an explicit authorization rule that still proves the target roster entry belongs to the active Player; it must not accidentally bypass `IntelligencePermission::KingdomManage`;
- all protected mutations reauthorize at execution time rather than trusting stale controller state;
- duplicate checks never disclose cross-Alliance Evidence.

## UX states

Governor Progression exposes **Update from screenshot** in the owning progression/roster workflow rather than a generic OCR page.

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

Controls must be keyboard usable, semantically labelled, localized, responsive and non-colour-dependent for confidence/error meaning. Retained screenshot access remains private/authorized.

## Read-consumer integration

Consumers use Roster owner queries/read models only:

- Governor Progression history/current state;
- Alliance roster intelligence;
- Transfer planning where current progression facts are relevant;
- Event planning where current roster facts are relevant;
- Alliance Assistant questions such as "What heroes do we have recorded for me?" and "When was my roster last updated?".

Consumers do not read Evidence tables as domain truth. Alliance Assistant remains authorized-read composition and cites the Roster observation/Evidence provenance used.

## Audit, outbox and privacy-safe diagnostics

Material transitions are observable:

- Governor screenshot upload accepted/rejected;
- classification/extraction/normalization attempt started/succeeded/failed;
- review approved/corrected;
- duplicate blocked/resolved;
- commit started/succeeded/failed/recovered;
- Roster destination deduplicated;
- Evidence deleted/redacted/purged;
- Roster correction/removal when separately invoked.

Diagnostics must not expose screenshot content, raw hashes, private OCR text, Player identity or cross-tenant duplicate information.

## Retention

Evidence retention may remove image/OCR/raw sensitive material after the configured boundary while retaining the minimum review/commit/receipt provenance needed to explain the historical handoff.

Roster observations are owner history and are not cascaded by Evidence retention. Correcting/removing an accepted observation is an explicit audited Roster owner operation.

## Acceptance criteria

The capability is complete only when all of the following are true:

1. All six v1 screenshot kinds are explicit `EvidenceKind` cases with registered versioned schemas and fixture corpora.
2. Classification independently verifies the expected Governor kind and rejects mismatches/unsupported screenshots safely.
3. Extraction emits only schema-allowlisted fixture-proven fields and retains raw OCR/confidence/bounds/warnings.
4. Normalization pins an immutable Progression dataset ID/checksum and preserves identity-match confidence/provenance.
5. A newer Progression release never silently changes historical normalization/review/observation meaning.
6. Review cannot create canonical Progression identities and preserves machine output when corrected.
7. The database/application enforce the explicit Governor Roster Evidence scope without generic polymorphism.
8. All six destination Actions reacquire current Roster authority and validate target scope + pinned dataset.
9. Accepted observations are append-only, typed and idempotent in `Intelligence/Roster`.
10. Partial observations do not erase unobserved state; complete roster semantics are explicit and Hero-Roster-only.
11. Exact, visual, semantic duplicate controls and destination idempotency remain distinct and tenant-safe.
12. The owner-success/Evidence-acknowledgement crash window recovers without duplicate Roster history.
13. Evidence deletion/redaction cannot cascade into accepted Roster history.
14. Governor Progression exposes accessible/mobile-first upload-review-preview-commit-receipt UX with localization.
15. Current-state/history read composition exposes provenance/captured dates and is integrated into Governor Progression.
16. Authorized downstream consumers use Roster reads rather than Evidence tables or Progression mutation.
17. Product/architecture/reference/operations documentation agrees with implementation.
18. Clean PostgreSQL install and applicable PHP/frontend/architecture/accessibility/visual/security/repository gates pass on one immutable candidate.

## Delivery ledger

Status values: `Planned`, `In progress`, `Complete`, `Blocked`.

| ID | Deliverable | Status |
| --- | --- | --- |
| GP-01 | Product contract, ownership and acceptance criteria | Complete |
| GP-02 | Explicit Governor Evidence kinds and schema registry | Planned |
| GP-03 | Fixture corpora for all six v1 schemas | Planned |
| GP-04 | Independent classification support | Planned |
| GP-05 | Schema-bound extraction support | Planned |
| GP-06 | Dataset-pinned normalization + canonical identity matching | Planned |
| GP-07 | Explicit Governor/Roster Evidence persistence scope | Planned |
| GP-08 | Immutable typed Governor review revisions | Planned |
| GP-09 | Exact/visual/semantic duplicate semantics | Planned |
| GP-10 | Roster append-only Governor progression observation ledger | Planned |
| GP-11 | Six Roster destination Actions + owner writer | Planned |
| GP-12 | Destination receipt/idempotency + crash recovery | Planned |
| GP-13 | Roster current-state/history query/projection | Planned |
| GP-14 | Governor Progression upload/review/preview/commit UX | Planned |
| GP-15 | Responsive/accessibility/localization/visual coverage | Planned |
| GP-16 | Audit/outbox/privacy-safe diagnostics/retention | Planned |
| GP-17 | Downstream authorized-read integration hooks | Planned |
| GP-18 | Architecture/reference/operations/current-truth reconciliation | Planned |
| GP-19 | Unit/feature/fixture/authorization/idempotency tests | Planned |
| GP-20 | Repository-wide release verification on immutable candidate | Planned |

No ledger item may be marked Complete solely because implementation code exists. Its behavior, tests and documentation must agree and applicable verification gates must pass.
