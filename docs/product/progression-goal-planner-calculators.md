# Progression Goal Planner and Calculator Evidence Program

Status: Implementation contract — 2026-08-27

This document is the implementation source of truth for the Progression Goal Planner, calculator evidence qualification and evidence-backed progression calculators. It refines the Goal Planner (`GP-*`), calculator evidence (`CE-*`) and calculator implementation (`CI-*`) acceptance criteria in the Capability Extension Program without weakening them.

## Product outcome

A Governor can select a factual progression target, compare it with an authorized observed current state, understand the sourced progression path and prerequisites, and — only for progression families whose calculator evidence gate has passed — calculate a factual resource/time delta against an immutable dataset release.

Planning remains useful when no calculator is available. Progression distance is never converted into a resource or time estimate unless an evidence-qualified calculation rule exists for that exact family and data boundary.

The product keeps four concepts separate end to end:

1. **GameWorld progression truth** — immutable factual catalogue topology, rows, prerequisites, source/conflict records and calculator qualification owned by `GameWorld/Progression`.
2. **Observed Governor state** — dated source-labelled observations owned by `Intelligence/Roster` and authorized before retrieval.
3. **Planning intent** — the Governor's selected target/scenario. It is not an observation, recommendation or GameWorld fact.
4. **Calculation result** — a deterministic projection over a qualified immutable dataset and explicit inputs. It is not strategy advice.

## Non-negotiable rules

1. A progression goal does not require a calculator.
2. Missing, stale, unknown, unsupported and conflicting values remain explicit and never silently become zero, false or complete.
3. Current state is never inferred from the catalogue. It comes from an authorized observation or remains unknown.
4. Targets and prerequisites are resolved from one pinned immutable Progression release.
5. No UI/controller formula, cost table, interpolation, extrapolation or guessed sequence is permitted.
6. No target is labelled `best`, `optimal`, `recommended`, `priority` or automatically selected by a ranking.
7. Calculator eligibility is independent by family. Qualifying one family does not unlock another.
8. Calculator qualification is evidence, not a feature flag. The qualification report, evidence coverage, immutable dataset/checksum and golden fixtures must exist and pass tests.
9. A published dataset correction creates a new release. Existing saved scenarios never silently recalculate or change historical meaning.
10. A conflict is represented as a conflict. Values from conflicting sources are never averaged to manufacture a calculable value.
11. Every calculation result names its dataset ID/version/checksum, calculation version, applicable source IDs and assumptions/boundaries.
12. Saved planning records pin their factual/calculation release identity and preserve original inputs/results.
13. GameWorld owns factual progression/calculation rules; ReadModels authorize/compose; Vue renders and submits inputs only.
14. Cross-context writes use scalar IDs/value objects through the canonical owner action. No foreign Eloquent model is passed or mutated.
15. A family may be fully usable for factual reference while still ineligible for calculation.

## Capability ownership

### `GameWorld/Progression`

Owns:

- immutable factual progression releases and checksums;
- family/state identifiers and ordering/topology;
- explicit prerequisite relationships;
- source, version, unit, conflict and applicability metadata;
- calculator qualification reports and machine-readable family eligibility;
- normalized calculator datasets derived only from reviewed release rows;
- pure deterministic calculation services;
- calculation versions and golden fixtures.

It does not own Governor observations, Alliance authorization, saved strategy recommendations or screenshot evidence.

### `Intelligence/Roster`

Owns observed Governor progression state, capture timestamps, evidence provenance and append-only observation history. The planner consumes its authorized projection; it never writes or backfills Roster state.

### Progression planning intent

Saved goals/scenarios, when persisted, are planning intent. They may live under the existing progression/planning application boundary but must not become GameWorld facts or Roster observations. A saved scenario stores only scalar references/inputs plus immutable dataset/calculation pins and result snapshot where applicable.

### `ReadModels/Progression`

Owns cross-owner composition for the Governor-facing planner surface:

- re-acquires active Player and concrete Alliance/Roster authority;
- retrieves authorized observed current state;
- retrieves the selected immutable GameWorld dataset;
- composes target/path/prerequisite/qualification/calculation presentation;
- never persists duplicated truth;
- performs no foreign-owner mutation.

