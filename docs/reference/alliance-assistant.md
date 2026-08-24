# Alliance Assistant HTTP contract

Status: Complete — 2026-08-24

The Alliance Assistant HTTP surface is an authenticated, Alliance-scoped read/composition contract. It does not expose an unconstrained chat API and does not create a second application write path.

## Routes

### `GET /assistant`

Returns the Inertia `Assistant/Index` page for an authenticated, verified account with an active Player and active Alliance context.

Possible boundary responses include:

- `401` when the account is unauthenticated;
- verification redirect/denial under the normal `verified` middleware;
- `409` when no active Player or active Alliance context can be resolved;
- `200` when the authorized page is rendered.

### `POST /assistant/ask`

This POST is logically read-only. POST is used so private question text is not placed in the URL, browser history, proxy query strings, or routine GET access logs.

Middleware:

- `auth`;
- `auth.session`;
- `verified`;
- the repository-wide current Player authority-context version guard;
- `alliance.context`;
- `throttle:alliance-assistant`.

The browser must submit the current `X-Game-Context-Version` value on same-origin POST requests. The normal frontend authority-context runtime attaches this header automatically. A missing or stale value is rejected before Assistant retrieval with `409 CONTEXT_STALE` and `X-Game-Context-Error: stale`; the caller must reload/re-resolve the active Governor context rather than retrying against stale authority.

Request JSON:

```json
{
  "question": "What time is Swordland and am I rostered?",
  "prompt": "swordland_roster"
}
```

`question` is required by behavior and must contain 2 through the configured maximum number of characters after trimming. ASCII control characters other than normal whitespace are rejected.

`prompt` is optional. When supplied it must be one of the closed localized suggestion identifiers:

- `swordland_roster`;
- `next_event`;
- `bear_hunt_guide`;
- `observation`.

A prompt identifier selects only a predeclared read intent. It grants no additional source visibility. Write-like question text remains unsupported even when paired with a read prompt identifier.

## Response

Successful Assistant processing returns a structured JSON object. User-visible operational prose is represented by a localization key plus typed parameters rather than a server-authored English answer string.

```json
{
  "intent": "event_roster_self",
  "status": "answered",
  "messageKey": "assistant.answers.eventTimeRostered",
  "messageParameters": {
    "event": "Swordland",
    "startsAt": "2026-08-29T20:00:00+00:00",
    "roster": "Team 2",
    "role": "joiner",
    "slot": 4,
    "status": "assigned"
  },
  "classifications": ["operational_fact"],
  "evidence": [],
  "citations": [],
  "ambiguity": null,
  "suggestedQuestions": ["swordland_roster", "next_event", "bear_hunt_guide", "observation"]
}
```

The exact evidence/citation objects are generated on the server from owner-authorized evidence. The browser cannot submit citation identifiers to make them authoritative.

Statuses:

- `answered` — a supported question was answered from authorized evidence;
- `ambiguous` — more than one authorized source could match and the Assistant will not choose arbitrarily;
- `not_found` — no authorized source supports the requested answer;
- `unsupported` — the request is outside the bounded intent catalogue or is write-like;
- `validation_error` — input validation failed;
- `unavailable` — an owner read or composition dependency failed; no model-knowledge fallback is used.

HTTP status mapping:

- normal Assistant outcomes, including `not_found`, `unsupported`, and `ambiguous`: `200`;
- invalid question or prompt: `422`;
- stale/missing Player authority context: `409 CONTEXT_STALE` with `X-Game-Context-Error: stale` when the current-authority guard rejects the POST;
- rate limit: `429` from middleware;
- unavailable owner/composition failure: `503`;
- other authorization failures: normal `401`/`403`/`409` application boundaries.

## Evidence and citations

Each evidence item contains a server-created evidence ID, source type, source ID, title, provenance classification, bounded statement, timestamps where applicable, safe deep link where applicable, and allowlisted metadata.

Each citation is derived from an evidence item actually used by the answer. Citation classifications are closed:

- `operational_fact`;
- `game_fact`;
- `alliance_strategy`;
- `observation`.

`game_fact` is reserved for a future bounded intent backed by an approved GameWorld owner query. The current Assistant does not answer arbitrary KingShot mechanics questions from model knowledge.

## Write boundary

Neither route mutates domain state. A request such as `Put me on the Swordland roster` returns `unsupported`; it does not invoke a roster write. Any future action handoff must call the existing owning capability Action and inherit its authorization, validation, idempotency, audit/outbox, observability, and recovery semantics.
