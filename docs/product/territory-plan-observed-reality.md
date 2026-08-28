# Territory Planner: Plan vs Observed Reality

Status: Implementation contract — 2026-08-27

Territory Planner: Plan vs Observed Reality extends the existing Alliance Territory & Hive Planner with an authorized, evidence-backed comparison between immutable published planning intent and dated observed spatial facts. This document is the implementation source of truth.

A delivery item is complete only when documented ownership, authorization, persistence, provenance, observation completeness, identity semantics, comparison behavior, Evidence handoff, UX, accessibility, localization, automated tests, visual proof, observability, documentation reconciliation and release gates are implemented and verified.

## Product outcome

An authorized planner can select an immutable published Territory revision and compare it with the latest authorized spatial observation, or an explicitly selected historical observation, to answer:

- which Governors are in position;
- which Governors are outside the configured position tolerance;
- which planned cities are proven missing from a sufficiently complete observation;
- which planned cities were simply not observed;
- which observed cities are unexpected;
- how far an observed city or structure moved from the published plan;
- whether planned HQ, Bear Trap and Banner positions match what was observed;
- which planned/observed Governor cities are covered or uncovered using the existing map geometry contracts;
- whether identity matching is resolved, ambiguous or unknown;
- whether the observation is fresh enough to use operationally;
- exactly which Evidence/review supports each accepted observed fact.

The planner remains desired state. Evidence and Observations represent what was seen. A screenshot, reviewed observation or reconciliation result must never silently overwrite a plan.

## Hard prohibitions

This capability must not:

- create a `TerritoryReality`, `TerritoryObservation`, `TerritoryReconciliation` or similar top-level bounded context;
- persist observed coordinates inside `Operations/TerritoryPlanning` planning tables;
- mutate an editable Territory plan because a screenshot differs from it;
- mutate or replace an immutable published Territory revision;
- allow a plan revision to rewrite an observation;
- allow raw OCR/extraction output to write observed domain truth;
- treat a partial screenshot as proof that off-screen objects are absent;
- treat `not observed` as `missing`;
- silently resolve ambiguous Governor or Banner identity;
- copy or reimplement KingdomMap placement/coverage geometry in Vue, controllers or the reconciliation read model;
- persist derived `in_position`, `missing`, `unexpected`, `uncovered`, `distance_delta`, `fresh`, `stale` or reconciliation summary flags as authoritative business state;
- compare against mutable plan-head state;
- expose another Alliance's private observations or Evidence through latest-observation discovery;
- provide an `Apply observed positions`, `Sync screenshot to plan` or equivalent overwrite action.

A later product may explicitly create a new editable draft from a reviewed observation through the normal TerritoryPlanning owner Action. That is not part of this capability.

## Capability ownership

### `Operations/TerritoryPlanning` owns desired state

TerritoryPlanning continues to own:

- editable Territory plans;
- planned Alliances and plan-local external Alliances;
- planned HQs, Banners, Governor cities and Bear Traps;
- plan-local Governor references and application-linked Player references;
- planning preferences;
- deterministic planned layout analysis;
- immutable published plan revisions and snapshots;
- plan revision dataset/checksum pins.

Only an immutable published `territory_plan_revision_id` may be a reconciliation target.

### `Intelligence/Evidence` owns source and review provenance

Evidence owns:

- the private uploaded screenshot;
- source checksum and secure-storage metadata;
- upload scanning;
- classification/extraction attempts;
- machine candidates, confidence, bounds and warnings;
- immutable review/correction revisions;
- duplicate decisions;
- commit attempts and destination receipt acknowledgement;
- retention/redaction/deletion lifecycle;
- the minimum scalar Alliance/Kingdom/map-dataset scope required to authorize and explain the destination handoff.

Evidence does not own the accepted observed spatial facts.

### `Intelligence/Observations` owns accepted observed spatial facts

Observations owns append-only spatial observation batches and their accepted observed objects.

The owner stores what was reviewed as observed at a specific time, with explicit observation coverage/completeness and provenance. It does not become a second source of Player identity, Alliance membership, map truth or planning intent.

### `GameWorld/KingdomMaps` owns map truth and geometry

KingdomMaps continues to own:

- immutable/versioned map datasets;
- coordinate bounds;
- structure/zone geometry;
- sourced placement rules;
- the geometry used for placement and territory coverage calculations.

Spatial observations pin the dataset ID/checksum against which their coordinates were reviewed. A later dataset release never rewrites an older observation or published plan revision.

