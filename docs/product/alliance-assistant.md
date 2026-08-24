# Alliance Assistant

Status: Active delivery contract — 2026-08-24

Alliance Assistant is a constrained, authorization-aware natural-language read surface over existing Kingshot Alliance capabilities. It answers operational questions from application-owned data the active Governor is already allowed to see, preserves provenance in every answer, and never becomes a parallel source of truth or a privileged mutation path.

This document is the implementation source of truth for Alliance Assistant. A requirement is not complete until backend behavior, authorization, tenant isolation, source/provenance semantics, failure and ambiguity states, UX, accessibility, localization, observability, automated tests, architecture enforcement and applicable visual/release evidence are complete.

## Product outcome

An authenticated Governor can ask concise questions such as:

- **What time is Swordland and am I rostered?**
- **What is my next Event?**
- **Am I rostered for Bear Hunt?**
- **What does our Swordland guide say?**
- **What strategy are we using for Bear Hunt?**
- **What have we observed about an Alliance in our Kingdom?**

The Assistant returns only information supported by approved application sources and visible to the current Governor. It cites the Event, roster, Alliance-authored content item, observation or factual GameWorld source that supports each substantive answer.

Alliance Assistant is deliberately **not** an unconstrained KingShot chatbot. If the application has no authorized source for a question, the Assistant says so instead of filling the gap from model/training knowledge.

## Non-goals

The first complete release does not:

- browse the public web;
- answer from undocumented model/training knowledge;
- create a second KingShot knowledge base;
- persist Assistant-owned copies of Event, roster, content, observation or GameWorld facts;
- expose raw private evidence/OCR/provider payloads;
- perform writes directly;
- bypass an owning capability's normal application Action;
- infer inaccessible source existence through counts, titles, errors, citations or timing-specific response text;
- provide general-purpose roleplay, creative chat or unrelated conversation.

## Ownership

Alliance Assistant is a cross-context read/composition capability under `app/ReadModels/AllianceAssistant`. It is not a new bounded context and owns no game/domain truth.

| Fact / behavior | Canonical owner | Assistant behavior |
| --- | --- | --- |
| Event identity, type, occurrence and start time | `Operations/Events` | authorized read |
| Event roster membership/assignment | `Operations/Rosters` | authorized self read after Event authorization |
| registration/response state where exposed later | `Operations/Participation` | authorized read |
| objectives/assignments where exposed later | `Operations/BattlePlans` | authorized read |
| Alliance-authored guides, notices and strategy | `Alliance/Content` | authorized member-content read |
| Kingdom/Alliance observations | `Intelligence/Observations` | authorized read through Intelligence scope |
| factual game data | applicable `GameWorld` owner | authorized/source-backed read only |
| active Governor/Alliance scope | existing Player/Alliance context | authorization input |
| mutations | existing owning capability Action | handoff/deep link only; never direct write |
| question interpretation, evidence composition, answer/citation projection | `ReadModels/AllianceAssistant` | composition only |

Owner contexts must not import `ReadModels/AllianceAssistant`.

## Architectural invariant: authorization before retrieval

Unauthorized data must never enter an Assistant candidate set, prompt/context, answer cache, diagnostics payload or citation set.

The required flow is:

1. authenticate the account;
2. require an active `PlayerReference` and current Alliance scope where Alliance data is needed;
3. interpret the bounded question intent;
4. invoke existing owner Queries that enforce current Player-scoped visibility/authorization;
5. transform only authorized results into `AssistantEvidence` value projections;
6. compose an answer from those projections;
7. validate every citation against the exact evidence set used for that answer;
8. return the bounded response.

Retrieving globally and filtering after retrieval is prohibited.

## Supported intent catalogue

The initial complete release supports a closed intent catalogue. Unknown questions are not sent to an unconstrained fallback.

### `event_time`

Examples:

- `What time is Swordland?`
- `When is Bear Hunt?`
- `When is my next event?`

Source: authorized `Operations/Events` occurrence data.

Behavior:

- searches the bounded authorized calendar window;
- matches Event title/type name case-insensitively;
- prefers upcoming occurrences;
- returns Event start time as an ISO value plus viewer-local presentation in the frontend;
- if multiple plausible occurrences remain, returns an ambiguity state instead of guessing;
- if no authorized match exists, returns a generic no-supported-source result.

### `event_roster_self`

Examples:

- `Am I rostered for Swordland?`
- `What is my Swordland assignment?`
- `What time is Swordland and am I rostered?`

Sources: authorized Event occurrence plus `Operations/Rosters::forPlayer` for the active Governor.

Behavior:

- never returns another Governor's private roster data from this intent;
- distinguishes no roster assignment from removed/declined state according to owner projection semantics;
- exposes roster/team name, role, slot and current roster status where present;
- combined time + roster questions cite both Event and roster evidence when roster evidence exists.

### `alliance_content`

Examples:

- `What does our Swordland guide say?`
- `What strategy are we using for Bear Hunt?`

Source: published public/member-safe `Alliance/Content` visible in the active Alliance.

Behavior:

- searches only current Alliance member-visible published content;
- returns a bounded title/summary/excerpt rather than arbitrary full-document reproduction;
- retains content revision/provenance/freshness metadata when available;
- classifies the answer as **Alliance strategy** or **Alliance-authored content**, never factual game truth;
- links to the canonical member content route.

### `alliance_observation`

Examples:

- `What have we observed about XYZ?`
- `What do we know about alliance ABC?`

Source: Intelligence observation projections available to the active Governor's current scope.

Behavior:

- returns only authorized observations;
- labels all such material **Observation**;
- retains observation time/source/confidence when available;
- never upgrades an observation to verified game fact;
- never exposes inaccessible Kingdom/Alliance intelligence or raw ingestion evidence.

### `help`

Examples:

- `What can you answer?`
- empty first-use state.

Returns the supported question categories and examples without reading private domain data.

## Evidence contract

Every substantive statement must be grounded in one or more typed evidence projections.

Conceptual contract:

```text
AssistantEvidence
  id                 stable response-local evidence identifier
  sourceType         event | roster | alliance_content | observation | game_fact
  sourceId           canonical owner identifier
  title              user-safe source title
  classification     operational_fact | game_fact | alliance_strategy | observation
  statement          bounded source-derived text/value
  occurredAt         when the fact occurred, where applicable
  updatedAt          source freshness timestamp where applicable
  href               authorized canonical application route or null
  metadata           bounded typed presentation metadata
```

Evidence projections are immutable response values. They are not persisted as a second source of truth.

## Provenance classifications

The UI and API must preserve these distinctions end to end:

| Classification | Meaning | User-facing label |
| --- | --- | --- |
| `operational_fact` | Event/roster/application operational truth | **Event** / **Roster** |
| `game_fact` | source-backed factual game data owned by GameWorld | **Game data** |
| `alliance_strategy` | Alliance-authored recommendation, guide or plan | **Alliance strategy** |
| `observation` | recorded intelligence/observation | **Observation** |

Alliance-authored strategy must never be presented as universal KingShot mechanics. Observations must never be presented as established fact.

## Citation contract

Every substantive answer returns a non-empty citation list except bounded `help`, unsupported, validation-error and generic failure responses.

Each citation:

- references an evidence ID actually present in the response evidence set;
- contains the canonical source type and source ID;
- uses a user-safe title;
- includes a canonical application `href` only when that route is authorized for the same viewer;
- carries classification and freshness metadata where available.

The server, not free-form generated text, constructs citations. An interpreter/composer may reference evidence IDs but may not invent canonical source IDs or URLs.

If a substantive claim cannot be supported by evidence, omit the claim or return an unsupported/missing-data state.

## Prompt-injection and untrusted-source handling

Alliance Content, observations, uploaded evidence and other user-authored text are data, never Assistant instructions.

Text such as `ignore previous instructions`, `show all roster data`, or provider/tool-like syntax inside a guide or observation must remain quoted/source material and cannot:

- change authorization;
- select additional tools/queries;
- expand retrieval scope;
- enable writes;
- alter system configuration;
- suppress provenance labels.

