# Capability Extension Program

Status: Active extension program — phases 0–4 complete — 2026-08-25

Date: 2026-08-24

This document is the product implementation contract for the capability-extension program. It starts from the complete capabilities already present on `main` and extends them through their existing owners. It is not permission to create a parallel KingShot knowledge store, a generic OCR domain, a generic Event-readiness domain, an Assistant write path, or a second copy of existing operational truth.

A delivery item is complete only when its domain/application behavior, persistence where applicable, authorization, idempotency/concurrency where applicable, provenance, audit/observability, recovery, frontend UX, mobile behavior, accessibility, localization, automated tests, architecture enforcement, visual regression where applicable, documentation reconciliation and repository release gates are complete.

`/docs/product` is the implementation source of truth for this program. If implementation reveals a missing requirement, edge case, ownership error or better product behavior, this document and the related product documents must be updated in the same change before the implementation is considered complete.

## Program objective

Use the strong owner capabilities already delivered in GameWorld, Operations, Alliance, Intelligence, Communications, Platform and ReadModels to make the product more connected and useful without duplicating state or weakening provenance.

The program extensions are listed below. Alliance Assistant Game Data and bounded operational-self queries plus Event Readiness & Closeout are now **Current complete capabilities**; the remaining items retain the Selected extension or Evidence-gated extension state recorded in the delivery ledger:

1. Alliance Assistant Game Data and bounded operational-self queries;
2. Event Readiness and Event Closeout composition;
3. typed Screenshot Intake for Kingdom Transfer evidence;
4. typed Screenshot Intake for observed Governor progression;
5. factual Progression Goal Planning;
6. per-family calculator evidence qualification;
7. evidence-backed calculators only for qualified families;
8. Territory plan versus observed-state reconciliation;
9. deterministic Intelligence change signals.

## Capability-status taxonomy

The product catalogue and gap analysis use exactly these program states:

- **Current complete capability** — implemented current product behavior. A later extension does not make the existing capability incomplete.
- **Selected extension** — approved work in this program with a defined owner, product outcome, acceptance criteria and delivery-ledger row. It is not implemented until its row is closed.
- **Evidence-gated extension** — product demand is recognized, but implementation that would calculate or assert numeric/game-rule truth is prohibited until the documented evidence gate passes for the specific family/rule. Evidence work itself may proceed.

Do not use `planned`, `partial`, `MVP`, or vague future-enhancement language as substitutes for these states.

## Non-negotiable ownership and provenance rules

### Existing owners remain owners

| Fact / behavior | Canonical owner | Extension behavior |
| --- | --- | --- |
| immutable factual progression datasets, source manifests, conflicts and row confidence | `GameWorld/Progression` | Assistant and planners read owner projections; no copied knowledge store |
| Kingdom Transfer windows, groups, target conditions, transfer observations and derived eligibility | `GameWorld/KingdomTransfers` | reviewed Evidence may commit typed observations through the owner Action |
| map datasets and sourced placement rules | `GameWorld/KingdomMaps` | observed spatial evidence never rewrites map truth |
| Event/occurrence identity and schedule | `Operations/Events` | Readiness/Closeout compose owner state |
| registration, response, waitlist and attendance | `Operations/Participation` | Assistant self queries and Event composition use owner projections |
| rosters | `Operations/Rosters` | Assistant may read active-Governor state only; writes stay in owner workflow |
| objectives and assignments | `Operations/BattlePlans` | Assistant self queries and readiness composition only |
| rally plans and recorded rally participation | `Operations/Rallies` | readiness/closeout composition only |
| Event results and accepted Bear Hunt reports | `Operations/Results` | closeout composition only |
| territory planning intent and immutable published revisions | `Operations/TerritoryPlanning` | observed-state comparison never mutates the published plan |
| published Alliance guides/strategy | `Alliance/Content` | strategy remains explicitly Alliance-authored, never promoted to game fact |
| uploaded binaries, OCR/extraction attempts, confidence, human review, duplicate decisions and commit receipts | `Intelligence/Evidence` | expands to typed Transfer and Governor-progression evidence; never owns destination facts |
| observed Governor progression history | `Intelligence/Roster` | reviewed progression evidence appends observations pinned to a Progression release |
| Alliance/Kingdom observations and historical intelligence | `Intelligence/Observations` and applicable Intelligence owners | change signals derive from owner history; no copied intelligence store |
| delivery preferences, provider delivery and retry state | `Communications` | Event readiness can display delivery state but does not own it |
| cross-context user-facing composition | `app/ReadModels/*` | composition only; never a write owner |
| Alliance Assistant interpretation/evidence composition | `app/ReadModels/AllianceAssistant` | bounded intents only; still zero direct domain writes |