### `ReadModels/TerritoryPlanning` owns comparison composition

The authorized read model composes:

`published plan revision ↔ selected/latest authorized observation ↔ pinned KingdomMap geometry`

and computes comparison state on demand. It owns no writable business aggregate.

## Authorization boundaries

Authorization occurs before protected data enters a candidate set.

### Plan side

A reconciliation request must first prove the actor may view the Territory plan/revision using the existing TerritoryPlanning Alliance/Kingdom scope rules.

### Observation side

The observation owner query must independently prove the actor may view Intelligence observations for the observation's Alliance/Kingdom scope before returning either the latest candidate or an explicitly selected observation.

A caller may not retrieve all observations and filter unauthorized rows after composition.

### Evidence side

Upload/review/commit/delete/retry must re-resolve the active Player and concrete Alliance/Kingdom spatial Evidence scope. Review authority does not imply destination write authority; the Observations destination Action reauthorizes inside its own transaction.

### Cross-context boundary

Public owner Actions accept scalar IDs/value objects only. No Eloquent model from TerritoryPlanning, Evidence, KingdomMaps or another context crosses into Observations as trusted authority state.

## Observed spatial state model

### Spatial observation batch

A `SpatialObservation` is an append-only reviewed observation event with:

- `id`;
- `alliance_id`;
- `kingdom_id`;
- `captured_at` — reviewer-confirmed in-game/source observation time;
- `recorded_at`/timestamps;
- `coverage_kind`;
- optional explicit rectangular coverage bounds when the source proves a visible region;
- `completeness`;
- pinned `kingdom_map_dataset_id` and checksum;
- `source` classification;
- optional scalar `source_evidence_id` and `source_review_id`;
- stable destination idempotency key;
- accepted actor;
- optional correction/invalidation provenance.

An observation is historical truth about what was accepted as seen; it is never updated in place to represent a newer capture.

### Observed spatial object

Each batch contains closed typed observed objects:

- `headquarters`;
- `banner`;
- `governor_city`;
- `bear_trap`.

Each object stores:

- stable observation-local key;
- object type;
- coordinate X/Y;
- optional resolved application `player_id` for Governor cities;
- optional reviewed plan-local identity key/name;
- optional observed label/name/tag text retained as observed meaning;
- identity state;
- optional source confidence/provenance metadata required to explain the reviewed mapping.

The table is not a generic field bag and cannot accept arbitrary object types.

## Coverage and completeness semantics

Absence is not evidence of absence unless the observation proves adequate coverage.

### Coverage kind

Supported v1 values are:

- `complete_hive` — reviewer confirms the supported capture represents the complete relevant hive/object set;
- `complete_visible_region` — all supported objects inside explicit visible bounds are represented, but the capture is not the complete hive;
- `partial_region` — only some objects inside/around a visible region are represented;
- `single_object` — the source proves one specific object/coordinate only;
- `unknown_coverage` — coverage cannot be safely established.

### Completeness

Supported values are:

- `complete`;
- `partial`;
- `unknown`.

`complete_hive` requires `complete`. `complete_visible_region` requires explicit valid bounds and `complete`. Partial/single/unknown coverage can never prove global absence.

### Missing versus not observed

For every planned object not matched to an observed object:

- return `missing` only when observation coverage proves that the planned coordinate/object should have been visible and the relevant object set is complete;
- otherwise return `not_observed`.

This rule is mandatory for Governor cities, HQ, Bear Trap and Banners.

## Identity matching semantics

Identity and spatial matching remain explicit.

### Governor cities

Identity states are:

- `resolved_player` — reviewed mapping to an application Player ID;
- `resolved_plan_local` — reviewed mapping to a plan-local Governor identity;
- `ambiguous` — more than one plausible identity remains;
- `unresolved` — no supported identity has been established.

Only resolved identity may produce a definitive Governor `in_position`/`out_of_position` result. Ambiguous/unresolved cities remain visible and may be reported as unmatched/identity-review-required.

Machine/OCR name similarity is never sufficient to create a Player mapping without the documented review.

### Structures

HQ and Bear Trap may match by closed object type within the relevant Alliance layer when uniqueness is established.

Banners have no assumed stable game identifier. If a stable visible identity is unavailable, the comparison service performs deterministic minimum-distance matching within the same Alliance layer, with documented maximum matching tolerance and deterministic tie handling. A tie/ambiguous assignment remains ambiguous rather than asserting movement.

## Evidence family

The v1 screenshot kind is:

`territory_map_observation`

Schema version:

`territory-map-observation/1`

The user-selected expected kind is a routing hint. Classification independently determines the supported kind and must fail closed on unsupported/mismatched screenshots.

### Fixture-proven fields

The schema may emit only:

- visible map/Kingdom context required to validate scope where fixture-proven;
- zero or more HQ coordinate candidates;
- zero or more Bear Trap coordinate candidates;
- zero or more Banner coordinate candidates;
- zero or more Governor city coordinate candidates;
- Governor/structure labels or names only where supported fixtures prove the extraction region/association;
- candidate visible-region bounds where supported;
- candidate source timestamp only where supported.

The extractor may not infer hidden/off-screen cities, Banners, Alliance membership, Player identity, coverage completeness or plan correspondence.

### Human review

Every v1 Territory spatial screenshot requires human review.

The reviewer must explicitly confirm/correct:

- captured time;
- Alliance/Kingdom scope;
- pinned map dataset;
- each included supported object and coordinate;
- Governor identity resolution where known;
- observation coverage kind;
- completeness;
- visible bounds when required;
- exclusions/unsupported candidates.

Machine extraction remains immutable provenance after correction.

### Duplicate semantics

- exact duplicate — same source binary inside the same authorized spatial Evidence scope;
- visual duplicate — advisory similarity only;
- semantic duplicate — same immutable reviewed spatial meaning/scope/capture boundary;
- destination idempotency — one stable Observations receipt per approved review.

A genuinely newer observation remains importable.

## Destination handoff

Approved Territory Evidence commits through a dedicated Observations owner Action:

`RecordSpatialObservationEvidence`

The action must:

1. reacquire current actor authority;
2. re-resolve Alliance/Kingdom scope;
3. load/validate the exact pinned KingdomMap dataset/checksum;
4. validate coordinate bounds and closed object types;
5. validate coverage/completeness/bounds combinations;
6. validate resolved Player IDs only through owner/reference queries allowed by the destination boundary;
7. append a batch and its object facts atomically;
8. enforce stable destination idempotency;
9. preserve correction/invalidation history;
10. emit audit/outbox facts;
11. return a scalar receipt to Evidence.

Evidence records the receipt but does not duplicate destination object rows.

## Observation correction and invalidation

Accepted observation rows are append-only historical facts.

A correction creates a new observation that references the corrected observation. The original remains immutable and is excluded from latest-current composition once superseded/invalidated according to owner semantics.

Invalidation records actor/time/reason and never deletes historical provenance.

Authorized observation history preserves current, corrected and invalidated historical records for explicit selection. Latest-current discovery excludes invalidated observations, but an authorized user may deliberately select an invalidated historical observation to inspect what was previously accepted. The projection exposes invalidation metadata, and the UX must label the selected record as historical/invalidated so it cannot be mistaken for current observed reality.

Deleting/redacting Evidence does not delete a committed spatial observation. Observation correction/removal uses the Observations owner workflow.

## Dataset compatibility

A published plan revision and observation each pin a KingdomMap dataset ID/checksum.

Reconciliation behavior is:

- same ID/checksum — fully comparable;
- distinct immutable releases with coordinate-system compatibility explicitly established by KingdomMaps — comparison may proceed and discloses both releases;
- incompatible/unknown coordinate systems — comparison state is `dataset_incompatible`; no misleading distance/coverage assertions are returned.

The read model never silently substitutes `latest()` for either historical pin.

## Comparison semantics

### Selected inputs

Comparison requires:

- an authorized immutable published Territory revision;
- either an explicitly selected authorized observation or the latest authorized non-invalidated observation in matching Alliance/Kingdom scope.

No observation produces `no_observation`; no published revision produces `no_published_revision`.

### Position distance

For resolved objects the service returns:

- planned X/Y;
- observed X/Y;
- `delta_x`;
- `delta_y`;
- Euclidean tile distance `sqrt(dx² + dy²)` rounded only for display, with unrounded backend comparison;
- comparison status.

The default Governor position tolerance is an explicit backend policy value. Exact coordinate equality is not hard-coded in Vue.

### Governor statuses

Supported statuses include:

- `in_position`;
- `out_of_position`;
- `missing`;
- `not_observed`;
- `unexpected`;
- `identity_ambiguous`;
- `identity_unresolved`.

### Structure statuses

Supported statuses include:

- `unchanged`;
- `moved`;
- `missing`;
- `not_observed`;
- `unexpected`;
- `ambiguous`.

