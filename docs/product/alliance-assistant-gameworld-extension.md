# Alliance Assistant — GameWorld Extension

Status: Complete — 2026-08-24

This document extends `docs/product/alliance-assistant.md` and is the implementation source of truth for the Alliance Assistant GameWorld extension. The base Alliance Assistant invariants remain mandatory. Completion requires owner-query/domain behavior, authorization and tenant isolation, unknown/conflicting-data handling, provenance and citations, stale-context behavior, UX, mobile behavior, accessibility, localization, observability, automated tests, architecture enforcement, contract tests and applicable visual regression to remain complete.

## Product outcome

Extend Alliance Assistant into existing GameWorld and Operations capabilities without turning it into a general KingShot chatbot. An authenticated Governor may ask bounded questions about source-backed Progression facts, their own Event participation, their own battle-plan assignments, their own authorized transfer status and the immutable published territory revision attached to an Event.

Every supported request must preserve this boundary:

```text
intent
 -> authorized owner query
 -> typed evidence
 -> server-created citation
 -> localized answer
```

The Assistant owns interpretation/composition only. It does not own game truth, transfer rules, roster state, battle-plan state, territory plans or write behavior.

## Non-goals and hard boundaries

The extension must not:

- become an unconstrained KingShot chatbot;
- answer factual game questions from model/training knowledge;
- create an Assistant-owned knowledge store or duplicate Progression data;
- resolve unknown or conflicting GameWorld evidence inside the Assistant;
- import broad management projections and filter them after retrieval;
- expose another Governor's participation, assignment, transfer or planning data;
- mutate roster, participation, battle-plan, transfer or territory state;
- add hidden form submissions, automatic POSTs, privileged Assistant Actions or alternate write paths;
- attach arbitrary URLs supplied by interpreted/generated text;
- infer inaccessible source existence through differing not-found responses.

Where an owner does not expose the minimum authorized projection needed for an intent, first add/refactor a narrow owner query and test that boundary. Only then compose it into Alliance Assistant.

## Capability ownership

| Intent/data | Canonical owner | Assistant responsibility |
| --- | --- | --- |
| Progression dataset/release, hero facts, gear, troops, Academy research and source status | `GameWorld/Progression` | bounded fact request + evidence projection |
| Event identity/occurrence/time | `Operations/Events` | authorized Event resolution |
| self RSVP/registration/waitlist | `Operations/Participation` | compose self-only owner projection |
| self objective/team assignment | `Operations/BattlePlans` | compose self-only owner projection |
| transfer eligibility/requirements | `GameWorld/KingdomTransfers` | compose self-only authorized assessment |
| published territory revision attached to Event | `Operations/TerritoryPlanning` | compose immutable attached revision only |
| citations, localized answer keys, handoff projection | `ReadModels/AllianceAssistant` | composition only |
| mutations | owning capability Actions | never invoked by Assistant |

Owner contexts must not depend on `ReadModels/AllianceAssistant`.

## Authorization invariant

Authorization occurs before retrieval. Data outside the active Governor's legitimate scope must never enter an Assistant candidate set, evidence collection, diagnostic payload, answer or citation.

For every Event-scoped intent:

1. resolve only Events visible to the active Governor through the existing authorized Event query;
2. reject ambiguity before reading dependent self state;
3. pass the resolved occurrence and active `PlayerReference` to a self-scoped owner query;
4. return neutral `not_found`/unsupported semantics for inaccessible data.

For transfer status, the owner query must establish that the active Governor is legitimately represented within an authorized transfer plan/window before eligibility inputs are loaded. Supplying a target Kingdom number alone never creates transfer scope.

## Bounded intents

### `game_fact`

Examples:

- `What generation is Amadeus?`
- `What troop class is Zoe?`
- `What is the max Widget level?`
- `What does Governor Gear Mythic 2 require?`
- `What are the stats for this troop tier?`
- `What does this Academy research level do?`

Owner: `GameWorld/Progression`.

The interpreter maps only documented fact forms to a typed request. A generic `what is X?` must not become a GameWorld query.

The owner result must include:

- fact resolution state: `known`, `unknown` or `conflicting`;
- family;
- canonical row/path;
- typed field/value projection;
- dataset release identifier/version;
- checksum;
- source IDs;
- confidence/evidence status;
- canonical Progression route.

`unknown` and `conflicting` are terminal factual states. Alliance Assistant must report them; it must not select a preferred row, merge competing values or use model knowledge to fill the gap.

Every answered GameWorld fact is classified **Game data** / `game_fact`.

### `event_participation_self`

Examples:

- `Did I register for Swordland?`
- `What did I RSVP for this week?`
- `Am I waitlisted?`

Owners: `Operations/Events`, then `Operations/Participation`.

Behavior:

- return only the active Governor's response/registration/waitlist state;
- support a bounded authorized week window for list questions;
- distinguish no response, response, registered and waitlisted states according to owner semantics;
- never cite a participation record that does not exist;
- cite the Event and the self participation record when present.

### `battle_plan_self`

Examples:

- `What is my Swordland assignment?`
- `Which objective am I assigned to?`
- `What team am I on?`

Owners: `Operations/Events`, then `Operations/BattlePlans`.

The owner must expose a self-only projection that returns effective direct and roster-derived assignments for the active Governor. The Assistant must not consume a management projection containing all objective assignments and then filter it.

Return a bounded list when more than one legitimate assignment applies. Include objective identity/name/type/status, team/roster where applicable, assignment notes only when already visible to that Governor, and a canonical Event/battle-plan route.

### `transfer_status_self`

Examples:

- `Can I transfer to Kingdom 123?`
- `What am I missing for transfer?`

Owner: `GameWorld/KingdomTransfers`.

The owner query must:

- establish the active Governor's authorized transfer plan/participant scope first;
- evaluate only that participant;
- reuse the canonical `TransferEligibilityEvaluator` and observation selection rules;
- return the target Kingdom, eligibility outcome, requirement states, assessment timestamp and supporting source/observation references;
- preserve `needs verification`, `unmet`, `unknown` and `conflicting` requirements without Assistant reinterpretation.

If the active Governor is not legitimately in an authorized transfer scope, return neutral unavailable/not-found behavior. The Assistant never creates or changes a transfer participant to answer the question.

### `territory_plan`

Example:

- `Which hive layout are we using for Bear Hunt?`

Owners: `Operations/Events`, then `Operations/TerritoryPlanning`.

Return only the immutable published `TerritoryPlanRevision` explicitly attached to the resolved Event occurrence. Do not return mutable plan head state or enumerate unrelated revisions.

Evidence includes plan name, revision number, published timestamp, map dataset ID/checksum, purpose and canonical viewer route. Multiple matching attachments produce `ambiguous`; none produces `not_found`.

Classification is Alliance plan/strategy, never universal Game data.

## Action handoff — navigation only

Recognized write attempts may return a typed navigation handoff rather than only `unsupported`.

Example:

```text
User: Put me on the Swordland roster.
Assistant: I can't change the roster from here.
           Open Swordland roster →
```

Contract:

- write-attempt detection remains ahead of read intent execution;
- the Assistant may resolve an authorized Event identifier solely to construct a safe destination;
- handoff URLs are server-created canonical application routes;
- no owner Action is invoked;
- no domain state is mutated;
- no hidden form or automatic submission is rendered;
- navigation does not carry a privileged authorization result;
- the destination performs its ordinary authorization, validation and current-context checks;
- stale authority context is rejected through the existing `X-Game-Context-Version` protocol;
- unrecognized write requests remain unsupported.

Conceptual result extension:

```text
AssistantNavigationHandoff
  labelKey
  href
  kind = navigation
```

## Evidence and citation contract

Extend `AssistantEvidence.sourceType` with typed owner sources for participation, battle-plan assignment, transfer assessment and territory revision while retaining `game_fact`.

Game fact metadata must include:

```text
datasetReleaseId
datasetVersion
checksum
family
path
sourceIds[]
confidence
evidenceStatus
```

Citations remain server-created from the exact evidence set returned in the response. Free-form text may never create source IDs or URLs.

Classifications remain semantically separate from source type:

- `operational_fact` — Event, roster, RSVP/registration and battle-plan operational state;
- `game_fact` — source-backed GameWorld Progression fact;
- `alliance_strategy` — Alliance-authored material or published Alliance territory plan;
- `observation` — recorded observation/transfer evidence where presented as observation.

## Failure and ambiguity states

Required behavior:

- ambiguous Event -> no dependent owner query;
- unknown GameWorld row -> `answered` with explicit unknown/evidence status, not guessed value;
- conflicting GameWorld row -> `answered` with explicit conflict state, not guessed value;
- no participation record -> neutral self-state answer supported by Event evidence only;
- multiple self battle assignments -> bounded list;
- transfer `NeedsVerification` -> never rendered as eligible;
- no legitimate transfer scope -> neutral `not_found`/unavailable response;
- multiple attached territory revisions matching purpose -> `ambiguous`;
- no attached territory revision -> `not_found`;
- owner failure -> privacy-safe `unavailable`;
- stale context -> shared `409 CONTEXT_STALE` before retrieval;
- general or low-confidence KingShot question -> `unsupported`.

## UX contract

The Assistant remains framed as **Ask your Alliance**, never `Ask anything`.

First-use scope copy must expand to communicate that answers can cover:

- Events and the Governor's own roster/RSVP;
- the Governor's own Event assignments;
- Alliance guides and observations;
- published Event territory plans;
- source-backed Game data;
- the Governor's own transfer readiness when in scope.

First-use discovery is additive, not a replacement for the delivered Assistant surface. The default prompt set must preserve the four established prompts (`swordland_roster`, `next_event`, `bear_hunt_guide`, `observation`) and add the five bounded extension prompts (`hero_fact`, `rsvp_week`, `battle_assignment`, `transfer_status`, `territory_plan`). All nine must remain keyboard reachable and wrap without horizontal overflow on narrow screens.

Required new presentation states:

- known Game fact;
- Game fact unknown;
- Game fact conflicting;
- Game data source/release card;
- RSVP/registration answer;
- waitlist position;
- weekly RSVP list;
- self battle assignment and multiple assignments;
- transfer eligible/ineligible/needs verification;
- transfer requirements/unknown requirements;
- no authorized transfer scope;
- attached published territory plan;
- territory-plan ambiguity;
- navigation-only write handoff.

Every source card must show a textual classification label. Game data must visibly expose release/source status. Territory plan must visibly identify the immutable revision.

## Accessibility, localization and performance

All new answers are returned as stable message keys plus typed parameters. The extension must provide native extension strings for every supported Assistant locale; the existence of the application's normal English fallback does not satisfy extension localization completeness. Do not embed English answer prose in PHP owner queries or frontend conditionals.

Owner-domain explanatory prose is data, not localized Assistant UI. For transfer requirements the Assistant renders the canonical requirement key and owner requirement state through localized labels, while typed actual/required/source values remain data. It must not surface an English owner `explanation` or `nextAction` as translated interface copy.

Extension-only localization payloads must load with the Assistant localization domain rather than the global application entry. The extension must continue to satisfy the repository's existing initial-JavaScript, page-chunk and stylesheet performance budgets; do not raise a budget to hide an Assistant-only loading regression.

New handoff links must be ordinary keyboard-focusable links with visible focus states. Status changes remain announced through the existing Assistant live-region pattern. Classification must not rely on color alone. Long requirements/source labels and the nine-prompt discovery grid must wrap on mobile without horizontal overflow.

## Observability and privacy

Preserve current request/response privacy rules: no persistent Assistant transcript and no private question/source text in ordinary logs. Metrics may identify bounded intent/status/classification but must not include raw questions, player names, source excerpts or transfer observations.

Add/retain privacy-safe metrics for:

- intent;
- status;
- owner query unavailable;
- unknown/conflicting GameWorld fact;
- action-handoff offered;
- stale-context rejection through the shared boundary.

## Acceptance criteria

### AC-GF — Game facts

