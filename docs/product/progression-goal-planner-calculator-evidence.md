# Progression Goal Planner and Calculator Evidence Program

Status: implementation source of truth for the active Progression Goal Planner and calculator evidence program.

Baseline: this contract is reconciled from `main` at `2739a0dfc5d4b2bedb0a425021adbbb3d2397816` on 2026-08-27. Existing Factual Governor Progression and Governor Progression Screenshot Intake remain upstream capabilities; this program composes them without changing their ownership.

## Product outcome

A Governor can select a factual progression target, compare it with authorized observed current state, inspect the deterministic path and prerequisites that are actually present in one immutable `GameWorld/Progression` release, and understand whether a numeric calculator is qualified for that exact progression family.

The planner is useful without a calculator. A progression distance, number of steps, or prerequisite path MUST NOT be converted into a resource, time, currency, quantity, or efficiency total unless the exact calculator family has independently passed the evidence gate defined below.

The product MUST distinguish four meanings end to end:

1. **Canonical GameWorld fact** — what an immutable Progression release says about identities, order, levels, tiers, prerequisites, or sourced factual rows.
2. **Observed Governor state** — what an authorized dated observation says was seen for a Governor, with observation/evidence provenance.
3. **Planning intent** — the target the Governor selects for comparison. Selection does not mutate observation history or GameWorld truth.
4. **Calculated projection** — a deterministic result over a qualified calculator release. No projection exists for an unqualified family.

No planner target is labelled `best`, `optimal`, `recommended`, `priority`, or equivalent. This capability answers the user's selected scenario; it does not introduce a recommendation engine.

## Capability ownership

### `GameWorld/Progression`

Owns:

- factual progression family taxonomy;
- canonical state identities and display labels;
- ordered progression topology where the released source data proves an order;
- factual prerequisite relationships where source data proves them;
- immutable dataset identity, schema version, dataset version and checksum;
- family evidence/disposition metadata;
- calculator qualification state derived from reviewed qualification reports;
- pure calculation rules only after a family is qualified.

It does not own Governor-specific observations or planning intent.

### `Intelligence/Roster`

Owns accepted Governor progression observations created through its existing reviewed Evidence handoff. Observation facts remain append-only, dated and source-labelled. A missing observation is not a canonical default and is never silently treated as zero, false, level 0, or non-ownership.

### `Intelligence/Evidence`

Owns screenshot artifact, extraction, normalization, review and destination receipt provenance. Evidence does not become `GameWorld/Progression` truth and does not own planning targets.

### `ReadModels/Progression`

Owns authorized composition for the Governor Goal Planner. It may combine:

- the active Governor and Alliance scope;
- authorized current observation state;
- one immutable Progression dataset;
- pure Progression path/prerequisite results;
- per-family calculator qualification state.

The ReadModel does not persist progression facts, observations, goals, readiness booleans or calculator eligibility.

## Authorization boundaries

1. The active Governor is resolved through `PlayerContext`; client-provided Governor/player identifiers are not authority.
2. Alliance observation scope is reacquired through the existing Alliance membership scope and Intelligence authorization boundary before observation retrieval.
3. Unauthorized observation data MUST NOT enter the planner composition and then be filtered in presentation.
4. Canonical GameWorld Progression data may be shared according to its existing read policy, but combining it with Governor-specific current state requires the same authorization required to view that observation.
5. The planner is read-only. Any future save/update/delete workflow must use a canonical planning owner Action that reacquires authority at the write boundary and accepts scalar IDs/value objects only.

## Supported planner families

The initial factual planner supports these independently selectable families:

| Planner family | Subject scope | Current-state source | Target source |
| --- | --- | --- | --- |
| `governor_gear` | Governor Gear slot | authorized Governor Gear observation | canonical Governor Gear ladder |
| `governor_charms` | Governor Charm slot | authorized Charm observation | canonical Charm level ladder |
| `hero_level` | observed owned Hero | authorized Hero detail/roster observation | canonical Hero level range |
| `hero_gear` | observed owned Hero + gear slot | authorized Hero Gear observation | canonical Hero Gear quality/level/mastery topology where source data is complete |
| `academy_research` | Academy technology | authorized current research observation when available | canonical sourced technology/level rows |
| `buildings` | Building | authorized current building observation when available | canonical sourced building progression rows |

A family or subject with no authorized current observation is still selectable as a factual target, but the comparison state is `not_observed`; the product must not invent a current level.

Troop training/promotion is calculator-evidence scope in this release. It becomes a planner target family only when a meaningful non-quantity factual current/target contract and observation source are documented.

## Progression-state semantics

A planner state has:

- `family`;
- `subject_id` and factual subject label;
- canonical `state_id` and display label when known;
- ordinal only when the dataset proves a total order for that subject;
- dataset ID/version/checksum;
- source IDs/evidence status where available.

A comparison returns one of:

- `comparable` — current and target are in the same proven ordered topology;
- `same_state` — current and target resolve to the same canonical state;
- `not_observed` — target is valid but authorized current state is absent;
- `unknown_current` — an observation exists but cannot be mapped to a canonical state without inference;
- `unknown_target` — requested target is not canonical in the pinned release;
- `conflicting` — unresolved source conflict makes the path unsafe;
- `not_applicable` — the selected subject/family combination does not apply;
- `unsupported_topology` — factual states exist but a deterministic path/order is not proven.

For `comparable`, the planner may return a canonical path and `remaining_transitions`. It MUST NOT derive numeric costs from `remaining_transitions`.

Reverse movement is not silently treated as an upgrade. If a family supports only forward factual progression, a target below current returns `unsupported_direction` or an explicitly documented downgrade semantic.

## Observation and provenance rules

Every current-state fact exposed by the planner retains, when available:

- value;
- captured timestamp;
- observation ID;
- Evidence ID/review ID;
- dataset ID/checksum against which the observation was normalized.

The planner separately exposes the active catalogue dataset ID/version/checksum and release `observed_at`. Observation freshness and catalogue freshness are distinct concepts in UX.

An observation normalized against an older dataset is not rewritten. If it can be mapped safely to the active dataset through an explicitly proven stable canonical identity, the composition may show that mapping while retaining the original observation dataset identity. Otherwise the state is `unknown_current`/`dataset_mismatch` and the user must obtain/review newer evidence.

## Prerequisite behavior

Prerequisites are factual relationships from the pinned Progression release only. Each prerequisite is evaluated as one of:

- `satisfied`;
- `unsatisfied`;
- `unknown`;
- `not_observed`;
- `not_applicable`;
- `conflicting`.

Missing observed state never satisfies a prerequisite. Unknown is not false and is not zero. A prerequisite may be displayed even when it cannot be evaluated against the Governor's observations.

A conflict or missing factual row blocks any calculator result that depends on that row; the planner may still show the known portion of the factual path and identify the blocking state.

## Dataset pinning and integrity

The active planner preview is pinned to one published immutable Progression release:

- `dataset_id`;
- `dataset_version`;
- `schema_version`;
- `dataset_checksum`;
- `observed_at`.

Historical releases are retained and immutable. A source correction creates a new release/checksum.

**Absence and corruption are different states.** If no published Progression release exists, planner UI may render an explicit `no_dataset` unavailable state. Invalid JSON, unreadable files, unsupported schema, missing required release files, manifest failure, source-lock failure or checksum/integrity failure is a hard data-integrity error and MUST NOT be caught and presented as `no_dataset`.

## Planner persistence rules

The first delivered Goal Planner is a read-only preview capability. This release does **not** introduce a new `ProgressionGoal`, `Scenario` or calculator persistence model merely to satisfy the planner.

If saved goals/scenarios are introduced later, the canonical write must represent planning intent and persist at minimum:

- owner Governor/player identity through the authorized owner boundary;
- family and subject canonical IDs;
- target canonical state ID;
- pinned dataset ID/checksum;
- user notes/metadata;
- created/updated timestamps.

A saved calculation additionally pins:

- canonical calculation inputs;
- calculator family;
- calculator version;
- qualified dataset ID/checksum;
- canonical result;
- calculated timestamp.

A corrected dataset never silently changes an old saved goal/calculation. Recalculation creates a new revision/result and preserves the historical pinned result.

## Goal Planner query contract

`GameWorld/Progression` provides pure dataset-driven query operations equivalent to:

- list planner families and subjects;
- enumerate canonical target states;
- resolve a canonical state;
- compare current and target states;
- return the deterministic path when provable;
- return factual prerequisites and family evidence/conflict metadata.

The query accepts a concrete `ProgressionDataset`; it does not read Governor models or authorize users.

`ReadModels/Progression` maps the already-authorized observation projection into the query's immutable current-state input and returns presentation-ready planner data.

## UX contract

The Governor Progression surface adds a Goal Planner with four explicit layers:

1. **Current state** — source-labelled observed fact, capture time and observation provenance. If unavailable, show `Not observed`; do not prefill a catalogue default.
2. **Target** — user selects family, subject and target only from canonical states in the active dataset.
3. **Factual path** — current → target path/transition count/prerequisites where provable, with explicit unknown/conflicting/unsupported states and pinned dataset identity.
4. **Calculator status** — subordinate family qualification state. If not `calculator_ready`, explain that progression planning remains available but resource/time totals are not supported.

Required UX states:

- loading/busy;
- no published dataset;
- dataset integrity failure (normal error boundary, not `no_dataset`);
- no observation permission;
- no current observation;
- stale/current observation provenance;
- unknown/unmappable current state;
- target not applicable;
- conflicting/partial factual topology;
- calculator ready;
- evidence incomplete;
- source gap;
- evidence conflict;
- evidence review;
- disabled;
- mobile, keyboard and screen-reader compatible presentation;
- localized user-facing strings.

No formulas, costs, ordering policy or eligibility decisions live in Vue.

## Calculator Evidence Program

Calculator qualification is independent per family. Factual-reference completeness does not qualify calculation.

### Machine-readable eligibility states

Every family has exactly one reviewed status:

- `calculator_ready` — all evidence gates passed and a calculator implementation with golden fixtures exists;
- `evidence_incomplete` — factual corpus exists but one or more evidence/fixture/assumption gates are incomplete;
- `source_gap` — required factual row/semantic is not inspectably sourced;
- `evidence_conflict` — unresolved material conflict blocks safe calculation;
- `evidence_review` — evidence corpus is assembled but review/qualification has not passed;
- `disabled` — intentionally unavailable for a documented product/safety reason.

Runtime eligibility derives from a reviewed qualification report; it is not a hidden feature toggle or one global calculators switch.

### Required qualification report

Each calculator family report contains:

- family ID and review status;
- evaluated Progression dataset ID/version/checksum;
- source URIs/source IDs;
- source labels, authority tier, observed/retrieved dates;
- game/server/version/applicability boundaries when known;
- explicit units;
- normalized input/output row coverage;
- official evidence or independent corroboration + reviewed in-game evidence where required;
- conflicts and their disposition;
- source gaps and non-applicability;
- immutable calculator dataset/release identity when qualified;
- calculation version when qualified;
- golden fixture inventory when qualified;
- pass/fail result for every gate below;
- reviewer decision/reason.

### Qualification gates

A family may become `calculator_ready` only when all are true:

1. **Source coverage** — every calculation row/input/output has inspectable provenance and units.
2. **Authority/corroboration** — official inspectable data or sufficient independent corroboration and reviewed in-game evidence where required.
3. **Boundary clarity** — version/server/unlock/modifier applicability is explicit; unsupported modifiers remain unsupported.
4. **Conflict closure** — material conflicts are resolved by evidence/boundary, not averaged or silently discarded.
5. **Immutable release** — normalized calculator data is schema-versioned, checksummed and retained when superseded.
6. **Pure typed calculation** — formulas/rules live under `GameWorld/Progression`, consume the qualified release and contain no Vue/controller hard-coded tables.
7. **Golden fixtures** — representative single-step, multi-step/range, same-state, invalid direction, missing row, conflict, unit, large-value/overflow and family-specific modifier cases pass.
8. **Provenance result** — result includes dataset/checksum/calculation version/source/assumption metadata.
9. **Historical stability** — saved results, if supported, remain pinned and are never silently rewritten.
10. **Independent unlock** — qualifying one family cannot enable another.

Unknown values never mean zero. A report cannot pass by documentation assertion alone.

## Current family evidence disposition to implement

The active 2026-08-23 v2 factual corpus is strong enough for planner topology/reference use, but the separate calculator gate remains conservative until qualification artifacts and golden fixtures exist.

| Calculator family | Required runtime status | Rationale at contract baseline |
| --- | --- | --- |
| `governor_gear` | `evidence_review` | 58 official rows are canonicalized and prior community disagreements are preserved/resolved, but calculator-specific golden fixtures and qualification review are not yet delivered. |
| `governor_charms` | `evidence_review` | 22 levels are reconciled against official/current sources with superseded conflict history, but calculator-specific qualification artifacts/fixtures are not yet delivered. |
| `hero_gear` | `evidence_incomplete` | factual quality/mastery tables exist, but calculator row coverage/applicability and fixtures must be proven as a calculator package. |
| `troop_training_promotion` | `source_gap` | troop tier cost/time rows exist, but promotion-vs-fresh-training semantics and required modifiers are not yet proven as a complete calculator contract. |
| `research` | `source_gap` | Academy/War Academy data is broad, but Fortified Mail VI has an explicit six-row source-table gap and calculator modifier/fixture qualification is incomplete. |
| `buildings` | `evidence_review` | building/Truegold tables exist, while documented early prerequisite inconsistencies and calculator fixtures still require review. |

These states may improve only by adding evidence artifacts/tests that satisfy the gates. They may regress if new conflicts are found.

## Calculator implementation contract

Only a `calculator_ready` family gets a runtime calculation action.

The calculation API is conceptually:

`calculate(qualifiedDatasetRelease, currentState, targetState, explicitOptions) -> CalculationResult`

Properties:

- deterministic and side-effect free;
- dataset/checksum/calculation-version pinned;
- canonical IDs/units only;
- explicit options only; no hidden Governor/VIP/event/research-speed/etc. modifier;
- unsupported modifier returns validation/unavailable rather than inference;
- same input + same release + same calculator version returns the same canonical result;
- unavailable/conflicting input returns an unavailable/conflicting typed result, not a partial guessed total.

No calculator route/action/component is added for a family whose report is not `calculator_ready`.

## Acceptance criteria

### Planner

- **PGP-01** Planner uses current `main` architecture: `GameWorld/Progression` owns factual query logic; `ReadModels/Progression` authorizes/composes.
- **PGP-02** Authorized observed current state is never replaced by a catalogue default; missing current state is explicit.
- **PGP-03** Supported targets are enumerated from the pinned immutable dataset.
- **PGP-04** Deterministic path and transition count are returned only for proven ordered topology.
- **PGP-05** Prerequisites use `satisfied`/`unsatisfied`/`unknown`/`not_observed`/`not_applicable`/`conflicting` semantics.
- **PGP-06** Planner returns dataset ID/version/checksum and observation provenance/freshness independently.
- **PGP-07** Planner never derives resource/time totals from progression distance.
- **PGP-08** No recommendation semantics or automatic target ranking is introduced.
- **PGP-09** No new persistence model is introduced in this release; future persistence rules above are enforced by tests if persistence is later added.
- **PGP-10** No-dataset is distinct from data-integrity failure.
- **PGP-11** Governor Progression UX exposes family/subject/current/target/path/prerequisite/calculator state with explicit unknown/permission/conflict states, localization and accessible responsive behavior.
- **PGP-12** Route/controller/read-model/query boundaries have automated functional/architecture coverage and visual regression coverage.