### Coverage comparison

Coverage is recomputed using existing KingdomMaps/Territory geometry contracts from the planned and observed structure/object coordinates. The read model may report:

- planned covered / observed covered;
- planned covered / observed uncovered (`lost_coverage`);
- planned uncovered / observed covered (`gained_coverage`);
- uncovered in both;
- unknown where observation completeness/geometry cannot support the assertion.

No coverage result is persisted as owner truth.

## Freshness and staleness

Freshness is presentation/comparison policy derived from `captured_at`, not Evidence upload time.

The backend returns:

- observation captured time;
- age in seconds;
- `freshness_state`;
- configured freshness/staleness thresholds.

v1 states are:

- `fresh`;
- `aging`;
- `stale`.

The default thresholds are configuration-backed and tested. A stale observation remains selectable and comparable but every summary/detail exposes that it may not reflect current reality.

## Read-model response

The reconciliation projection returns at minimum:

- immutable plan revision identity/number/checksum/published time/map pin;
- observation identity/captured time/source/coverage/completeness/map pin/freshness;
- compatibility state;
- counts by discrepancy state;
- Governor comparison rows;
- structure comparison rows;
- unmatched observed objects;
- coverage deltas;
- provenance links/IDs safe for the actor;
- available authorized observation history for selection, including invalidated/corrected historical records with their invalidation metadata;
- available immutable revision history for selection;
- pinned KingdomMap coordinate-system identity and exact map bounds required to render the comparison without presentation-owned world constants.

The projection is bounded and query-budget tested.

## UX contract

The Territory Planner remains the primary surface. Add a reconciliation mode that clearly separates:

- **Plan** — desired published state;
- **Observed** — dated observed state;
- **Compare** — derived differences.

### Compare summary

The first viewport communicates:

- selected published revision;
- observation capture age/freshness;
- Governors in/out of position;
- proven missing versus not observed;
- unexpected/unresolved cities;
- HQ/Bear/Banner changes;
- uncovered/lost-coverage count;
- stale/dataset-incompatible warnings.

### Filters

Support:

- all;
- out of position;
- missing;
- not observed;
- unexpected;
- uncovered/lost coverage;
- structures changed;
- stale/uncertain/ambiguous.

### Map overlay

Compare mode may render planned objects as the desired layer and observed objects as the observed layer, with connector/delta treatment for resolved moved objects. The read model supplies the exact bounds and coordinate-system identity from the pinned KingdomMaps dataset. Vue derives the SVG/canvas view box and coordinate transform from those values; it must not hard-code map width, height, origin or Y-axis inversion constants.

The map/canvas is never the sole information surface. A synchronized semantic list/table exposes exact coordinates, status, distance, identity and provenance without color dependency.

### Evidence intake

Authorized planners can choose **Add observed map evidence** from the Territory surface, upload the supported screenshot, review extracted candidates/coverage/identity, and explicitly commit the reviewed observation. The user remains on the canonical Evidence workflow until destination acceptance succeeds.

### No overwrite control

The UI contains no control that applies observed coordinates to the current plan or published revision.

## Persistence rules

Persist only owner state:

- Evidence source/review/commit provenance in Evidence tables;
- append-only spatial observations and observed objects in Observations tables;
- existing desired state/revisions in TerritoryPlanning.

Do not persist read-model discrepancy state.

Database constraints enforce valid Evidence scope and observation coverage/completeness combinations where practical; application validation enforces the complete contract.

## Observability and audit

Record structured audit/outbox events for:

- spatial Evidence uploaded/rejected/reviewed/duplicate-resolved;
- destination commit attempted/succeeded/recovered/failed;
- spatial observation recorded/corrected/invalidated;
- no plan mutation is emitted from these workflows.

Operational diagnostics expose processing failures without leaking private screenshot payloads.

## Acceptance criteria