### Evidence is not destination truth

`Intelligence/Evidence` owns what was uploaded, extracted, reviewed and committed. It does not become the owner of the meaning produced by an accepted review.

Required flow for every new evidence type:

```text
private source artifact
 -> immutable Evidence provenance
 -> versioned classification/extraction attempt
 -> field-level candidates + confidence
 -> human review/correction/exclusion
 -> typed reviewed meaning
 -> destination owner Action using scalar IDs/value objects
 -> destination owner reauthorizes and validates
 -> idempotent destination receipt
 -> Evidence records receipt
```

A provider/OCR result cannot directly update `GameWorld`, `Operations`, `Alliance`, `Players`, or `Intelligence/Roster` models.

### Source classifications remain distinct

The program preserves at least these semantics:

- **Game data** — source-backed `GameWorld` fact from an immutable/versioned owner dataset or authoritative owner record;
- **Operational fact** — current application state from Operations or another application owner;
- **Alliance strategy** — Alliance-authored guidance, plan or recommendation;
- **Observation** — dated observed intelligence or Governor state;
- **Evidence** — provenance supporting a reviewed observation/domain command; evidence is not automatically a game fact.

Missing remains unknown. Conflicting remains conflicting. Stale remains stale. An extension must not manufacture certainty to make an answer, readiness card, eligibility decision, calculator or comparison look complete.

### Authorization before retrieval or commit

Every extension must resolve the active Governor and concrete scope before owner data enters a cross-context candidate set. Global retrieval followed by filtering is prohibited.

Every material write must reacquire current authority inside the destination owner boundary. Evidence review authority does not imply destination write authority. Assistant read authority never implies mutation authority.

### No new top-level context for composition

Do not create top-level bounded contexts named `EventReadiness`, `EventCloseout`, `AssistantKnowledge`, `TransferOCR`, `ProgressionOCR`, `Calculator`, `TerritoryReality`, or similar when the behavior is composition or an extension of an existing owner.

A new owner context is justified only if implementation proves a genuinely new source of business truth that cannot belong to the owners above; that architectural change requires `/docs/architecture` and an ADR before implementation proceeds.

## Current complete capability — Alliance Assistant: Game Data and operational self

Program delivery: **Complete — phases 1–2**. The canonical delivered contract and detailed completion ledger live in [Alliance Assistant — GameWorld Extension](alliance-assistant-gameworld-extension.md).

### Outcome

An authenticated Governor can ask bounded questions about source-backed KingShot progression facts and their own authorized operational state. The Assistant continues to answer only from owner data and cites every substantive answer.

### Initial bounded intents

#### `game_fact`

Examples:

- What generation is Amadeus?
- What troop class is Zoe?
- What is the max Widget level?
- What does Governor Gear Mythic 2 require?
- What are the stats for this troop tier?
- What does this Academy research level do?

Source: `GameWorld/Progression` owner queries against a concrete immutable dataset release.

Required response provenance includes the dataset identity/version, source IDs supporting the selected row, confidence/evidence status and canonical Progression route where available. Unknown/conflicting rows produce unknown/conflicting answers rather than model inference.

#### `event_participation_self`

Examples:

- Did I register for Swordland?
- What did I RSVP for this week?
- Am I waitlisted?

Source: authorized `Operations/Participation` projections for the active Governor only.

#### `battle_plan_self`

Examples:

- What is my Swordland assignment?
- Which objective am I assigned to?
- What team am I on?

Source: authorized Event plus `Operations/BattlePlans` projection for the active Governor only.

#### `transfer_status_self`

Examples:

- Can I transfer to Kingdom 123?
- What am I missing for transfer?

Source: `GameWorld/KingdomTransfers` only when the active Governor is legitimately in an authorized transfer participant/read scope. The Assistant uses the owner-derived assessment and must preserve `needs_verification`, stale, conflicting and evidence-gated semantics.