## Supported planner families

The planner initially supports factual targets for families where the committed dataset exposes deterministic state identity/order without guessing:

- Governor Gear;
- Governor Charms;
- Hero level;
- Hero Gear / Mastery where a deterministic ladder is represented;
- Academy research;
- War Academy research;
- Buildings / Truegold where deterministic levels are represented.

Troop training/promotion is primarily a calculator scenario rather than a persistent progression-state goal unless the dataset exposes an unambiguous factual tier target. Unsupported or structurally ambiguous families remain visible as unavailable rather than being synthesized.

## Progression state contract

A selectable progression state contains at minimum:

- family;
- stable subject/entity identifier;
- stable state identifier;
- human label;
- ordinal only when the source dataset proves an ordered ladder;
- source IDs;
- evidence/confidence state;
- dataset ID/version/checksum;
- applicability/version boundary when present.

`ordinal` is a navigation convenience derived from the immutable ordered dataset. It is not a resource multiplier.

A progression comparison returns:

- current state or explicit `unknown`;
- selected target state;
- ordered path including current/target when deterministically known;
- remaining transition count;
- prerequisites and their satisfaction state;
- unresolved/conflicting path facts;
- observation freshness/provenance separately from catalogue release freshness/provenance;
- calculator eligibility for the selected family.

No resource/time total is present unless the family is calculator-qualified and the user explicitly asks to calculate the selected scenario.

## Observation and provenance semantics

Current-state facts may be composed from the existing `GovernorProgressionObservationQuery` projection. Each observed fact preserves:

- value;
- capture timestamp;
- observation ID;
- Evidence/review IDs where applicable;
- pinned progression dataset ID/checksum used during normalization.

The planner must distinguish:

- `observed_current` — directly present in authorized observation history;
- `unknown_current` — no applicable observation exists;
- `stale_observation` — observation exists but is older than the configured presentation freshness threshold; stale remains usable only when the user can see its date and the operation does not claim currency;
- `dataset_mismatch` — observed normalization release and selected target release differ materially; historical observation is preserved and the UX explains the boundary;
- `conflicting_catalogue` — the target/path/prerequisite row is unresolved for the selected release;
- `unsupported` — the release cannot deterministically represent the requested transition.

Observation freshness never changes a GameWorld fact. Dataset age never changes the observation capture date.

## Prerequisite contract

Prerequisites are sourced relationships from the pinned dataset. The planner may classify each prerequisite as:

- `satisfied` — an authorized observation deterministically proves the requirement;
- `unsatisfied` — an authorized observation deterministically proves a lower/incompatible state;
- `unknown` — the required observed fact is absent;
- `conflicting` — the factual prerequisite relationship/value is unresolved;
- `not_applicable` — the dataset explicitly marks the prerequisite inapplicable for the selected subject/version.

Unknown is never treated as unsatisfied or satisfied. Missing prerequisite graph data blocks any calculation that depends on that graph.

## Dataset pinning

Transient previews use a concrete dataset selected by ID/checksum (defaulting to the latest published reviewed release). Every response returns the pin.

Persisted planning intent stores:

- `progression_dataset_id`;
- `progression_dataset_checksum`;
- canonical family/subject/current/target identifiers;
- canonical input options;
- calculation version/result snapshot when a calculator ran;
- creation/update timestamps.

Opening an older saved scenario uses its pinned dataset. If a newer release exists, the UX may offer an explicit `Recalculate with latest dataset` action that creates a new calculation revision. It must never silently overwrite the original result.

## Calculator eligibility states

Machine-readable eligibility is owned by `GameWorld/Progression` and exposed per family:

- `calculator_ready` — qualification passed and a calculation implementation/golden fixtures exist for the pinned release;
- `qualified_pending_implementation` — evidence gate passed but runtime calculation is not yet implemented;
- `evidence_review` — evidence package exists but review is incomplete;
- `evidence_incomplete` — one or more qualification conditions fail;
- `source_gap` — required source rows/units/version boundary are missing;
- `evidence_conflict` — unresolved conflict prevents safe calculation;
- `unsupported` — family has no approved calculator contract.

