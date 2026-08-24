# Alliance Assistant — GameWorld Extension

Status: Active delivery — 2026-08-24

This document extends `docs/product/alliance-assistant.md` and is the implementation source of truth for the Alliance Assistant GameWorld extension. The base Alliance Assistant invariants remain mandatory. No item in this extension is complete until its owner-query/domain behavior, authorization and tenant isolation, unknown/conflicting-data handling, provenance and citations, stale-context behavior, UX, mobile behavior, accessibility, localization, observability, automated tests, architecture enforcement, contract tests and applicable visual regression are complete.

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
- preserve `needs verification`, `unknown` and `conflicting` requirements without Assistant reinterpretation.

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

## Accessibility and localization

All new answers are returned as stable message keys plus typed parameters. Add keys to every supported Assistant locale. Do not embed English answer prose in PHP owner queries or frontend conditionals.

New handoff links must be ordinary keyboard-focusable links with visible focus states. Status changes remain announced through the existing Assistant live-region pattern. Classification must not rely on color alone. Long requirements/source labels must wrap on mobile without horizontal overflow.

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

- [ ] `game_fact` is a closed deterministic intent, not a generic fallback.
- [ ] GameWorld owns fact lookup and evidence/conflict semantics.
- [ ] supported hero generation/troop-class questions resolve from Progression.
- [ ] max-level, Governor Gear requirement, troop-tier stats and Academy-level questions resolve only from supported Progression families.
- [ ] every answer contains dataset release/version, checksum, source IDs and confidence/evidence status where available.
- [ ] unknown/conflicting evidence is preserved and visibly reported.
- [ ] canonical Progression route is server-created.

### AC-PART — Self participation

- [ ] named Event registration/RSVP resolves Event authorization before self participation retrieval.
- [ ] waitlist status and position are preserved.
- [ ] bounded `this week` RSVP question returns only authorized occurrences and the active Governor's rows.
- [ ] absent participation never produces fabricated evidence.

### AC-BP — Self battle plan

- [ ] a narrow owner query returns only effective assignments for the active Governor.
- [ ] management-wide assignment projection is not consumed by Assistant.
- [ ] direct and roster-derived assignment behavior is tested.
- [ ] multiple legitimate assignments remain multiple.

### AC-TR — Self transfer status

- [ ] active Governor transfer scope is proven before participant evidence is loaded.
- [ ] only the active Governor participant is evaluated.
- [ ] canonical eligibility evaluator is reused.
- [ ] unmet/unknown/conflicting requirements are returned as owned assessment semantics.
- [ ] `NeedsVerification` is never rewritten as eligible.
- [ ] out-of-scope Governor receives neutral response.

### AC-TP — Territory plan

- [ ] a narrow owner query returns only the immutable published revision attached to the authorized occurrence.
- [ ] mutable plan head and unrelated revisions never enter the Assistant candidate set.
- [ ] no attachment -> `not_found`; multiple purpose matches -> `ambiguous`.
- [ ] revision/map dataset provenance is cited.

### AC-HO — Action handoff

- [ ] recognized roster write request returns read-only navigation handoff.
- [ ] handoff performs zero mutation.
- [ ] destination is server-created and Event-resolved.
- [ ] normal destination authorization/current-context handling remains authoritative.
- [ ] unknown writes remain unsupported.

### AC-X — Cross-cutting

- [ ] owner contexts do not import Alliance Assistant.
- [ ] Alliance Assistant does not import owner Actions or write repositories.
- [ ] no broad management-query-and-filter implementation is introduced.
- [ ] every substantive answer has typed evidence and server-created citations.
- [ ] frontend renders all required mobile/desktop/accessibility states.
- [ ] all supported locales contain the new keys.
- [ ] PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contract tests and visual regression are green.
- [ ] applicable CodeQL, dependency review, container/staging/release checks are green before status becomes Complete.

## Delivery ledger

The ledger below is authoritative for this extension. Work proceeds to the next incomplete row immediately after the previous row is implemented and verified.

| ID | Deliverable | Status |
| --- | --- | --- |
| AA-GW-001 | Product contract and acceptance criteria | Complete |
| AA-GW-002 | Typed intents, parsed requests, evidence/result/handoff contracts | Pending |
| AA-GW-003 | Narrow GameWorld Progression fact query | Pending |
| AA-GW-004 | `game_fact` interpretation/composition/citations | Pending |
| AA-GW-005 | `event_participation_self` owner composition | Pending |
| AA-GW-006 | self-only BattlePlans owner query + composition | Pending |
| AA-GW-007 | self-only authorized transfer assessment query + composition | Pending |
| AA-GW-008 | narrow Event-attached published territory revision query + composition | Pending |
| AA-GW-009 | navigation-only recognized-write handoff | Pending |
| AA-GW-010 | UX, accessibility and all-locale localization | Pending |
| AA-GW-011 | authorization, architecture, behavior, contract and visual tests | Pending |
| AA-GW-012 | reference/architecture/operations reconciliation | Pending |
| AA-GW-013 | full quality/release verification and final ledger reconciliation | Pending |

Do not change this document to Complete while any ledger row or acceptance criterion remains incomplete.