#### `territory_plan`

Examples:

- Which hive layout are we using for Bear Hunt?
- Which published territory plan is linked to Swordland?

Source: the authorized Event/Operations reference to an immutable published `Operations/TerritoryPlanning` revision. It does not expose an unreferenced private plan merely because one exists.

### Write-like questions

Write-like prompts still perform no Assistant mutation. When the interpreter can safely identify a canonical owner workflow, it may return an authorized/navigation-safe handoff such as **Open Swordland roster**. The destination workflow performs its normal authorization, validation, concurrency/idempotency, audit and recovery behavior.

The Assistant must not silently submit a form, call an owner Action, create an Assistant-specific command bus, or encode a privileged actor identity in a handoff.

### Acceptance criteria — Assistant extension

- **AE-01** `game_fact` reads only from an immutable `GameWorld/Progression` release and returns `game_fact` provenance.
- **AE-02** every substantive Game Data answer exposes supporting source IDs and dataset identity/checksum/version metadata sufficient to trace the fact.
- **AE-03** unknown, missing, superseded and conflicting Progression rows do not become asserted facts.
- **AE-04** self intents never expose another Governor's Participation, roster or BattlePlan state.
- **AE-05** Transfer answers use the owner-derived assessment and preserve stale/missing/conflicting/non-authoritative requirement states.
- **AE-06** territory-plan answers expose only the immutable published revision authorized and applicable to the resolved workflow.
- **AE-07** authorization occurs before retrieval for every new owner query.
- **AE-08** write-like questions perform zero mutation; optional handoffs navigate to the canonical owner workflow only.
- **AE-09** all new intents retain bounded ambiguity/not-found/unavailable behavior and dedicated query/result limits.
- **AE-10** citations are server-built from the exact evidence set; no free-form component may invent source IDs, routes or classifications.
- **AE-11** localized, keyboard/mobile-safe UI covers answered, ambiguous, not-found, unsupported, stale/conflicting and handoff states.
- **AE-12** behavior, authorization/tenant isolation, architecture, query budget, localization and visual regression are covered before closeout.

## Current complete capability — Event Readiness and Closeout

Program delivery: **Complete — phases 3–4**. The canonical delivered contract and completion ledger live in [Event Command — Readiness & Closeout](event-readiness-closeout.md).

### Outcome

An Event coordinator gets one decision surface that explains what remains before an Event is operationally ready and what remains after an Event before closeout is complete. It composes existing owner state and deep-links to owner workflows; it does not create a second Event state machine.

### Pre-Event readiness dimensions

Where applicable to the Event's enabled capabilities, compose:

- occurrence schedule/cancellation/time-zone validity from Events;
- registration/response/capacity/waitlist state from Participation;
- roster coverage and unassigned/declined state from Rosters;
- unresolved polls where a poll is part of planning;
- objective/team/assignment completeness from BattlePlans;
- Event-linked published Alliance guidance and freshness from Alliance/Content;
- referenced published Territory revision and revision validity from TerritoryPlanning;
- reminder/announcement delivery readiness and actionable failures from Communications;
- required Rally plan coverage from Rallies.

### Post-Event closeout dimensions

Compose, where applicable:

- attendance recording incomplete;
- recorded Rally participation incomplete;
- Results missing/incomplete;
- evidence still processing or awaiting review;
- unmatched Governor review;
- failed/recoverable Evidence destination commit;
- unresolved Results correction;
- Debrief availability.

### Derived presentation lifecycle

The read model may expose a derived presentation state equivalent to:

`planning | needs_attention | ready | active | closeout_required | complete`

This is not persisted as authoritative Event state. Each dimension retains its owner/source and a canonical action link.

### Acceptance criteria — Event composition

