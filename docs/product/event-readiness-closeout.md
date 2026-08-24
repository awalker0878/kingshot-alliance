# Event Command — Readiness & Closeout

Status: Selected extension — implementation contract

Date: 2026-08-24

This document is the implementation source of truth for Event Readiness and Event Closeout. The capability is an occurrence-scoped cross-context composition owned by `app/ReadModels/EventManagement`; it is not a new bounded context, aggregate, Event state machine or write path.

A delivery item is complete only when its owner behavior, bounded read contract, authorization/isolation, missing-data semantics, derived composition, canonical workflow handoff, responsive/accessibility/localization UX, observability, automated tests, architecture/contract/query-budget coverage, visual regression where applicable, documentation reconciliation and repository release gates are complete.

If implementation reveals a missing rule, edge case, owner-query deficiency, authorization gap, recovery condition or better product behavior, update this contract before closing the affected ledger row.

## Product outcome

An authorized Event coordinator can answer two operational questions from one Event Command surface:

1. **Before the occurrence:** what prevents this Event occurrence from being operationally ready?
2. **After the occurrence:** what work remains before operational closeout is complete?

The surface explains blockers and warnings, identifies the canonical owner of every fact, and deep-links to the normal owner workflow. Event Command itself performs no domain writes.

The same bounded projection is intentionally reusable later by Command Overview and Alliance Assistant so they can answer questions such as “Is Swordland ready?” without either consumer becoming authoritative.

## Non-goals and prohibited implementation

Do not create `EventReadiness`, `EventCloseout`, `EventCommand` or similar top-level bounded contexts merely to compose existing owner state.

Do not persist any derived Event Command truth, including:

- `event_ready`;
- `event_complete`;
- readiness/closeout lifecycle state;
- blocker or warning counts;
- derived section completion;
- a second copy of owner facts.

Do not add write endpoints such as `mark-ready`, `complete-closeout` or `fix-readiness`. Every mutation remains in the canonical owner workflow and re-runs that owner's authorization, validation, concurrency/idempotency, audit and recovery behavior.

Do not encode readiness rules in Vue components, controllers, jobs or bots. Owner policy belongs to owners; cross-context derivation belongs to typed `ReadModels/EventManagement` composition.

## Capability ownership

| Dimension | Canonical owner | Event Command responsibility |
| --- | --- | --- |
| Event/occurrence identity, scope, schedule, cancellation | `Operations/Events` | authorize Event first; select an authorized occurrence; compose schedule status |
| registration, response, capacity, waitlist, attendance | `Operations/Participation` | consume bounded readiness/closeout projection |
| planning polls | `Operations/Polls` | consume bounded planning completion projection when the capability applies |
| roster structure, slots, assignments, declined/removed state | `Operations/Rosters` | consume bounded roster readiness projection |
| objectives and assignments | `Operations/BattlePlans` | consume bounded Battle Plan readiness projection |
| Rally plans and recorded participation | `Operations/Rallies` | consume pre-Event plan and post-Event actual-participation projections |
| Event Results and correction state | `Operations/Results` | consume closeout projection |
| desired territory plan and immutable Event-attached published revision | `Operations/TerritoryPlanning` | consume referenced revision validity and draft-difference information without changing the Event reference |
| Event-linked published Alliance strategy/guidance | `Alliance/Content` | consume published revision and Content-owned freshness state; label as Alliance strategy |
| Event reminder rule/scheduling policy | `Operations/Participation/Reminders` | consume reminder configuration/scheduling state |
| delivery attempts, provider state and retryability | `Communications` | consume bounded occurrence delivery-health projection; queued is not delivered |
| uploaded screenshots, extraction/review/matching/commit attempts and receipts | `Intelligence/Evidence` | consume bounded occurrence closeout projection only |
| Debrief/analysis availability | `ReadModels/EventAnalysis` | consume a bounded availability summary; Event Command does not duplicate analysis |
| cross-context composition and presentation lifecycle | `ReadModels/EventManagement` | derive the Event Command projection only |

Owner contexts must not import `ReadModels/EventManagement`.

## Authorization boundary

Authorization occurs before cross-context retrieval.

Required flow:

```text
current authenticated User
 -> current active PlayerReference
 -> authorize concrete Event through Operations/Events
 -> resolve one occurrence that belongs to that authorized Event
 -> invoke bounded owner queries with authorized scalar IDs/value objects
 -> compose Event Command projection
```