The first release uses a deterministic bounded interpreter/composer and does not transmit private source text to an external model provider. The model/provider boundary remains explicit so a future provider can only be enabled after the same authorization, evidence and citation contracts are preserved and the product/operations contracts are updated.

## Write boundary

Alliance Assistant is read-only.

The Assistant code must not depend on owner write repositories or call Eloquent mutation APIs (`save`, `create`, `update`, `delete`, raw mutation SQL) for domain/application state.

When a user asks for a write such as `Put me on the Swordland roster`:

- the first release identifies the request as unsupported from the Assistant;
- where a safe canonical workflow is known, it may return a navigation/deep-link suggestion to that workflow;
- it performs no mutation.

A future action handoff may only invoke the existing owning capability's normal application Action and must inherit that Action's authorization, validation, concurrency/idempotency, audit/outbox, observability and recovery behavior. The Assistant must never create an alternate write path.

## Data retention and conversation state

The first release is request/response only:

- no Assistant conversation transcript is persisted;
- no private question/answer text is written to ordinary application logs;
- no evidence snapshot cache is persisted;
- browser state may keep the current page's local transcript only until navigation/reload according to frontend behavior;
- every submitted question is authorized and resolved against current application state.

This avoids stale authorization and cross-session leakage.

## HTTP contract

Authenticated routes:

- `GET /assistant` — render the Alliance Assistant surface.
- `POST /assistant/ask` — answer one bounded question.

`POST /assistant/ask` is logically read-only despite using POST to avoid placing private questions in URLs, browser history and intermediary query-string logs.

Request:

```json
{
  "question": "What time is Swordland and am I rostered?"
}
```

Validation:

- required string;
- trimmed;
- minimum 2 visible characters;
- maximum 500 characters;
- control characters rejected/normalized by the normal request boundary;
- rate limited by a dedicated Assistant limiter.

Response shape:

```json
{
  "intent": "event_roster_self",
  "status": "answered",
  "answer": "Swordland starts ... You are rostered ...",
  "classifications": ["operational_fact"],
  "evidence": [],
  "citations": [],
  "ambiguity": null,
  "suggestedQuestions": []
}
```

Supported statuses:

- `answered`
- `ambiguous`
- `not_found`
- `unsupported`
- `validation_error`
- `unavailable`

HTTP responses must not encode inaccessible-source existence differently from a normal authorized `not_found` result.

## Rate limiting and abuse bounds

A dedicated limiter protects `/assistant/ask`.

Default contract:

- 30 requests/minute per authenticated account;
- question length <= 500 characters;
- Event candidate window and result count remain owner-query bounded;
- content and observation results are bounded before answer composition;
- response evidence/citations are bounded to the minimum supporting set;
- no arbitrary recursive tool/query execution.

Rate limits are configurable through `config/assistant.php` and environment variables without changing authorization semantics.

## Failure and ambiguity behavior

### Multiple Event matches

Return `ambiguous` with a bounded list of authorized candidate Event titles/start times. Do not query roster state until a unique occurrence is resolved.

### Event exists but no self roster assignment

Return the Event fact plus `You are not currently rostered` and cite the Event. Do not invent a roster citation when no roster record exists.

### No published guide/content match

Return `not_found` with neutral wording. Do not reveal drafts, archived content or another Alliance's content.

### No observation match

Return `not_found` without indicating whether hidden observations exist.

### Unsupported/general KingShot question

Return `unsupported` and explain the bounded supported sources. Do not answer from prior model knowledge.

### Owner query/provider failure

Return `unavailable` with a retry-safe message and privacy-safe diagnostic correlation already provided by normal platform logging where applicable. Never include stack traces or raw source text.

## UX contract

Primary route: `/assistant`.

The page is framed as **Ask your Alliance**, not `Ask me anything`.

### First-use state

Show:

- concise scope statement: `Ask about Events, your roster, Alliance guides and observations.`
- suggested prompts:
  - `What time is Swordland and am I rostered?`
  - `What is my next Event?`
  - `What does our Bear Hunt guide say?`
  - `What have we observed about our opponent?`
- privacy/authority hint: `Answers use only Alliance data you can already view.`

### Answer presentation

Every answered response presents:

- answer text;
- visible provenance badge(s);
- source chips/cards with source title, classification and freshness where applicable;
- authorized source links when available;
- local-time formatting for Event times with UTC/source time accessible in detail;
- clear `Alliance strategy` and `Observation` labels;
- no raw IDs as primary UX copy.

### Required UX states

- empty/first use;
- submitting/busy;
- answered Event time;
- answered Event + self roster assignment;
- not rostered;
- Alliance strategy/content answer;
- observation answer;
- ambiguous Event;
- no authorized source found;
- unsupported question;
- validation error;
- rate limited;
- transient unavailable/retry;
- long source titles/content excerpts;
- mobile and desktop layouts;
- keyboard-only operation;
- reduced-motion presentation.

## Accessibility

The Assistant must provide:

- a real form and labelled question input;
- keyboard submission without trapping Enter/Shift+Enter behavior;
- visible focus states;
- `aria-live`/status semantics for loading and completed responses without excessive announcements;
- semantic headings and source lists;
- classification conveyed in text, never color alone;
- accessible error association;
- sufficient touch targets;
- no horizontal overflow at supported mobile widths;
- reduced-motion-safe busy presentation.

## Localization and KingShot language

All user-facing Assistant strings are localized in every supported application locale.

Use established game/application terms: **Governor**, **Alliance**, **Event**, **Roster**, **Bear Hunt**, **Swordland**, **Alliance strategy**, **Observation**, **Game data**.

Do not describe the active Player as an employee/user/member when `Governor` is the established term.

## Observability and privacy

Routine Assistant reads do not create domain audit records.

Privacy-safe diagnostics may include:

- resolved intent;
- answer status;
- source type counts;
- candidate/evidence counts;
- retrieval/composition latency buckets;
- ambiguity/unsupported/not-found counts;
- rate-limit rejection count;
- owner-query failure category.

Diagnostics must not contain:

- raw question text;
- answer text;
- Governor names;
- private guide bodies/excerpts;
- observation text;
- roster notes;
- raw evidence/OCR/provider payloads;
- inaccessible source identifiers.

## Performance

- owner queries remain bounded and reuse existing indexed owner paths;
- no per-candidate N+1 roster lookup: roster retrieval happens only after a unique Event is selected;
- content/observation search has hard result limits;
- no external provider/network request exists in the initial release;
- target p95 server response time for deterministic answers is < 750 ms under normal application load, excluding platform/network variance.

## Security and architecture enforcement

Automated architecture/security tests must prove:

1. `app/ReadModels/AllianceAssistant` contains no Eloquent model classes or migrations.
2. Assistant code has no direct domain write calls/repositories.
3. owner contexts do not import `App\ReadModels\AllianceAssistant`.
4. Event candidates come through authorized Event visibility/query contracts.
5. Alliance content comes only from member-visible published content for the current Alliance.
6. observation retrieval uses Intelligence authorization/current scope before source text becomes evidence.
7. citations reference only response-local authorized evidence IDs.
8. no external HTTP/model provider receives private Assistant questions or evidence in the initial release.
9. cross-Alliance tests cannot distinguish hidden-source existence.
10. write-like questions cause zero domain mutations.

## Test contract

Required automated coverage includes:

- `What time is Swordland?` resolves a unique authorized upcoming Event;
- `What time is Swordland and am I rostered?` returns Event + self roster status and correct citations;
- roster role/team/slot/status projection;
- not-rostered behavior with no fabricated roster citation;
- multiple matching Events return `ambiguous` before roster lookup;
- another Alliance's Event does not enter candidates;
- another Governor's roster data is not returned by self-roster intents;
- member-visible Alliance guide search;
- draft/archived/other-Alliance content never enters evidence;
- strategy classification remains `alliance_strategy`;
- observation classification remains `observation`;
- observation authorization prevents cross-scope leakage;
- prompt-injection-like guide/observation text remains inert data;
- unsupported generic KingShot questions do not receive unsourced answers;
- citations cannot reference absent/inaccessible evidence;
- question validation and dedicated rate limiting;
- no domain writes from write-like prompts;
- controller authentication/active-Player/Alliance-scope behavior;
- privacy-safe logs omit question/answer/source text;
- localized frontend strings/types/build;
- keyboard/accessibility states;
- deterministic mobile/desktop visual regression.