Eligibility includes reason codes, human-readable reasons, evidence coverage summary, dataset pin and calculation version when applicable. A boolean-only feature flag is prohibited.

## Calculator evidence qualification

Each initial family is reviewed independently:

1. Governor Gear;
2. Governor Charms;
3. Hero Gear / Mastery;
4. troop training/promotion;
5. Academy / War Academy research;
6. buildings / Truegold progression.

For each family the repository contains a qualification report that evaluates all ten gates from the Capability Extension Program:

- source URI/ID/label;
- observed date;
- game/server/version boundary where available;
- explicit input/output units;
- official inspectable table or required independent corroboration/in-game evidence;
- explicit conflicts/unknown/unlock/applicability boundaries;
- immutable schema-versioned checksummed dataset;
- typed calculation contract with no presentation formulas;
- golden fixtures for relevant boundaries;
- source/version disclosure and correction path;
- saved-scenario pinning contract;
- per-row unavailability where family qualification does not prove every row;
- machine-readable review state.

### Initial qualification disposition for dataset `2026.08.23.2`

The implementation must derive/verify these dispositions from committed evidence rather than hard-coding UI assumptions:

- **Governor Gear** — eligible for qualification because the release contains a complete 58-row Century Games/KingShot Official Wiki Tier-A table, retained superseded community conflicts and immutable source snapshot metadata. Runtime readiness still requires normalized transition semantics and golden fixtures to pass.
- **Governor Charms** — eligible for qualification because the release contains a complete 22-level Tier-A table, independent maintained corroboration, retained historical max-level conflict and immutable source snapshot metadata. Runtime readiness still requires golden fixtures to pass.
- **Hero Gear / Mastery** — remains evidence-incomplete unless the committed rows used by the intended calculator expose complete units, transition semantics and the required official/corroborated evidence package.
- **Troop training/promotion** — remains evidence-incomplete unless training vs promotion semantics, quantity units, resource/time units and applicable modifiers are independently qualified.
- **Academy / War Academy research** — remains source-gap/evidence-incomplete for any calculation whose path intersects unresolved level/prerequisite data; `Fortified Mail VI` remains an explicit source gap in `2026.08.23.2`.
- **Buildings / Truegold** — remains evidence-review/evidence-incomplete while disputed prerequisite rows or incomplete official/corroborated calculator evidence affect the intended calculation scope.

A later implementation pass may qualify additional families only by adding an inspectable evidence package and tests. Documentation assertion alone cannot change a family to `calculator_ready`.

## Calculation contract

Calculation rules live in `GameWorld/Progression` as pure deterministic operations over:

`dataset release + family + current state + target state + explicit options`

The service returns a typed result with:

- `status`: `calculated | unavailable | conflicting | invalid`;
- family/current/target IDs;
- transition IDs included;
- resource totals with explicit units;
- base duration only where the qualified dataset contains base duration;
- explicit applied modifiers/options;
- dataset ID/version/checksum;
- calculation version;
- source IDs;
- assumptions and unavailable/conflict reasons.

Rules:

1. same-state calculation returns zero for known additive resources only because there are zero transitions, not because data is missing;
2. reverse/non-forward requests are invalid unless the family explicitly defines a reversible/promotion contract;
3. multi-step results sum the exact qualified transition rows in order;
4. missing/conflicting transition rows produce `unavailable`/`conflicting`, never partial totals presented as complete;
5. units are never mixed implicitly;
6. bonuses/modifiers are accepted only when their semantics are explicitly part of the qualified family contract;
7. frontend code cannot contain factual cost tables or calculation formulas.

### Governor Gear calculation semantics

The qualified Gear calculator treats each canonical `upgradeSteps` row as the cost of entering that state from the immediately preceding state. The user selects a current canonical Gear state and a later target state. The result sums the material rows strictly after current through target.