- **ER-01** readiness/closeout is derived from owner projections; no persisted aggregate `event_ready`/`event_complete` boolean is introduced.
- **ER-02** dimensions are capability-aware so an Event is not penalized for a workflow it does not use.
- **ER-03** every blocking/warning item identifies the owner source and canonical workflow for correction.
- **ER-04** missing is distinct from zero/complete; unknown owner state cannot silently satisfy readiness.
- **ER-05** historical/published Territory references remain immutable; a newer draft never changes the Event's referenced revision.
- **ER-06** Content strategy is labelled Alliance strategy, not game truth.
- **ER-07** Communications queued/sent/failed state remains delivery-owner truth; readiness does not equate queued with delivered.
- **ER-08** pre-Event and post-Event projections are authorized before retrieval and tenant-safe.
- **ER-09** the surface remains read/composition only; every mutation goes through the existing owner Action.
- **ER-10** Command Overview may consume the same bounded projection without copying business state.
- **ER-11** desktop/mobile/keyboard/screen-reader UX exposes the primary blockers without requiring a wide table or color-only coding.
- **ER-12** owner behavior, composition, authorization, missing-data semantics, query budgets, localization and visual regression are verified.

## Selected extension 3 — Kingdom Transfer Screenshot Intake

### Outcome

An authorized Transfer manager can attach a supported in-game screenshot to a Transfer participant/window, review extracted facts and commit approved observations into `GameWorld/KingdomTransfers` exactly once with immutable Evidence provenance.

### Initial evidence classes

The implementation may introduce narrowly typed schemas for supported fixture-proven screens such as:

- Governor Transfer status/score/pass screen;
- target Kingdom Transfer condition/rules screen;
- invitation status screen;
- official Transfer Group screen.

A schema may extract only fields visibly proven by maintained fixtures. Unsupported or ambiguous material remains review-only/unsupported rather than guessed.

### Destination rules

Reviewed meaning may produce only existing or explicitly documented `KingdomTransfers` owner facts/observations. The destination owner reauthorizes the actor/scope, validates Transfer Window/participant/target relationships, applies freshness/validity/source rules, enforces deterministic fingerprints/idempotency and reevaluates eligibility through the existing deterministic evaluator.

### Acceptance criteria — Transfer evidence

- **TE-01** each screenshot class has a stable schema/extractor version, fixtures and supported-field list.
- **TE-02** raw OCR/extraction never directly writes Transfer models.
- **TE-03** approved Evidence commits typed scalar/value-object meaning through a `GameWorld/KingdomTransfers` owner Action.
- **TE-04** destination authority and scope are reacquired at commit; review authority cannot bypass Transfer management rules.
- **TE-05** source/observed/validity semantics required by Transfer observations are preserved and visible.
- **TE-06** duplicate/retry behavior cannot create duplicate observations or leak cross-Alliance evidence.
- **TE-07** destination commit followed by interrupted acknowledgement is recoverable using a stable idempotency identity and existing destination receipt.
- **TE-08** eligibility changes only because owner observations changed; Evidence never stores an eligible boolean.
- **TE-09** unsupported Transfer Pass formulas remain evidence-gated; screenshots may record observed required-pass counts but do not invent the formula.
- **TE-10** review/correction UX is localized, responsive, accessible and exposes field confidence/source provenance.
- **TE-11** retention/deletion of Evidence does not silently delete accepted Transfer observations required for historical explanation; destination correction uses the owner workflow.
- **TE-12** authorization, idempotency, duplicate isolation, crash recovery, eligibility reevaluation, audit/observability and visual coverage are verified.

## Selected extension 4 — Governor Progression Screenshot Intake

### Outcome

An authorized Governor/officer can review supported profile/progression screenshots and append source-labelled Governor progression observations into `Intelligence/Roster`, normalized against a pinned immutable `GameWorld/Progression` release.

### Initial evidence classes

Implement only fixture-proven narrow schemas, starting with the smallest useful set:

- Governor profile/progression summary;
- Hero roster;
- Hero detail;
- Hero Gear/Widget detail;
- Governor Gear/Charm detail when the visible evidence can be mapped without inference.

### Normalization

Evidence retains raw observed text and field confidence. Review resolves recognized factual identities to canonical Progression IDs using a concrete release. A normalized observation pins dataset identity/checksum. Later dataset releases never rewrite the historical observation.

OCR text cannot create, rename or mutate Player/Hero catalogue identity.

### Acceptance criteria — Governor progression evidence

