# Alliance Assistant HTTP contract

Status: Kingshot expansion implemented; containing-candidate verification pending — 2026-08-29

The Alliance Assistant HTTP surface is an authenticated, Alliance-scoped read/composition contract. It does not expose an unconstrained chat API and does not create a second application write path.

## Routes

### `GET /assistant`

Returns the Inertia `Assistant/Index` page for an authenticated, verified account with an active Player and active Alliance context.

### `POST /assistant/ask`

This POST remains logically read-only. POST is used so private question text is not placed in the URL, browser history, proxy query strings, or routine GET access logs.

Middleware remains:

- `auth`;
- `auth.session`;
- `verified`;
- current Player authority-context version guard;
- `alliance.context`;
- `throttle:alliance-assistant`.

The browser must submit the current `X-Game-Context-Version`. Missing/stale context is rejected before Assistant retrieval with `409 CONTEXT_STALE` and `X-Game-Context-Error: stale`.

Request JSON:

```json
{
  "question": "What generation is Amadeus?",
  "prompt": "hero_fact"
}
```

`question` must contain 2 through the configured maximum characters after trimming. Control characters are rejected.

Closed `prompt` identifiers:

- `swordland_roster`;
- `next_event`;
- `bear_hunt_guide`;
- `observation`;
- `hero_fact`;
- `rsvp_week`;
- `battle_assignment`;
- `transfer_status`;
- `territory_plan`.
- `alliance_command`;
- `event_readiness`;
- `rally_gaps`;
- `bear_hunt_history`;
- `progression_freshness`;
- `transfer_verification`;
- `intelligence_changes`;
- `territory_comparison`.

Prompt identifiers select only predeclared intents and grant no authority. Write-attempt detection is evaluated before prompt override.

## Supported intent values

- `help`;
- `event_time`;
- `event_roster_self`;
- `event_participation_self`;
- `battle_plan_self`;
- `game_fact`;
- `transfer_status_self`;
- `territory_plan`;
- `alliance_content`;
- `alliance_observation`;
- `intelligence_changes`;
- `alliance_command_attention`;
- `event_readiness`;
- `rally_gaps`;
- `bear_hunt_history`;
- `progression_freshness`;
- `transfer_verification`;
- `territory_comparison`;
- `action_handoff`;
- `unsupported`.

No other value creates a generic chatbot fallback.

## Response

```json
{
  "intent": "game_fact",
  "status": "answered",
  "messageKey": "assistant.answers.gameFactKnown",
  "messageParameters": {
    "title": "Amadeus",
    "resolution": "known",
    "values": {
      "fact": "Generation",
      "value": "6"
    },
    "datasetVersion": "2026.08.23.2",
    "evidenceStatus": "maintained_source_inspectable"
  },
  "classifications": ["game_fact"],
  "evidence": [],
  "citations": [],
  "ambiguity": null,
  "suggestedQuestions": [],
  "handoff": null
}
```

`messageParameters` may contain bounded typed lists/objects for answer details such as participation items, battle assignments, GameWorld values, and transfer requirements. User-visible prose still comes from localization keys; typed structures are rendered semantically by the frontend.

Statuses remain:

- `answered`;
- `ambiguous`;
- `not_found`;
- `unsupported`;
- `validation_error`;
- `unavailable`.

Normal Assistant outcomes return HTTP 200. Input validation returns 422, stale context 409, rate limit 429, and owner/composition unavailability 503.

## Evidence and citations

Evidence source types include:

- `event`;
- `roster`;
- `participation`;
- `battle_plan_assignment`;
- `transfer_assessment`;
- `territory_plan_revision`;
- `alliance_content`;
- `observation`;
- `game_fact`.
- `alliance_command`;
- `event_readiness`;
- `bear_hunt_run`;
- `roster_freshness`;
- `transfer_verification`;
- `territory_comparison`.

Classifications remain:

- `operational_fact`;
- `game_fact`;
- `alliance_strategy`;
- `observation`.

Citation metadata is server-created from the exact evidence item. It may contain bounded nested typed metadata.

### GameWorld factual metadata

Every `game_fact` evidence/citation includes:

```json
{
  "resolution": "known|unknown|conflicting",
  "family": "heroes",
  "path": "heroes.amadeus.generation",
  "datasetReleaseId": "kingshot-2026-08-23-v2",
  "datasetVersion": "2026.08.23.2",
  "checksum": "...",
  "sourceIds": ["..."],
  "confidence": "...",
  "evidenceStatus": "..."
}
```

An `unknown` or `conflicting` factual result is still an answered, cited source state. The Assistant does not replace it with model knowledge.

## Navigation-only handoff

A recognized write request may return:

```json
{
  "intent": "action_handoff",
  "status": "answered",
  "messageKey": "assistant.answers.rosterWriteHandoff",
  "handoff": {
    "kind": "navigation",
    "labelKey": "assistant.handoffs.openRoster",
    "href": "/events/<authorized-occurrence-id>"
  }
}
```

This is a normal GET navigation target only. The Assistant does not call a roster Action, submit a hidden form, attach a privileged authorization token, or automatically POST after navigation. The destination performs normal authorization/current-context checks.

Recognized Event, Roster, Rally, Transfer, Evidence and Territory writes remain navigation-only. If no authorized source evidence exists, the result remains `not_found`; the Assistant does not synthesize an evidence-free handoff. Unknown writes remain `unsupported`.

## Owner-specific response rules

- Event-dependent private state is not queried until one authorized Event occurrence has been resolved.
- `event_participation_self` returns only the active Governor's rows; weekly list questions use a self-only batch query over authorized occurrence IDs.
- `battle_plan_self` returns only direct or active-roster-derived assignments for the active Governor.
- `transfer_status_self` returns no assessment unless the active Governor has legitimate visible participant scope. A requested Kingdom number constrains the existing participant target and never grants scope.
- `territory_plan` returns only immutable published revisions attached to the Event occurrence; mutable plan heads are never an Assistant source.
- `alliance_command_attention`, `event_readiness`, `rally_gaps`, `progression_freshness`, `transfer_verification` and `territory_comparison` reuse the authorized Alliance Command projection and do not persist a task or score.
- `bear_hunt_history` requires Alliance Event view authority, the canonical verified `bear-hunt` identity and a bounded existing Debrief history.
- `intelligence_changes` uses only authorized typed change signals and retains their owner/source citations.
