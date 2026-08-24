# Alliance Assistant architecture

Status: Active delivery — 2026-08-24

Alliance Assistant is a **ReadModel composition capability**, not a bounded context. It owns question interpretation, authorized read composition, evidence/citation shaping, and presentation semantics. It owns no business state and no write authority.

## Placement

Runtime composition lives under:

```text
app/ReadModels/AllianceAssistant/
```

Do not create `app/Contexts/Assistant`, Assistant models, Assistant migrations, Assistant repositories, or Assistant-owned copies of Event/content/observation/roster state.

## Dependency direction

```text
Authenticated account + active Player + active Alliance
                      |
                      v
             AllianceAssistant ReadModel
                /       |       \
               v        v        v
     Operations/Events  Alliance/Content  Intelligence/Observations
               |
               v
       Operations/Rosters
```

The ReadModel calls explicit owner Queries/services. Owner contexts do not import `App\ReadModels\AllianceAssistant`.

## Authorization-before-retrieval

Authorization is part of source acquisition, not output filtering.

- Event candidates come from `Operations/Events` visibility/authorization queries.
- Roster reads happen only after an authorized Event occurrence has been uniquely resolved and are self-only in this capability.
- Alliance member content is queried only after a fresh `AlliancePermission::View` check for the active Player/Alliance.
- Intelligence observations use an Intelligence-owned Assistant projection that performs `IntelligencePermission::View` authorization before querying observation rows.

Unauthorized records must not enter an Assistant candidate list, evidence object, prompt/provider context, cache, diagnostic record, or citation.

## Evidence contract

The ReadModel maps owner outputs into transient `AssistantEvidence` values. Evidence is not persisted and does not become domain truth.

Closed classifications preserve meaning:

- operational fact;
- game fact;
- Alliance strategy;
- observation.

Alliance Content cannot become `game_fact` merely because it discusses a mechanic. Observations cannot become factual game truth. `game_fact` may be emitted only by a bounded intent whose source is an approved GameWorld owner projection; no such generic intent exists in the current release.

Citations are projections of the exact evidence used to form the answer. The browser/model cannot invent a source ID or deep link.

## Deterministic intent boundary

The current release does not call an external model. Free-form interpretation is intentionally bounded. Localized suggestion buttons submit a closed `AssistantPrompt` identifier alongside translated display text so every supported locale can invoke the same deterministic read intent without broadening free-form language inference.

A prompt identifier is not authority. The same owner authorization path runs regardless of whether an intent came from recognized text or a closed UI prompt.

## Untrusted source text

Guide bodies, summaries, observation fields, imported text, and any future Evidence-derived text are data only. They are never parsed as system/tool instructions and cannot select another source or mutation path.

## Write boundary

`ReadModels/AllianceAssistant` has no write dependency. It must not call model mutation APIs, repositories with write semantics, direct SQL mutation, or owner write Actions.

A write-like question is unsupported in the current release. A future write handoff, if product-approved, must be an explicit typed handoff to the normal owner Action after current authority/validation checks. The Assistant does not become an alternate write service.

## Persistence and caching

The current release persists no conversations, questions, answers, evidence sets, citations, or model output. Browser transcript state is memory-only and is cleared on reload/context navigation. No cross-tenant Assistant cache exists.

## Architecture enforcement

Tests must enforce:

- no `Models`, migrations, persistence repository, or domain write Actions under the Assistant ReadModel;
- no owner-context import of `App\ReadModels\AllianceAssistant`;
- no direct Eloquent mutation method use in Assistant PHP;
- no direct DB mutation statement in Assistant PHP;
- owner authorization precedes observation/content retrieval;
- no external HTTP/model SDK dependency in Assistant runtime;
- routes remain authenticated/verified/Alliance-scoped and POST ask remains logically read-only.