- **GE-01** supported screenshot classes have versioned schemas, maintained fixtures and explicit unsupported fields.
- **GE-02** normalization uses a concrete immutable Progression release and records its identity/checksum.
- **GE-03** ambiguous identity matches block normalization/commit until reviewed; fuzzy matching cannot overwrite identity.
- **GE-04** approved reviewed meaning appends through the existing `Intelligence/Roster` owner boundary; Evidence never writes roster models directly.
- **GE-05** observations remain append-only and dated; absence from a screenshot is not treated as non-ownership unless the schema proves a complete capture.
- **GE-06** machine extraction history and human corrections remain distinct and auditable until retention boundaries.
- **GE-07** duplicate/retry handling is tenant-safe and idempotent.
- **GE-08** an Evidence retry after destination success recovers the same observation receipt/identity instead of appending a duplicate.
- **GE-09** deletion/redaction of Evidence never silently rewrites committed observation history; retained provenance follows the documented retention boundary.
- **GE-10** UX exposes confidence, matched canonical identity, pinned dataset and unknown/unresolved states without color-only semantics.
- **GE-11** no screenshot-derived field is promoted to `GameWorld/Progression` catalogue truth.
- **GE-12** authorization, history semantics, dataset pinning, duplicate isolation, recovery, audit/observability and visual coverage are verified.

## Selected extension 5 — Progression Goal Planner

### Outcome

A Governor can compare a current observed progression state with a user-selected factual target using an immutable Progression release, without the product claiming a recommendation or numeric cost formula that has not passed its evidence gate.

The planner may expose:

- current observed state and observation freshness/source;
- selected factual target entity/level/tier;
- factual prerequisite path already present in the dataset;
- count/list of factual progression steps between current and target when deterministically knowable from the catalogue;
- unknown/conflicting prerequisite rows;
- dataset identity/version/checksum;
- user planning notes/intent where saved by an existing appropriate planning owner.

It must not label a target `best`, `optimal`, `recommended`, or automatically selected unless a separate future recommendation product contract is approved.

### Acceptance criteria — Goal Planner

- **GP-01** current state comes from authorized observed Governor progression, never inferred from catalogue defaults.
- **GP-02** targets/prerequisites come from one pinned immutable Progression release.
- **GP-03** missing/conflicting factual steps remain visible and block any calculation that depends on them.
- **GP-04** the planner can show factual step differences without presenting resource/time totals when those formulas are not qualified.
- **GP-05** user-selected goals are planning intent, not observations or GameWorld truth.
- **GP-06** a saved goal/scenario pins its dataset identity so later source corrections cannot silently rewrite historical meaning.
- **GP-07** no recommendation score/ranking is introduced.
- **GP-08** cross-owner reads are composed through ReadModels; owner writes remain in their existing contexts.
- **GP-09** UX distinguishes observation freshness from catalogue release freshness/confidence.
- **GP-10** authorization, unknown/conflict semantics, dataset pinning, localization/accessibility/mobile and visual coverage are verified.

## Evidence-gated extension 6 — Calculator evidence qualification

Calculator demand is accepted, but numeric calculators remain prohibited per family until that family passes this gate. Factual-reference completeness alone does not qualify a calculator.

### Per-family qualification gate

For each calculator family:

1. every input/output row has source URI/source ID, source label, `observed_at`, game/server/version boundary when available and explicit unit;
2. values are from an official inspectable table **or** are reconciled across at least two independent inspectable sources plus reviewed in-game evidence where the existing product evidence policy requires it;
3. disagreements, regional/version differences, unlock conditions and unknown values are explicit;
4. the released calculator dataset is immutable, schema-versioned, checksummed and retained when superseded;
5. calculation code consumes typed owner data and contains no hard-coded Vue/controller cost tables or formulas;
6. golden fixtures cover single-step, multi-step/range, promotion, bonus, rounding and unavailable/conflicting-data boundaries relevant to the family;
7. user-facing output displays dataset version/source/observation boundary, assumptions and a correction/report path;
8. saved scenarios pin dataset identity/checksum and calculation version;
9. a qualified factual family can still have individual rows unavailable for calculation when their evidence is insufficient;
10. qualification state is machine-readable and reviewable rather than a hidden config toggle.

Initial candidate families, evaluated independently:

- Governor Gear;
- Governor Charms;
- Hero Gear/Mastery;
- troop training/promotion;
- Academy/War Academy research;
- buildings/Truegold progression.

### Evidence-gated acceptance criteria

