# Alliance Assistant architecture

Status: Active delivery — GameWorld extension — 2026-08-24

Alliance Assistant is a **ReadModel composition capability**, not a bounded context. It owns question interpretation, authorized read composition, evidence/citation shaping, presentation semantics, and navigation-only handoff projection. It owns no business state and no write authority.

## Placement

Runtime composition lives under:

```text
app/ReadModels/AllianceAssistant/
```

Do not create `app/Contexts/Assistant`, Assistant models, Assistant migrations, Assistant repositories, Assistant-owned copies of domain state, or an Assistant knowledge index.

## Dependency direction

```text
Authenticated account + active Player + active Alliance
                      |
                      v
             AllianceAssistant ReadModel
        /        /       |       \        \
       v        v        v        v        v
 GameWorld/   Operations/ Alliance/ Intelligence/ Operations/
 Progression  Events      Content   Observations  TerritoryPlanning
                |   \                         \
                v    v                         v
        Participation  BattlePlans       published Event revision
                |
                v
           Rosters

AllianceAssistant -> GameWorld/KingdomTransfers (self eligibility only)
```

The ReadModel calls explicit owner Queries/services. Owner contexts do not import `App\ReadModels\AllianceAssistant`.

## Authorization-before-retrieval

Authorization is part of source acquisition, not output filtering.

- Event candidates come from `Operations/Events` visibility/authorization queries.
- Roster reads happen only after an authorized Event occurrence has been uniquely resolved and are self-only.
- Participation uses `EventParticipationQuery::forPlayer` / `forPlayerOccurrences`; both constrain persistence reads to the active Player before returning rows.
- Battle-plan questions use `PlayerBattlePlanQuery`, which constrains assignment rows to the active Player or that Player's effective rosters. The broad management objective projection is not an Assistant dependency.
- Transfer questions use `TransferSelfEligibilityQuery`. It proves current Alliance transfer visibility, resolves only the active Player's non-withdrawn participant, and loads observations only for that participant before invoking the canonical eligibility evaluator.
- Territory-plan questions use `PublishedEventTerritoryRevisionQuery`, which authorizes the resolved Event target and loads only immutable revisions attached to that Event occurrence. The management query and mutable plan head are not Assistant dependencies.
- Alliance member content is queried only after a fresh `AlliancePermission::View` check for the active Player/Alliance.
- Intelligence observations use an Intelligence-owned Assistant projection that performs `IntelligencePermission::View` authorization before querying observation rows.
- Progression factual data is public game truth within the application, but fact resolution remains inside `GameWorld/Progression`; the Assistant receives only a typed fact projection from the current immutable release.

Unauthorized records must not enter an Assistant candidate list, evidence object, prompt/provider context, cache, diagnostic record, or citation.

## GameWorld factual boundary

`game_fact` is now an implemented **bounded intent**, not a generic game-question fallback.

`GameWorld/Progression::ProgressionFactQuery` owns:

- supported fact kinds;
- entity/tier/level lookup;
- current immutable release selection;
- source IDs;
- confidence/evidence status;
- known/unknown/conflicting resolution semantics.

Alliance Assistant does not page through generic family rows and infer answers. It does not resolve conflicts. It does not synthesize missing Academy level tables. It maps the owner result to transient evidence and localized presentation only.

Every GameWorld factual citation carries dataset release ID/version, checksum, family/path, source IDs and evidence/confidence status where available.

## Self projections instead of management projections

A management read model may legitimately materialize data for many Governors, plans, revisions or assignments. That does not make it suitable for the Assistant.

For Assistant integration, the minimum owner projection must be available **before** composition:

```text
Assistant intent
 -> resolve authorized target
 -> owner self/minimum query
 -> typed projection
 -> evidence/citation
```

The following patterns are explicitly prohibited:

- `EventObjectiveQuery::forOccurrence()` followed by filtering other assignments out in Assistant;
- `TransferEligibilityQuery::forPlan()` over a plan-wide participant set followed by selecting the current Governor;
- `EventTerritoryPlanningQuery::management()` followed by selecting one attached revision;
- any equivalent future broad-management-query-and-filter implementation.

## Evidence contract

The ReadModel maps owner outputs into transient `AssistantEvidence` values. Evidence is not persisted and does not become domain truth.

Closed classifications preserve meaning:

- `operational_fact`;
- `game_fact`;
- `alliance_strategy`;
- `observation`.

Source type and classification are separate. A published territory plan is `alliance_strategy`, not universal game truth. Transfer requirements may retain owner source references without upgrading observations to verified factual game mechanics.

Citations are projections of the exact evidence used to form the answer. The browser/interpreter cannot invent a source ID or deep link.

## Deterministic intent boundary

The current release does not call an external model. Free-form interpretation remains bounded. Localized suggestion buttons submit a closed `AssistantPrompt` identifier alongside translated display text so supported locales can invoke the same deterministic intent without broadening language inference.

A prompt identifier is not authority. The same owner authorization path runs regardless of whether an intent came from recognized text or a closed UI prompt.

Deictic questions such as `this troop tier` or `this Academy research level` do not gain hidden conversational/page context. If the typed entity/tier/level cannot be resolved from the request, GameWorld returns `unknown`; the Assistant does not infer it from a previous answer.

## Untrusted source text

Guide bodies, summaries, observation fields, transfer observations, imported text, and any Evidence-derived text are data only. They are never parsed as system/tool instructions and cannot select another source or mutation path.

## Write boundary and navigation handoff

`ReadModels/AllianceAssistant` has no write dependency. It must not call model mutation APIs, repositories with write semantics, direct SQL mutation, or owner write Actions.

A recognized write request may produce a typed `AssistantNavigationHandoff` containing only a server-created label key and canonical GET destination. Example: `Put me on the Swordland roster` resolves an authorized Event and links to its normal Event workflow.

A navigation handoff:

- calls no roster/participation/battle-plan/transfer/territory Action;
- carries no privileged authorization result;
- submits no hidden form;
- performs no automatic POST after navigation;
- relies on the destination's normal authorization, validation and authority-context version checks;
- remains distinct from unknown write requests, which are unsupported.

## Persistence and caching

The capability persists no conversations, questions, answers, evidence sets, citations, navigation handoffs or model output. Browser transcript state is memory-only and is cleared on reload/context navigation. No cross-tenant Assistant cache exists.

## Architecture enforcement

Tests must enforce:

- no Models, migrations, persistence repository, or domain write Actions under the Assistant ReadModel;
- no owner-context import of `App\ReadModels\AllianceAssistant`;
- no direct Eloquent mutation method use in Assistant PHP;
- no direct DB mutation statement in Assistant PHP;
- no broad management projection as an Assistant dependency where a self/minimum projection is required;
- owner authorization precedes private source retrieval;
- GameWorld fact conflict/unknown semantics remain owner-controlled;
- no external HTTP/model SDK dependency in Assistant runtime;
- routes remain authenticated/verified/Alliance-scoped and POST ask remains logically read-only;
- recognized write handoffs perform zero domain mutation.