Resources are the exact fields present in the qualified rows (currently Satin, Gilded Threads and Artisan's Vision). Cumulative stat/power fields are display deltas only and are not added as costs.

### Governor Charm calculation semantics

The qualified Charm calculator treats each `charmLevels` row as the cost of entering that level from the immediately preceding level. Current level `0` means an explicitly selected unupgraded charm, not an unknown observation. The result sums Charm Guides and Charm Designs for levels greater than current and less than or equal to target. Power/stat fields may be shown as factual deltas but are not resources.

## Saved scenarios

Saved scenarios are optional planning intent. When implemented they contain:

- owner Player ID;
- family and subject/slot reference;
- observed-current reference or explicit user-selected current state;
- target state;
- dataset ID/checksum;
- canonical calculation inputs/options;
- calculation version;
- result snapshot/status;
- user note/name;
- created/updated timestamps.

The saved record does not duplicate source tables. It references immutable release identity and stores the result snapshot required for historical stability.

## Authorization

1. The actor must have an authenticated, verified active Player.
2. Reading observed current progression additionally requires the same concrete Alliance/Roster Intelligence authority used by the existing Governor Progression surface.
3. Authorization occurs before observation retrieval.
4. A planner cannot accept arbitrary another-Alliance observation IDs and rely on post-filtering.
5. GameWorld factual catalogue/qualification data is read-only to end users.
6. Saving/deleting a goal authorizes against the owning Player at the write boundary.
7. Calculation itself does not elevate authorization; it operates only on already-authorized scalar inputs plus public factual GameWorld data.

## UX contract

The Governor Progression experience exposes a `Goal planner` surface with:

The canonical entry point is a localized, keyboard-accessible `Goal planner` action on the Governor Progression page that navigates to `/progression/governor/planner`. Opening the planner is read-only and must not mutate observations or planning intent.

1. **Current state** — observed value, source/captured date, dataset pin, and explicit unknown/stale state.
2. **Target** — selectable only from deterministic factual states in the pinned release.
3. **Path** — factual steps and prerequisites, with unknown/conflict markers.

Prerequisite behavior is evidence-bounded. A dataset row may expose direct prerequisite text exactly as sourced. The planner may classify satisfaction only when the prerequisite has a canonical identity that can be matched deterministically to an authorized observation. It must not resolve prerequisite names by fuzzy/name matching, infer transitive dependencies from prose, or turn absence into `unsatisfied`. Transitive prerequisite traversal is enabled only for datasets that publish canonical prerequisite identities/edges; otherwise the direct sourced facts remain visible with satisfaction `unknown`.
4. **Calculator status** — per-family eligibility and reason. Planning remains usable while calculation is unavailable.
5. **Calculation result** — only when `calculator_ready`, including resource totals, transition count, provenance and calculation version.

Required states include loading, no dataset, no observation, stale observation, no supported targets, invalid/reverse target, unknown prerequisite, conflict, dataset mismatch, calculator unavailable/review/source-gap/conflict, calculated result, validation failure, permission denied and responsive/mobile presentation. When no factual progression release is published, the Planner renders an explicit read-only `no_dataset` state, performs no observation retrieval or calculation, and never fabricates an empty factual dataset. The `no_dataset` state is reserved for true absence of a published release. Invalid JSON, unsupported schema, unreadable or missing required release files, checksum/integrity failures, or other published-dataset validation errors remain hard failures and must not be relabelled as dataset absence. Material meaning is not color-only. Controls are keyboard/screen-reader usable and strings are localized.

Observation freshness is presentation metadata only. The default stale threshold is 30 days and is configurable under `config/intelligence.php`; an observation older than the configured threshold is presented as `stale_observation` while retaining its original factual value, captured date, evidence provenance and dataset pin. Staleness alone must never enable or disable a calculator.

## API/application boundaries

Recommended read/application contracts:

- `ProgressionTopologyQuery` — enumerate supported family subjects/states, compare ordered states and resolve prerequisite graph from a concrete `ProgressionDataset`.
- `CalculatorEligibilityQuery` — return per-family qualification/runtime readiness from machine-readable reports.
- `ProgressionCalculator` — pure service returning a typed calculation result.
- `ProgressionPlannerController` under `ReadModels/Progression` — authorizes/composes observed state + GameWorld topology/eligibility/calculation response.

No `Calculator` top-level bounded context is created.

## Testing contract

### Planner tests

Cover:

- target enumeration/order;
- unknown current state;
- observed current state and provenance;
- same/different observation-vs-target dataset pins;
- forward path/transition count;
- invalid/reverse target;
- prerequisite satisfied/unsatisfied/unknown/conflicting classification where supported;
- authorization before retrieval;
- no recommendation semantics;
- calculator unavailable states;
- localization/accessibility/mobile/visual rendering.

### Evidence qualification tests

Cover every family report, required gate fields, source IDs, immutable dataset checksum binding, qualification independence, unresolved conflict/source-gap blocking and prohibition on enabling an unqualified family.

### Calculator golden fixtures

For each ready family cover at minimum:

- same state;
- one transition;
- multiple transitions;
- first supported state/current-zero boundary where applicable;
- final supported state;
- invalid reverse/out-of-range state;
- missing row;
- conflicting/unqualified family;
- exact resource units and totals;
- dataset/calculation provenance.

Governor Gear and Governor Charms must include hard expected totals computed from the committed qualified rows, not expectations generated by the production calculator itself.

## Acceptance criteria

The capability is complete only when all of the following are true:

- **GP-01–GP-10** from the Capability Extension Program pass.
- **CE-01–CE-06** pass for all six candidate families, including truthful failed/incomplete reports where evidence is insufficient.
- **CI-01–CI-08** pass for every family whose report is `qualified`.
- Planner capability works when every calculator is unavailable.
- No unqualified family has an enabled route/action or calculable output.
- Historical dataset/scenario pinning is verified.
- Authorization, unknown/conflict semantics, provenance, query bounds, privacy-safe diagnostics, localization/accessibility/mobile and visual coverage pass.
- PHP/unit/feature/static/architecture/frontend/visual/repository release gates are green on one immutable candidate.
- `/docs/product`, architecture documentation, tests and implementation agree.

## Delivery ledger

| Item | Required outcome | Status at contract start |
| --- | --- | --- |
| PG-01 | Canonical product/ownership/authorization/provenance contract | Complete |
| PG-02 | Dataset-pinned progression topology/state query | Not started |
| PG-03 | Authorized observed-current composition | Not started |
| PG-04 | Target/path/prerequisite planner response | Not started |
| PG-05 | Planner UX and required states | Not started |
| PG-06 | Planner acceptance/localization/accessibility/visual tests | Not started |
| CE-GEAR | Governor Gear evidence qualification report + machine-readable eligibility | Not started |
| CE-CHARM | Governor Charm evidence qualification report + machine-readable eligibility | Not started |
| CE-HERO-GEAR | Hero Gear/Mastery evidence qualification report | Not started |
| CE-TROOPS | Troop training/promotion evidence qualification report | Not started |
| CE-RESEARCH | Academy/War Academy evidence qualification report | Not started |
| CE-BUILDINGS | Buildings/Truegold evidence qualification report | Not started |
| CI-GEAR | Governor Gear pure calculator + golden fixtures + UI, if qualified | Evidence-gated |
| CI-CHARM | Governor Charm pure calculator + golden fixtures + UI, if qualified | Evidence-gated |
| CI-HERO-GEAR | Hero Gear/Mastery calculator, only if qualified | Evidence-gated |
| CI-TROOPS | Troop calculator, only if qualified | Evidence-gated |
| CI-RESEARCH | Research calculator, only if qualified | Evidence-gated |
| CI-BUILDINGS | Building calculator, only if qualified | Evidence-gated |
| PG-07 | Documentation/implementation reconciliation | Not started |
| PG-08 | Full repository verification and immutable closeout candidate | Not started |

The ledger is reconciled continuously. An evidence-incomplete family is considered honestly dispositioned only when its qualification report and machine-readable status exist, its calculator remains unavailable, and tests prove the gate cannot be bypassed.