Prohibited flow:

```text
load broad Alliance/Kingdom owner data
 -> compose candidates
 -> filter unauthorized rows afterward
```

A crafted occurrence ID from another Event, Alliance, Kingdom or Player scope must not enter owner queries. Deep links contain navigation identity only; destination workflows perform their normal authorization again.

## Occurrence-centric model

Readiness and closeout belong to an Event occurrence, not to the recurring Event as a whole. Different occurrences of one recurring Event may legitimately be in different presentation states at the same time.

### Selected occurrence precedence

The Event Management route may accept an optional occurrence selector. Selection is deterministic:

1. an explicitly requested occurrence when it belongs to the already-authorized Event;
2. an occurrence that is currently active;
3. the most recent ended occurrence that still requires closeout;
4. the next upcoming non-cancelled occurrence;
5. the most recent occurrence;
6. no occurrence, producing an explicit unavailable/empty state rather than invented readiness.

The implementation may avoid eagerly composing every occurrence. It must be possible to switch occurrences without changing owner truth.

### Cancellation

Cancellation remains underlying `Operations/Events` truth. A cancelled occurrence is presented as **Cancelled** and readiness/closeout dimensions become `not_applicable`; Event Command must not call cancellation `complete` merely to fit the derived lifecycle.

## Derived presentation lifecycle

For a non-cancelled selected occurrence Event Command derives exactly these presentation states:

`planning | needs_attention | ready | active | closeout_required | complete`

They are recomputed from owner projections on every read and are never authoritative Event state.

### Lifecycle precedence

1. If the occurrence is active according to Event-owned schedule/status, presentation state is `active`.
2. If the occurrence has ended, state is `closeout_required` when one or more applicable blocking closeout items are unresolved or unknown; otherwise `complete`.
3. If the occurrence is upcoming, state is `needs_attention` when one or more applicable blocking readiness items are unresolved or unknown.
4. An upcoming occurrence with no blocking readiness items is `ready` when it is inside the configured readiness horizon; otherwise `planning`.

The readiness horizon is presentation policy owned by EventManagement configuration, must be typed/tested and must not hide blockers. The initial default is seven days before occurrence start unless an existing repository policy provides a more specific typed value. Blockers remain visible while state is `planning`.

## Projection contract

The backend exposes a typed server-built projection. Exact class names may follow repository conventions, but the semantic contract is:

```text
EventCommandProjection
  eventId
  selectedOccurrenceId|null
  occurrences[]                    # navigation summaries only
  state|null                       # derived lifecycle; null for no occurrence/cancelled
  eventStatus
  startsAt|null
  endsAt|null
  timezone
  blockerCount
  warningCount
  sections[]
```

Each section contains typed items. Every item exposes at least:

```text
code                              # stable machine key
phase                             # readiness|closeout
status                            # complete|needs_attention|warning|unknown|not_applicable
severity                          # blocking|warning|informational
owner                             # canonical owner key
classification                    # operational_fact|alliance_strategy|evidence|derived
count|null
messageKey                        # localization key, not arbitrary backend prose
messageParameters                 # bounded scalar values
source                            # bounded owner provenance/identity where useful
handoff|null                      # canonical navigation-only action
```

`blockerCount` and `warningCount` are computed response values, never persisted.

### Status semantics

- `complete` — the owner projection explicitly proves the applicable requirement is satisfied.
- `needs_attention` — the owner projection explicitly proves an applicable blocking requirement is unsatisfied.
- `warning` — actionable/non-blocking issue that does not prevent readiness/closeout.
- `unknown` — required owner state could not be established. For an applicable blocking requirement, unknown blocks readiness/closeout.
- `not_applicable` — the Event capability or lifecycle makes the dimension irrelevant.

Missing is never converted to zero or complete unless the owner can prove that zero is the correct complete value.

## Pre-Event readiness dimensions

Only applicable capabilities contribute blocking state.

### Schedule — `Operations/Events`

Derive:

- selected occurrence exists and belongs to Event;
- occurrence has valid start/end values;
- Event timezone is a valid IANA timezone;
- occurrence is not cancelled;
- schedule is internally valid according to Event-owned policy.

Canonical handoff: Event schedule/settings section.

### Attendance/registration — `Operations/Participation`

Derive, where supported:

- registration not configured/open/closed;
- eligible responder count;
- response count;
- unanswered Governors;
- registration/confirmed counts;
- capacity;
- waitlist count/state;
- owner-reported inconsistent/invalid participation state.

Zero responses is complete only when the owner proves zero eligible responders or response collection is not applicable.

Canonical handoff: Participation/attendance section.

### Polls — `Operations/Polls`

Where planning polls apply, derive owner-defined planning completeness, including draft/open/unresolved required planning polls. Event Command must not invent a concept of “required poll” that Polls/Event capability configuration does not own.

Canonical handoff: Polls section.

### Roster — `Operations/Rosters`

Derive, per applicable occurrence:

- required/available slots according to owner configuration;
- filled/unfilled slots;
- unassigned Governors;
- declined/removed assignments;
- owner assignment warnings/conflicts.

Canonical handoff: Roster section.

### Battle plan — `Operations/BattlePlans`

Derive:

- applicable objectives configured;
- assignment coverage;
- rostered/participating Governors without applicable assignment;
- assignments referencing owner-invalid targets;
- owner-defined plan warnings.

Event Command consumes a bounded owner summary rather than reproducing assignment policy.

Canonical handoff: Battle Plan section.

### Alliance strategy — `Alliance/Content`

For an Alliance-scoped Event where strategy applies, derive:

- published Event-linked guide exists;
- current published revision identity;
- Content-owned freshness state (`fresh`, due-soon, overdue or equivalent);
- optional newer draft indicator when the owner safely exposes it.

Content's existing source/review/revision provenance is preserved. The section and every item are classified and labelled **Alliance strategy**, never game data/game truth.

Canonical handoff: published Event guide/content management route as authorized.

### Territory — `Operations/TerritoryPlanning`

Where Territory applies, derive:

- applicable immutable published revision is selected for the occurrence;
- referenced revision remains readable/valid;
- validation warnings on the referenced revision;
- informational indication when a newer mutable draft differs from the Event's immutable referenced revision.

A newer draft never silently replaces or changes the historical Event reference.

Canonical handoffs: view Event revision and open Territory planner.

### Communications — Reminders + `Communications`

Derive separately:

- reminder rule/configuration exists and is enabled where required;
- reminder scheduling/materialization state according to the reminder owner;
- occurrence-scoped delivery health from Communications;
- failed/retryable/exhausted delivery counts where actionable.

Queued/pending is not delivered. Recipient inbox queries that expose only already-sent messages are not sufficient for coordinator readiness.

Canonical handoffs: reminder configuration and normal failed-delivery recovery workflow.

### Rallies — `Operations/Rallies`

Where Rally planning applies, derive owner-defined plan coverage such as configured groups/leads/joiners and owner warnings. Events without the Rally capability are `not_applicable`.

Canonical handoff: Rally planning section.

## Post-Event closeout dimensions

After an occurrence ends, Event Command evaluates applicable closeout dimensions.

### Attendance — `Operations/Participation`

Distinguish recorded attended/absent/excused from genuinely unrecorded attendance. Unrecorded applicable Governors block closeout.

### Rally actual participation — `Operations/Rallies`

Distinguish pre-Event Rally plans from post-Event recorded actual participation. Missing required actual participation blocks closeout; owner warnings remain owner truth.

### Results — `Operations/Results`

Derive missing/incomplete summary/player results/required metrics according to Results-owned semantics. A zero score is not missing.

### Evidence — `Intelligence/Evidence`

Consume a narrow occurrence-scoped summary capable of representing at least:

- pending/processing evidence;
- awaiting human review;
- unmatched Governor/identity resolution;
- destination commit pending;
- failed/recoverable destination commit;
- committed/rejected terminal evidence.

Evidence state is provenance/workflow state and never substitutes for accepted Results/Rally/Participation truth.

### Results corrections — `Operations/Results`

If the Results owner has explicit unresolved correction/review state, expose it. Event Command must not infer “unresolved correction” from timestamps or arbitrary differences. If the owner lacks the needed state, update this contract before adding owner behavior.

### Debrief — `ReadModels/EventAnalysis`

Expose Debrief availability from a bounded analysis summary. Initial behavior treats “Debrief available” as informational, not a blocking requirement to open/view a page. If analysis cannot yet be generated because required owner state is incomplete, show that explicitly without duplicating analysis rules.

## Handoffs

Every blocking/warning item that a coordinator can act on includes a canonical navigation-only handoff. Handoffs:

- use stable owner route/section anchors;
- never encode privileged actor state;
- never execute owner Actions;
- never bypass normal destination authorization;
- may be absent when the user cannot legitimately access a correction workflow, in which case the item remains visible with an explicit unavailable action state.

Event Management should provide stable section anchors for Schedule, Participation, Polls, Rosters, Battle Plan, Territory, Rallies and Results where those workflows are on the same page.

## UX contract

### Primary card

The selected occurrence gets an Event Command card near the top of Event Management.

Before the Event:

```text
Needs attention — 3 items
4 roster slots unfilled
Battle plan has 2 unassigned Governors
Event reminder has not been scheduled
```

After the Event:

```text
Closeout required — 3 items
6 Governors have no attendance result
2 screenshots await review
Event results are missing
```

### UX states

The UI explicitly handles:

- no occurrence available;
- cancelled occurrence;
- planning;
- needs attention;
- ready;
- active;
- closeout required;
- complete;
- owner projection partially unavailable/unknown.

Complete/not-applicable sections may be progressively collapsed, but blockers and warnings are visible without expanding a wide table. Color is never the only signal. Counts, text labels and accessible status semantics are required.

The occurrence switcher must be keyboard-operable and preserve the selected occurrence in navigation. Mobile layouts must not require horizontal scrolling to discover blockers/actions.

## Provenance and classification

Every composed item retains a canonical owner key. Where a concrete owner revision/receipt/referenced entity matters to explanation, the projection carries bounded source identity sufficient for navigation/explanation without leaking inaccessible data.

Classifications remain distinct:

- operational facts — Events/Participation/Polls/Rosters/BattlePlans/Rallies/Results/Communications/Territory operational state;
- Alliance strategy — Content-authored guidance;
- evidence — artifact/review/commit workflow state;
- derived — Event Command lifecycle/counts only.

Event Command never promotes Alliance strategy or evidence into game truth.

## Failure and recovery semantics

A failed owner projection cannot make readiness/closeout look complete. When an applicable owner query is unavailable, its item/section becomes `unknown`; blocking requirements remain blocking.

Recovery occurs through the canonical owner workflow. Event Command itself has no retry/replay write path except normal page/query retry.

## Query/performance contract

The composition must avoid per-Governor/per-delivery N+1 behavior. Prefer one bounded occurrence-level projection from each applicable owner.

The Event Management request receives a query-budget regression test. Adding Event Command must not produce query growth proportional to the number of Governors, assignments, evidence artifacts or deliveries for the selected occurrence.

Expensive cross-context data that is not needed for the initial Event Command card must not be loaded merely because it already exists elsewhere on Event Management.

## Observability

Use repository-standard structured observability for the derived read path. At minimum make it possible to diagnose:

- Event Command render/composition failures;
- derived state;
- blocker/warning counts;
- owner projection unavailable/failure by owner key;
- composition duration/query budget regression.

Do not log private evidence contents/OCR, provider payloads, private guide bodies or other sensitive source material.

## Acceptance criteria

The program-level `ER-01`–`ER-12` criteria remain mandatory. This contract expands them as follows.