- **TR-01** reconciliation targets only an immutable published Territory revision, never mutable draft/head state.
- **TR-02** accepted spatial facts are append-only `Intelligence/Observations` state; Evidence owns provenance only.
- **TR-03** upload/review/commit of an observation cannot change TerritoryPlanning state.
- **TR-04** plan edit/publish/restore cannot change an existing observation.
- **TR-05** HQ, Bear Trap, Banner and Governor-city observations use closed typed semantics.
- **TR-06** observation coverage/completeness is explicit and validated.
- **TR-07** `not_observed` remains distinct from `missing`; partial/unknown coverage cannot prove absence.
- **TR-08** Governor identity preserves resolved-player, resolved-plan-local, ambiguous and unresolved states.
- **TR-09** ambiguous identity never becomes a definitive Governor position assertion.
- **TR-10** Banner spatial matching is deterministic and ambiguity-safe where no stable identifier exists.
- **TR-11** distance/tolerance logic is backend-owned and fixture tested.
- **TR-12** planned/observed coverage uses the existing KingdomMaps/Territory geometry contracts rather than duplicated frontend formulas.
- **TR-13** changed structure rows expose planned and observed coordinates plus distance.
- **TR-14** unexpected observed cities/structures remain visible and preserve unresolved identity.
- **TR-15** observation freshness derives from captured time and is always disclosed.
- **TR-16** stale observations remain usable only with explicit stale presentation semantics.
- **TR-17** plan/observation map dataset pins are preserved; incompatible pins fail closed.
- **TR-18** every accepted screenshot observation has traceable Evidence/review provenance and a stable destination receipt.
- **TR-19** raw OCR/extraction cannot directly create a spatial observation.
- **TR-20** exact/visual/semantic duplicates and destination idempotency remain distinct and tenant-safe.
- **TR-21** interrupted Evidence acknowledgement after successful destination commit recovers the existing receipt without duplicate observation rows.
- **TR-22** correction/invalidation preserves historical observation provenance, keeps invalidated records in authorized history for explicit selection, excludes them from latest-current discovery and visibly labels an explicitly selected invalidated observation as historical/invalidated.
- **TR-23** deleting/redacting Evidence does not silently delete accepted observation history.
- **TR-24** authorization occurs before plan revision, observation history or Evidence enters comparison/review candidate sets.
- **TR-25** cross-context owner Actions accept scalars/value objects and no foreign Eloquent model crosses the authority boundary.
- **TR-26** reconciliation persists no authoritative discrepancy/coverage/freshness result flags.
- **TR-27** Compare mode communicates every material discrepancy through semantic DOM, not canvas/color alone.
- **TR-28** desktop/mobile/keyboard/screen-reader UX covers aligned, drifted, partial, stale, missing, not-observed, unexpected, ambiguous and dataset-incompatible states.
- **TR-29** localization covers all new user-visible reconciliation/Evidence states.
- **TR-30** query-budget tests prevent N+1 retrieval across large hives/observation history.
- **TR-31** no screenshot-to-plan overwrite/sync action exists.
- **TR-32** product/architecture/reference/operations docs and the global delivery ledger are reconciled before closeout.
- **TR-33** PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture tests, accessibility/visual regression and applicable repository release gates pass before the capability is marked complete.
- **TR-34** the comparison projection exposes pinned KingdomMap bounds/coordinate-system metadata and every map overlay derives its view box/coordinate transform from that data; no frontend world-size/origin constant duplicates KingdomMaps truth.

## Delivery ledger

| Phase | Deliverable | State |
| --- | --- | --- |
| 0 | Canonical product contract, ownership boundaries, acceptance criteria and global-doc reconciliation | In progress |
| 1 | Spatial observation persistence, enums/value objects, constraints and owner models | Not started |
| 2 | Spatial observation authorization/write/query/correction/invalidation semantics | Not started |
| 3 | Territory spatial Evidence scope, kind/schema, upload/classification/extraction fixtures | Not started |
| 4 | Human review, coverage/completeness and identity-resolution workflow | Not started |
| 5 | Evidence → Observations idempotent commit/recovery/duplicate semantics | Not started |
| 6 | Published-revision and authorized observation owner projections | Not started |
| 7 | Deterministic identity/object matching, distance/tolerance and dataset compatibility | Not started |
| 8 | Planned-vs-observed coverage comparison and dataset-driven rendering geometry using existing KingdomMaps contracts | Not started |
| 9 | `ReadModels/TerritoryPlanning` reconciliation projection/query budgets | Not started |
| 10 | Territory Planner Plan/Observed/Compare UX, filters, semantic discrepancy list, provenance and explicit invalidated-history presentation | Not started |
| 11 | Evidence intake/review integration from Territory Planner | Not started |
| 12 | Localization, accessibility, responsive behavior and deterministic visual regression | Not started |
| 13 | Unit/feature/architecture/integration/idempotency/authorization tests | Not started |
| 14 | Documentation reconciliation, clean-database/full-release verification and ledger closeout | Not started |

The ledger is updated continuously. A phase is not complete merely because its primary class/table/page exists.