- [x] `game_fact` is a closed deterministic intent, not a generic fallback.
- [x] GameWorld owns fact lookup and evidence/conflict semantics.
- [x] supported hero generation/troop-class questions resolve from Progression.
- [x] max-level, Governor Gear requirement, troop-tier stats and Academy-level questions resolve only from supported Progression families.
- [x] every answer contains dataset release/version, checksum, source IDs and confidence/evidence status where available.
- [x] unknown/conflicting evidence is preserved and visibly reported.
- [x] canonical Progression route is server-created.

### AC-PART — Self participation

- [x] named Event registration/RSVP resolves Event authorization before self participation retrieval.
- [x] waitlist status and position are preserved.
- [x] bounded `this week` RSVP question returns only authorized occurrences and the active Governor's rows.
- [x] absent participation never produces fabricated evidence.

### AC-BP — Self battle plan

- [x] a narrow owner query returns only effective assignments for the active Governor.
- [x] management-wide assignment projection is not consumed by Assistant.
- [x] direct and roster-derived assignment behavior is tested.
- [x] multiple legitimate assignments remain multiple.

### AC-TR — Self transfer status

- [x] active Governor transfer scope is proven before participant evidence is loaded.
- [x] only the active Governor participant is evaluated.
- [x] canonical eligibility evaluator is reused.
- [x] unmet/unknown/conflicting requirements are returned as owned assessment semantics.
- [x] `NeedsVerification` is never rewritten as eligible.
- [x] out-of-scope Governor receives neutral response.

### AC-TP — Territory plan

- [x] a narrow owner query returns only the immutable published revision attached to the authorized occurrence.
- [x] mutable plan head and unrelated revisions never enter the Assistant candidate set.
- [x] no attachment -> `not_found`; multiple purpose matches -> `ambiguous`.
- [x] revision/map dataset provenance is cited.

### AC-HO — Action handoff

- [x] recognized roster write request returns read-only navigation handoff.
- [x] handoff performs zero mutation.
- [x] destination is server-created and Event-resolved.
- [x] normal destination authorization/current-context handling remains authoritative.
- [x] unknown writes remain unsupported.

### AC-X — Cross-cutting

- [x] owner contexts do not import Alliance Assistant.
- [x] Alliance Assistant does not import owner Actions or write repositories.
- [x] no broad management-query-and-filter implementation is introduced.
- [x] every substantive answer has typed evidence and server-created citations.
- [x] frontend renders all required mobile/desktop/accessibility states and preserves all nine bounded discovery prompts.
- [x] all supported locales contain native extension strings; English fallback is not counted as extension completion.
- [x] transfer requirement keys/states are localized without presenting English owner explanatory prose as UI copy.
- [x] Assistant-only localization payloads remain lazy/domain-scoped and all repository performance budgets remain green without a budget increase.
- [x] PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contract tests and visual regression are green.
- [x] applicable CodeQL, dependency review, container/staging/release checks are green before status becomes Complete.

## Delivery ledger

The ledger below is authoritative for this extension. Every row has been implemented and verified against the completed acceptance contract.

| ID | Deliverable | Status |
| --- | --- | --- |
| AA-GW-001 | Product contract and acceptance criteria | Complete |
| AA-GW-002 | Typed intents, parsed requests, evidence/result/handoff contracts | Complete |
| AA-GW-003 | Narrow GameWorld Progression fact query | Complete |
| AA-GW-004 | `game_fact` interpretation/composition/citations | Complete |
| AA-GW-005 | `event_participation_self` owner composition | Complete |
| AA-GW-006 | self-only BattlePlans owner query + composition | Complete |
| AA-GW-007 | self-only authorized transfer assessment query + composition | Complete |
| AA-GW-008 | narrow Event-attached published territory revision query + composition | Complete |
| AA-GW-009 | navigation-only recognized-write handoff | Complete |
| AA-GW-010 | additive nine-prompt UX, accessibility, native all-locale localization and domain-scoped performance | Complete |
| AA-GW-011 | authorization, architecture, behavior, contract and visual tests | Complete |
| AA-GW-012 | reference/architecture/operations reconciliation | Complete |
| AA-GW-013 | full quality/release verification and final ledger reconciliation | Complete |

Any regression that invalidates an acceptance criterion reopens the corresponding ledger row; new Assistant capabilities require a new explicit bounded extension rather than broadening this completed contract implicitly.