- **EC-01 Occurrence scope:** every readiness/closeout result belongs to one authorized Event occurrence; recurring occurrences can have independent states.
- **EC-02 Selection:** explicit/current/closeout/upcoming/recent occurrence precedence is deterministic and tenant-safe.
- **EC-03 Derived only:** no readiness/complete/lifecycle/count persistence or write endpoint exists.
- **EC-04 Lifecycle:** planning/needs_attention/ready/active/closeout_required/complete follows the documented precedence; cancellation remains Event truth.
- **EC-05 Capability awareness:** a disabled/non-applicable capability never creates a blocker.
- **EC-06 Explicit uncertainty:** missing/unavailable owner state is unknown, not zero/complete; applicable blocking unknowns block readiness/closeout.
- **EC-07 Owner provenance:** every item carries a canonical owner and appropriate classification/source identity.
- **EC-08 Handoffs:** actionable items navigate only to canonical owner workflows and never mutate from Event Command.
- **EC-09 Schedule:** occurrence/timezone/cancellation validity is derived from Events.
- **EC-10 Participation:** response/registration/capacity/waitlist and attendance semantics come from Participation and distinguish missing from zero.
- **EC-11 Polls:** planning poll completeness is owner/capability-defined; no invented required-poll rule exists in the ReadModel.
- **EC-12 Rosters:** required/filled/unfilled/declined/removed/warning state comes from a bounded Rosters projection.
- **EC-13 BattlePlans:** objective/assignment coverage comes from a bounded BattlePlans projection; the ReadModel does not reimplement assignment policy.
- **EC-14 Strategy:** only authorized published Event-linked Alliance Content is considered; freshness is Content-owned and labelled Alliance strategy.
- **EC-15 Territory:** the immutable Event-attached published revision remains authoritative for that occurrence; newer drafts are informational only.
- **EC-16 Communications:** reminder policy and Communications delivery health remain separate owner truths; queued is not delivered; failure recovery deep-links to owner workflow.
- **EC-17 Rallies:** plan readiness and actual participation remain distinct owner projections and are capability-aware.
- **EC-18 Results:** missing/incomplete/correction state comes from Results and zero values are not treated as missing.
- **EC-19 Evidence:** processing/review/matching/commit failure state is occurrence-scoped and cannot replace accepted destination truth.
- **EC-20 Debrief:** EventAnalysis availability is composed without requiring a user to open the Debrief before closeout can complete.
- **EC-21 UX:** desktop/mobile/keyboard/screen-reader UX exposes primary blockers/actions without wide-table dependence or color-only meaning.
- **EC-22 Localization:** all visible Event Command copy and action labels use supported locale messages.
- **EC-23 Isolation:** forged Event/occurrence/Alliance/Kingdom/Player identities cannot cause unauthorized owner retrieval or source leakage.
- **EC-24 Performance:** query-budget tests prove bounded owner retrieval and no per-member/artifact/delivery query growth.
- **EC-25 Observability:** failures and derived state are diagnosable without logging private source contents.
- **EC-26 Reuse:** the projection is a bounded server contract that Command Overview/Alliance Assistant may consume later without copying business rules/state.
- **EC-27 Architecture:** owner contexts do not import EventManagement; no new readiness/closeout bounded context exists; architecture tests enforce the boundary.
- **EC-28 Verification:** PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, visual regression, CodeQL, dependency review, container/release/staging/backup gates pass as applicable on one immutable candidate.

## Delivery ledger

No row may be marked complete based on scaffolding or backend-only implementation.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Product contract | This canonical contract defines ownership, occurrence semantics, lifecycle, uncertainty, UX, ACs and ledger before application code changes. |
| 1 | Not started | Event Command foundation | Typed projection/item/status contracts, authorized occurrence selection, derived lifecycle/cancellation and stable handoff schema covered by tests. |
| 2 | Not started | Schedule + Participation + Polls | Bounded owner projections and capability-aware pre-Event composition complete with missing/zero semantics. |
| 3 | Not started | Rosters + BattlePlans | Owner-backed coverage/blocker summaries and handoffs complete. |
| 4 | Not started | Alliance strategy + Territory | Published Event-linked Content freshness/classification and immutable Territory revision semantics complete. |
| 5 | Not started | Reminders + Communications | Reminder configuration plus occurrence delivery health/failures/recovery handoffs complete. |
| 6 | Not started | Rally readiness | Capability-aware Rally planning projection complete. |
| 7 | Not started | Closeout Participation + Rallies | Attendance and actual Rally participation closeout projection complete. |
| 8 | Not started | Results + Evidence | Results completeness/correction and Evidence processing/review/match/commit failure projections complete. |
| 9 | Not started | Debrief availability | Bounded EventAnalysis availability integrated without duplicated analysis rules. |
| 10 | Not started | Event Command UX | Card, occurrence switcher, lifecycle states, stable deep links, responsive/accessibility/localization complete. |
| 11 | Not started | Performance + observability + architecture | query budget, diagnostics, isolation and boundary enforcement complete. |
| 12 | Not started | Verification + reconciliation | all tests/release gates green; product/architecture/reference/operations docs and global delivery ledger reconciled; no incomplete Event Readiness/Closeout item remains. |

## Definition of Done

Event Readiness & Closeout becomes a current complete capability only when every ledger row is complete on one immutable candidate and `ER-*`, `EC-*` and applicable program-wide `PX-*` criteria are satisfied. A missing owner query, failing test, architecture violation, inaccessible handoff, incomplete mobile/accessibility state, stale documentation or failed release gate is implementation work, not a reason to declare the capability complete.