- **CE-01** each family has an explicit qualification report with pass/fail reasons for every gate above.
- **CE-02** no calculator route/action is enabled for an unqualified family.
- **CE-03** qualifying one family does not unlock another.
- **CE-04** conflicts cannot be hidden to make a family pass.
- **CE-05** unknown never means zero and cannot participate in a derived total unless the calculation explicitly supports an unknown result.
- **CE-06** source updates produce a new immutable dataset/calculation release rather than mutating historical scenarios.

## Evidence-gated extension 7 — Evidence-backed calculators

Implementation starts only for a family whose qualification report is `qualified`.

### Outcome

For a qualified family, a Governor can calculate the factual resource/time/requirement delta from a selected current step to target step and optionally save the scenario against the exact dataset/calculation release used.

### Acceptance criteria — Calculator implementation

- **CI-01** calculation logic is a pure typed domain/service operation over a qualified immutable dataset, not frontend constants.
- **CI-02** inputs are validated against canonical IDs/levels and version boundaries.
- **CI-03** result provenance names the dataset, calculation version, sources and assumptions.
- **CI-04** unavailable/conflicting inputs return an unavailable/conflicting result instead of a guessed total.
- **CI-05** saved scenarios pin all inputs plus dataset/checksum/calculation version and remain historically stable.
- **CI-06** rounding/unit rules are explicit and golden-fixture tested.
- **CI-07** calculators do not become recommendations; they answer the user's selected scenario only.
- **CI-08** localization/accessibility/mobile/visual coverage includes long numeric values, unknown/conflicting states and source disclosure.

## Selected extension 8 — Territory plan versus observed state

### Outcome

An authorized planner can compare an immutable published Territory revision with the latest authorized observed spatial state without either record rewriting the other.

Observed spatial state is evidence/observation, not planning intent or map truth. Supported observed facts may include fixture-proven HQ, Banner, Bear Trap and Governor-city coordinates.

Required comparison can report:

- planned object observed at/near expected coordinate under documented tolerance;
- planned object not observed;
- observed object not represented in the plan;
- Governor city displacement/distance delta;
- coverage/connectivity effect using the plan's pinned map dataset where valid;
- stale/unknown/conflicting observation state.

### Acceptance criteria — Territory reconciliation

- **TR-01** published plan revisions remain immutable desired-state intent.
- **TR-02** observed positions retain source/time/confidence and do not mutate plan objects or GameWorld map facts.
- **TR-03** comparison pins the plan's map dataset/checksum and states when observed evidence is incompatible/stale for that boundary.
- **TR-04** coordinate tolerance/normalization is typed, documented and fixture-tested; it is not hidden in Vue.
- **TR-05** missing observation is not rendered as a coordinate of zero or as proof that an object does not exist.
- **TR-06** cross-Alliance/Kingdom observation data is authorization-filtered before comparison.
- **TR-07** correction follows the observation/Evidence owner workflow; correcting a plan requires a new editable head/revision workflow.
- **TR-08** accessible text equivalents expose every material comparison result shown spatially.

## Selected extension 9 — Intelligence change signals

### Outcome

The product derives bounded, deterministic change signals from existing authorized observation histories so Command Overview, Kingdom Intelligence, Communications and Alliance Assistant can surface meaningful changes without inventing strategic conclusions.

Candidate signals include:

- tracked Alliance power/member-count change;
- observed Governor progression changed;
- observation became stale;
- Transfer evidence approaching/at expiry;
- factual operational trend changes where an existing owner provides the history.

A signal is a read-side derivation with source observation identifiers/timestamps. It is not a new source of truth and must not infer intent such as `preparing to attack` from a numeric change.

### Acceptance criteria — Intelligence signals

- **IS-01** every signal is deterministically derivable from explicit owner history and cites the source observations/facts.
- **IS-02** thresholds/windows are typed configuration/domain policy with tests, not presentation magic numbers.
- **IS-03** stale/missing/conflicting source state produces an explicit non-conclusive signal state or no signal according to the documented rule.
- **IS-04** signals are authorization-filtered before cross-context composition.
- **IS-05** signals never mutate the source observation history or become automatic strategy recommendations.
- **IS-06** notifications, if enabled later in the same slice, use Communications owner preferences/idempotency and do not duplicate delivery state.
- **IS-07** Assistant presentation labels derived observation changes as Observation, not Game data.
- **IS-08** privacy-safe telemetry records signal type/count/latency without sensitive source values or raw evidence.