### Calculator evidence

- **PCE-01** all six calculator families have a machine-readable reviewed qualification report.
- **PCE-02** every report identifies the evaluated dataset/checksum and pass/fail result for all ten gates.
- **PCE-03** runtime eligibility is independent per family and derives from qualification report state.
- **PCE-04** no unqualified family exposes a calculation route/action or numeric derived result.
- **PCE-05** source gaps/conflicts remain explicit and cannot be converted to zero or averaged.
- **PCE-06** a family becomes `calculator_ready` only together with immutable calculator data, pure typed calculation code and passing golden fixtures.
- **PCE-07** qualification changes produce a new reviewed artifact/release; historical results are not silently mutated.

## Delivery ledger

A row is complete only when code, tests, UX where applicable and this contract agree.

| ID | Deliverable | Baseline finding | Required closeout state |
| --- | --- | --- | --- |
| PGP-D01 | Product contract/ownership/auth/semantics | prior extension contract existed but was too high level for implementation closeout | Complete — this document is canonical and global ledger links to it |
| PGP-D02 | Immutable Progression dataset integrity/pinning | existing v1/v2 loader/checksum/source-lock infrastructure | Complete after absence-vs-corruption behavior is explicitly enforced/tested |
| PGP-D03 | Family/subject/target topology query | factual family/data queries exist; Goal Planner topology query missing | Implement and test pure dataset-driven planner query |
| PGP-D04 | Prerequisite/path comparison | not exposed as Goal Planner contract | Implement typed path/comparison/prerequisite semantics without formulas |
| PGP-D05 | Authorized observation composition | Governor ReadModel already composes authorized observation projection | Extend it to map observation state into planner current-state inputs without changing owner truth |
| PGP-D06 | Planner backend presentation contract | missing | Add planner payload to Governor Progression ReadModel/controller |
| PGP-D07 | Planner UX | missing from current Governor page | Add accessible localized planner section and explicit unavailable/conflict states |
| PGP-D08 | Planner persistence | no canonical goal owner currently required | Complete by deliberate non-persistence in this release; no placeholder model/table/API |
| PGP-D09 | Calculator qualification artifacts | no complete six-family runtime report contract | Add machine-readable reviewed reports for all six families |
| PGP-D10 | Calculator runtime eligibility registry | no explicit per-family runtime contract | Add typed/queryable per-family eligibility derived from reports |
| PGP-D11 | Evidence-backed calculators | no family qualified at baseline | Implement only families that pass PGP-D09/PCE gates; otherwise truthful explicit unavailable state is the correct delivered behavior |
| PGP-D12 | Automated verification | existing Progression tests/visual baseline do not prove full planner/evidence contract | Add functional/domain/architecture/frontend/visual coverage and run repository gates on one immutable candidate |
| PGP-D13 | Documentation reconciliation | global ledger still marks planner not started/gate not executed | Reconcile global ledger/catalogue/extension contract after implementation evidence is known |

## Definition of done

This program is complete when:

1. the planner is fully usable for every planner family whose factual topology can be truthfully represented by the active dataset, with explicit unsupported states elsewhere;
2. current observation, target, path/prerequisite semantics and dataset/provenance boundaries are enforced in code and tests;
3. all six calculator families have a reviewed machine-readable qualification result;
4. every family is either `calculator_ready` with a fully tested evidence-backed calculator, or explicitly unavailable with one of the non-ready statuses and concrete gate failures;
5. no speculative formula, hidden modifier, inferred zero or recommendation semantics exists;
6. `/docs/product` and runtime behavior agree, including the global delivery ledger;
7. all repository-required automated/architecture/static/security/frontend/visual/release gates pass on one immutable final candidate.