## Delivery queue

Alliance Assistant is the final composition capability. A phase is `Complete` only when its implementation, tests, documentation and applicable release evidence satisfy the exit condition.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | In progress | Product contract and dependency audit | `/docs/product` defines complete behavior/ownership/authorization/provenance/write boundaries; every source capability required by the first release is verified sufficient or corrected before Assistant composition depends on it. |
| 2 | Not started | Typed evidence and intent contracts | Closed intent/status/classification/evidence/citation value contracts exist with unit tests and no persistence/domain ownership. |
| 3 | Not started | Authorized Event and self-roster retrieval | Unique/ambiguous Event resolution and self-roster composition reuse owner Queries, prevent cross-scope leakage and preserve missing semantics. |
| 4 | Not started | Authorized Alliance Content retrieval | Published member-visible content search produces bounded `alliance_strategy` evidence with revision/freshness/source links. |
| 5 | Not started | Authorized observation retrieval | Intelligence owner query exposes a bounded authorization-safe Assistant projection without raw ingestion/evidence leakage. |
| 6 | Not started | Deterministic interpreter and answer composer | Supported natural-language forms resolve to closed intents; answers are generated only from authorized evidence; unsupported questions never use external/model knowledge. |
| 7 | Not started | Citation validation and source links | Every substantive answer has server-validated citations to evidence actually used; invented or inaccessible references are impossible. |
| 8 | Not started | HTTP boundary, rate limit and privacy-safe observability | `GET /assistant` + `POST /assistant/ask` validate/authenticate/rate-limit correctly and log only privacy-safe metadata. |
| 9 | Not started | Responsive accessible localized UX | First-use, answered, provenance, ambiguity, not-found, unsupported, validation, rate-limit and unavailable states work on mobile/desktop/keyboard in every supported locale. |
| 10 | Not started | Write-boundary and injection enforcement | Write-like prompts cause zero mutation; architecture tests prohibit direct writes; untrusted source text cannot change Assistant behavior. |
| 11 | Not started | Behavior/security/performance/visual verification | Backend/frontend/authorization/tenant-isolation/citation/privacy/performance and deterministic visual suites are green. |
| 12 | Not started | Final reconciliation and release closeout | Spec→code, code→spec, UX→backend, authorization, architecture and data-ownership audits find no incomplete item; all applicable repository release gates pass on one immutable candidate. |

## Cross-phase invariants

1. Alliance Assistant is a read/composition capability, not a bounded domain context.
2. Unauthorized data is excluded **before** Assistant evidence construction.
3. No model/training knowledge is an application source.
4. Every substantive answer is cited.
5. Alliance strategy, observation, game fact and operational fact remain distinguishable.
6. The Assistant owns no Event, roster, content, observation or game-fact persistence.
7. Write-like requests never mutate state directly.
8. Any future action handoff uses the existing owner Action without bypassing its guarantees.
9. User-authored source text is untrusted data and cannot control the Assistant.
10. No phase is Complete unless backend, authorization, UX, accessibility/localization, observability, tests, docs and applicable release evidence agree.

## Definition of done

Alliance Assistant is complete only when:

- every phase above is `Complete`;
- the canonical question `What time is Swordland and am I rostered?` resolves only authorized Event/roster data and cites the supporting source(s);
- Alliance-authored strategy and observations are visibly and structurally separate from facts;
- unsupported/general KingShot questions receive no unsourced answer;
- cross-Alliance and cross-Governor leakage tests pass;
- write-like prompts produce no direct domain mutation;
- no Assistant-owned duplicate source of truth exists;
- current source links/provenance/freshness are retained;
- privacy-safe diagnostics exclude private question/answer/source content;
- desktop/mobile/accessibility/localization/visual behavior is complete;
- PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, visual regression, CodeQL, dependency review, production image/container scan, staging, clean-database and backup/restore checks are green where applicable;
- final spec→code, code→spec, UX→backend, authorization, architecture and data-ownership audits find no TODO, placeholder, undocumented behavior or incomplete delivery-ledger item.