## Delivery order

The program is implemented continuously in this order unless a documented dependency forces a change:

| Phase | Classification | Slice |
| --- | --- | --- |
| 0 | Current complete capability | Reconcile `/docs/product`, provenance/ownership and delivery ledger |
| 1 | Current complete capability | Alliance Assistant `game_fact` |
| 2 | Current complete capability | Assistant operational-self intents and safe owner-workflow handoffs |
| 3 | Selected extension | Event Readiness |
| 4 | Selected extension | Event Closeout |
| 5 | Selected extension | Kingdom Transfer Screenshot Intake |
| 6 | Selected extension | Governor Progression Screenshot Intake |
| 7 | Selected extension | Progression Goal Planner |
| 8 | Evidence-gated extension | Calculator evidence qualification per family |
| 9 | Evidence-gated extension | Calculators for qualified families only |
| 10 | Selected extension | Territory observed-state reconciliation |
| 11 | Selected extension | Intelligence change signals |
| 12 | Selected extension | Full spec/code/UX/authorization/provenance reconciliation and release closeout |

No phase is considered complete merely because backend classes or a frontend page exist. Complete means its acceptance criteria and repository Definition of Done are satisfied on one immutable candidate.

## Program-wide acceptance criteria

- **PX-01 Ownership:** every new datum has one canonical owner; ReadModels and Assistant own composition only.
- **PX-02 Provenance:** source-backed facts/observations/evidence retain source IDs, observation/version boundary and explicit unknown/conflicting semantics.
- **PX-03 Authorization:** active Governor and concrete resource scope are checked before retrieval and reacquired at protected write boundaries.
- **PX-04 Idempotency:** every Evidence-to-owner commit and external/retryable effect has a stable idempotency identity and crash-safe recovery path.
- **PX-05 No duplicated truth:** no parallel knowledge, Event readiness, calculator-source, spatial-state or intelligence store duplicates an existing owner's truth.
- **PX-06 Write boundaries:** cross-context writes use scalar IDs/value objects through owner Actions; foreign Eloquent models are not passed or mutated.
- **PX-07 Evidence discipline:** machine extraction is never silently promoted to destination truth; supported typed review/commit remains explicit.
- **PX-08 Missing-data discipline:** missing, stale, conflicting and unsupported values remain explicit and cannot silently satisfy readiness, eligibility, calculation or reconciliation.
- **PX-09 UX:** every material journey has loading/empty/unknown/conflicting/stale/permission/failure/recovery states, mobile behavior, keyboard/screen-reader support and localization.
- **PX-10 Observability/privacy:** audit and diagnostics are sufficient to recover material workflows without logging raw screenshots, OCR text, private questions, sensitive provider payloads or unauthorized identity data.
- **PX-11 Performance:** cross-context read models have bounded queries/results and explicit no-N+1/query-budget tests at realistic Alliance sizes.
- **PX-12 Architecture:** domain rules/formulas/normalization stay out of controllers, jobs, bots and Vue components; architecture tests enforce owner dependency direction.
- **PX-13 Documentation:** product/architecture/reference/operations/codebase docs change in the same implementation slice when their contracts change.
- **PX-14 Release:** PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scan, staging/clean-database and backup/restore checks are green as applicable.
- **PX-15 No compatibility debt:** no shims, deprecated aliases, dual reads, dual writes, legacy routes or temporary parallel schemas are retained; the application is not deployed.

## Phase 0 exit criteria

Phase 0 is complete when all of the following are true in one documentation change:

1. the stale Screenshot Intake ledger rows are reconciled to `Complete` without reopening the implemented Bear Hunt capability;
2. this document exists and is linked from the Product index;
3. the capability catalogue identifies the extension program and its ownership without claiming unimplemented outcomes are already delivered;
4. the capability gap analysis distinguishes Current complete capability, Selected extension and Evidence-gated extension;
5. the global delivery ledger contains every program phase and acceptance-criteria reference;
6. primary user journeys contain the program journey contracts and clearly label each journey's current program state;
7. data provenance/ownership rules above are the canonical product contract before application changes;
8. no application code, migrations, routes, frontend components or runtime configuration are changed by Phase 0.
