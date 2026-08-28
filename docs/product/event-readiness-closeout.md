# Event Command — Readiness & Closeout

Status: Current complete capability — verified 2026-08-25

Date: 2026-08-25

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
3. the most recent ended occurrence that still requires closeout **within the bounded automatic closeout search window**;
4. the next upcoming non-cancelled occurrence;
5. the most recent occurrence;
6. no occurrence, producing an explicit unavailable/empty state rather than invented readiness.

The automatic unresolved-closeout search is presentation/query-budget policy, not owner truth. It inspects at most the **12 most recent ended, non-cancelled occurrences**. This prevents the Event Management read path from composing an unbounded historical series. Older occurrences remain visible in the occurrence selector and can be explicitly selected; explicit selection always composes that authorized occurrence regardless of age.

Changing the automatic search window is an EventManagement presentation/performance policy change and requires this contract and its behavior/query-budget tests to change together.

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

The readiness horizon is presentation policy owned by EventManagement, must be typed/tested and must not hide blockers. The initial default is seven days before occurrence start unless an existing repository policy provides a more specific typed value. A blocking item always moves an upcoming occurrence to `needs_attention`; warning and informational items remain visible while state is `planning` or `ready`.

## Projection contract

The backend exposes a typed server-built projection with this semantic shape:

```text
EventCommandProjection
  eventId
  selectedOccurrenceId|null
  occurrences[]                    # navigation summaries only
  state|null                       # derived lifecycle; null for no occurrence/cancelled
  eventStatus
  occurrenceStatus|null
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

Derive selected occurrence identity, valid start/end ordering, valid IANA Event timezone, cancellation truth and Event-owned schedule validity. Canonical handoff: Event schedule/settings section.

### Participation — `Operations/Participation`

Where supported, consume owner-defined registration state, eligible responder count, response count, unanswered Governors, registration/confirmed counts, capacity, waitlist state and owner inconsistencies. Zero responses is complete only when the owner proves zero eligible responders or response collection is not applicable. Canonical handoff: Participation/attendance section.

### Polls — `Operations/Polls`

Where planning polls apply, consume owner-defined unresolved planning state. Event Command must not invent a concept of “required poll” that Polls/Event capability configuration does not own. Canonical handoff: Polls section.

### Rosters — `Operations/Rosters`

Consume roster count, owner capacity/slot semantics, filled/unfilled state, unassigned Governors and owner warnings/conflicts while preserving declined/removed semantics. Canonical handoff: Roster section.

### Battle plan — `Operations/BattlePlans`

Consume objective and assignment coverage, planned Governors without assignment and owner-invalid assignment targets. Event Command does not reproduce assignment policy. Canonical handoff: Battle Plan section.

### Alliance strategy — `Alliance/Content`

For an Alliance-scoped Event, consume authorized published Event-linked guidance, revision identity and Content-owned freshness. Missing or stale strategy is non-blocking unless future product policy explicitly changes that rule. Every item is labelled/classified **Alliance strategy**, never game truth. Canonical handoff: Alliance Content.

### Territory — `Operations/TerritoryPlanning`

For applicable non-Player scope, consume the immutable Event-attached published revision, owner validation/violation/warning state and whether a newer mutable draft differs. A newer draft never silently changes the Event reference. No attached Territory revision is `not_applicable` unless the owner/event contract makes one applicable. Canonical handoff: Event Territory section/planner.

### Communications — Reminders + `Communications`

Reminder configuration/scheduling policy remains owned by `Operations/Participation/Reminders`; provider delivery state remains owned by Communications. Event Command consumes both separately. Missing required reminder configuration blocks readiness. Failed deliveries block; pending/queued delivery is warning and is never represented as delivered; sent delivery is complete. Canonical handoffs: reminder configuration and delivery recovery/inspection workflow.

### Rallies — `Operations/Rallies`

Where Rally capability applies, consume owner-defined Rally plan coverage and warnings. Events without the capability do not acquire Rally blockers. Canonical handoff: Rally planning section.

## Post-Event closeout dimensions

After an occurrence ends, Event Command evaluates only applicable closeout dimensions.

### Attendance — `Operations/Participation`

Distinguish attended/absent/excused from genuinely unrecorded attendance. Unrecorded applicable Governors block closeout; zero eligible Governors may legitimately be complete when the owner proves that state.

### Rally actual participation — `Operations/Rallies`

Keep pre-Event Rally planning separate from post-Event actual participation. Missing owner-required actual participation blocks closeout; owner warnings remain owner truth.

### Results — `Operations/Results`

Consume missing/incomplete summary/player results and explicit correction state according to Results-owned semantics. A zero score is not missing. If the Results owner does not expose an explicit unresolved-correction workflow, Event Command says so rather than inferring one from timestamps or value differences.

### Evidence — `Intelligence/Evidence`

Consume only typed occurrence evidence workflows. The currently implemented typed Event Evidence destination contract is Bear Hunt; other Event types do not invent evidence requirements. For supported evidence, represent processing, awaiting review, unmatched Governor, destination commit pending, processing/commit failure and committed terminal state. Evidence remains provenance/workflow state and never substitutes for accepted Results/Rally/Participation truth.

Evidence retrieval is intentionally bounded, but the bound must never create false completion. The owner projection must explicitly report whether its reviewed Evidence window covers the complete applicable occurrence set. If the bound is exceeded, Event Command represents Evidence coverage as `unknown` with blocking severity and keeps the occurrence in `closeout_required` until complete coverage can be established. Truncated Evidence must never be interpreted as zero, clear or complete.

### Debrief — `ReadModels/EventAnalysis`

Consume bounded Debrief availability. Availability is informational: closeout does not require a coordinator to open the Debrief. If analysis cannot be produced because owner data is incomplete, report that explicitly without duplicating analysis rules.

## Canonical handoffs and write boundary

Every blocking/warning item that a coordinator can act on includes a canonical navigation-only handoff. Handoffs:

- use stable owner route/section anchors;
- never encode privileged actor state;
- never execute owner Actions;
- never bypass normal destination authorization;
- may be absent only when no legitimate correction workflow exists, in which case the unavailable action state remains explicit.

The destination owner performs normal authorization, validation, concurrency/idempotency, audit and recovery behavior. Event Command never writes domain state.

## UX contract

The selected occurrence gets an Event Command card near the top of Event Management. The card leads with derived lifecycle, blocker/warning count and the highest-priority actionable items, rather than a giant always-expanded checklist.

Before the Event, examples include:

```text
Needs attention — 3 items
4 roster slots unfilled
Battle plan has 2 unassigned Governors
Event reminder has not been scheduled
```

After the Event, examples include:

```text
Closeout required — 3 items
6 Governors have no attendance result
2 screenshots await review
Event results are missing
```

The UI explicitly handles no occurrence, cancellation, planning, needs attention, ready, active, closeout required, complete and partially unavailable owner projections. Complete/not-applicable items may be progressively collapsed, but blockers and warnings remain directly discoverable. Color is never the only signal. Counts, labels and accessible status semantics are required.

The occurrence switcher is keyboard-operable, preserves the selected occurrence in navigation and lets recurring occurrences be reviewed independently. Mobile layouts must not require horizontal scrolling to discover blockers/actions. All visible Event Command copy and owner/action labels use supported localization messages.

## Provenance and classification

Every composed item retains a canonical owner key. Concrete revision/receipt/reference identity is included only when it is useful and authorized.

Classifications remain distinct:

- `operational_fact` — Events/Participation/Polls/Rosters/BattlePlans/Rallies/Results/Communications/Territory operational state;
- `alliance_strategy` — Alliance-authored Content guidance;
- `evidence` — artifact/review/commit workflow state;
- `derived` — Event Command lifecycle/counts only.

Event Command never promotes Alliance strategy or Evidence into factual game truth.

## Failure and recovery semantics

A failed owner projection cannot make readiness/closeout look complete. The owner read boundary converts a failed applicable owner read into explicit `unknown`; blocking requirements remain blocking. Diagnostic telemetry identifies the owner key without logging private source bodies or payloads.

Recovery occurs through the canonical owner workflow. Event Command itself has no retry/replay write path beyond normal page/query retry.

## Query/performance contract

The composition avoids per-Governor/per-delivery/per-artifact N+1 behavior and prefers one bounded occurrence-level projection from each applicable owner.

The selected-occurrence Event Command has a query-budget regression test proving query count does not grow with Governor population and remains within the reviewed ceiling. Default unresolved-closeout selection is additionally bounded to the 12 most recent ended non-cancelled occurrences so a recurring Event cannot trigger unbounded historical cross-context composition. Explicit selection remains available for older authorized occurrences.

Owner-specific result bounds must expose coverage completeness. EventManagement may consume bounded summaries, but when an applicable owner cannot prove that its bound covers the complete relevant set, the composed requirement is `unknown` and blocking rather than inferred complete. Bounds are a performance mechanism, never a permission to hide unresolved work.

Expensive cross-context data not required by the Event Command card is not loaded merely because it exists elsewhere on Event Management.

## Observability

Repository-standard structured diagnostics make it possible to diagnose:

- Event Command render/composition;
- derived state;
- blocker/warning counts;
- owner projection unavailable/failure by owner key;
- composition duration and query-budget regressions.

Diagnostics use safe identifiers only and do not log private Evidence contents/OCR, provider payloads, private guide bodies or other sensitive source material.

## Acceptance criteria

The program-level `ER-01`–`ER-12` criteria remain mandatory. This contract expands them as follows.

- **EC-01 Occurrence scope:** every readiness/closeout result belongs to one authorized Event occurrence; recurring occurrences can have independent states.
- **EC-02 Selection:** explicit/current/bounded-closeout/upcoming/recent occurrence precedence is deterministic and tenant-safe; the automatic closeout search examines at most the 12 most recent ended non-cancelled occurrences and older authorized occurrences remain explicitly selectable.
- **EC-03 Derived only:** no readiness/complete/lifecycle/count persistence or write endpoint exists.
- **EC-04 Lifecycle:** `planning|needs_attention|ready|active|closeout_required|complete` follows documented precedence; cancellation remains Event truth.
- **EC-05 Capability awareness:** a disabled/non-applicable capability never creates a blocker.
- **EC-06 Explicit uncertainty:** missing/unavailable owner state or incomplete bounded coverage is unknown, not zero/complete; applicable blocking unknowns block readiness/closeout.
- **EC-07 Owner provenance:** every item carries a canonical owner and appropriate classification/source identity.
- **EC-08 Handoffs:** actionable items navigate only to canonical owner workflows and never mutate from Event Command.
- **EC-09 Schedule:** occurrence/timezone/cancellation validity is derived from Events.
- **EC-10 Participation:** response/registration/capacity/waitlist and attendance semantics come from Participation and distinguish missing from zero.
- **EC-11 Polls:** planning poll completeness is owner/capability-defined; no invented required-poll rule exists in the ReadModel.
- **EC-12 Rosters:** roster/slot/assignment/warning state comes from a bounded Rosters projection.
- **EC-13 BattlePlans:** objective/assignment coverage comes from a bounded BattlePlans projection; EventManagement does not reimplement assignment policy.
- **EC-14 Strategy:** only authorized published Event-linked Alliance Content is considered; freshness is Content-owned and labelled Alliance strategy.
- **EC-15 Territory:** the immutable Event-attached published revision remains authoritative for that occurrence; newer drafts are informational only.
- **EC-16 Communications:** reminder policy and Communications delivery health remain separate owner truths; queued is not delivered; failure recovery deep-links to owner workflow.
- **EC-17 Rallies:** plan readiness and actual participation remain distinct owner projections and are capability-aware.
- **EC-18 Results:** missing/incomplete/correction state comes from Results and zero values are not treated as missing.
- **EC-19 Evidence:** typed Evidence processing/review/matching/commit failure state is occurrence-scoped, cannot replace accepted destination truth, and bounded Evidence retrieval explicitly fails closed as `unknown` when complete coverage cannot be proven.
- **EC-20 Debrief:** EventAnalysis availability is composed without requiring a user to open the Debrief before closeout can complete.
- **EC-21 UX:** desktop/mobile/keyboard/screen-reader UX exposes primary blockers/actions without wide-table dependence or color-only meaning.
- **EC-22 Localization:** all visible Event Command copy and action/owner labels use supported locale messages.
- **EC-23 Isolation:** forged Event/occurrence/Alliance/Kingdom/Player identities cannot cause unauthorized owner retrieval or source leakage.
- **EC-24 Performance:** query-budget tests prove bounded owner retrieval, no per-member query growth, bounded recurring-history automatic closeout selection and fail-closed coverage semantics for owner result bounds.
- **EC-25 Observability:** failures and derived state are diagnosable without logging private source contents.
- **EC-26 Reuse:** the projection is a bounded server contract that Command Overview/Alliance Assistant may consume later without copying business rules/state.
- **EC-27 Architecture:** owner contexts do not import EventManagement; no new readiness/closeout bounded context exists; architecture tests enforce the boundary.
- **EC-28 Verification:** PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, visual regression, CodeQL, dependency review, container/release/staging/backup gates pass as applicable on one immutable candidate.

## Delivery ledger

No row may be marked complete based on scaffolding or backend-only implementation.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Product contract | Canonical contract defines ownership, occurrence semantics, lifecycle, uncertainty, UX, ACs and ledger. |
| 1 | Complete | Event Command foundation | Typed projection/item/status contracts, authorized occurrence selection, derived lifecycle/cancellation and stable handoff schema are implemented and behavior-tested. |
| 2 | Complete | Schedule + Participation + Polls | Bounded owner projections and capability-aware pre-Event composition are implemented with missing/zero semantics. |
| 3 | Complete | Rosters + BattlePlans | Owner-backed coverage/blocker summaries and handoffs are implemented. |
| 4 | Complete | Alliance strategy + Territory | Published Event-linked Content freshness/classification and immutable Territory revision semantics are implemented. |
| 5 | Complete | Reminders + Communications | Reminder configuration plus occurrence delivery health/failure semantics and recovery handoffs are implemented. |
| 6 | Complete | Rally readiness | Capability-aware Rally planning projection is implemented. |
| 7 | Complete | Closeout Participation + Rallies | Attendance and actual Rally participation closeout composition is implemented. |
| 8 | Complete | Results + Evidence | Results semantics and bounded Evidence coverage are implemented; Evidence explicitly fails closed as blocking `unknown` when the 200-artifact review window cannot prove complete coverage. |
| 9 | Complete | Debrief availability | Bounded EventAnalysis availability is integrated without duplicated analysis rules. |
| 10 | Complete | Event Command UX | Card, occurrence switcher, lifecycle states, stable deep links, responsive/accessibility/localization behavior and deterministic desktop/mobile visual fixtures are implemented. |
| 11 | Complete | Performance + observability + architecture | query budget, safe diagnostics, isolation and boundary enforcement are implemented. |
| 12 | Complete | Verification + reconciliation | PR #124 hardening candidate `c9d5a09b48e132f811df896eef7d8721f8d35c66` passed CI, PHP/Pint/PHPStan, frontend checks/build, Architecture V3, Visual Regression, CodeQL, Dependency Review, Intelligence Verification, production image, ephemeral staging, backup/restore and image scan; final documentation reconciliation follows on a docs-only verified head. |

## Definition of Done

Event Readiness & Closeout is a **Current complete capability**. The occurrence-scoped Event Command composition, pre-Event readiness and post-Event closeout flows, bounded owner projections, fail-closed Evidence coverage, authorization/isolation including forged-occurrence HTTP rejection, canonical navigation-only handoffs, responsive/accessibility/localization UX, observability, query-budget coverage, automated/architecture/contract/visual tests, static/security gates, production image, ephemeral staging, backup/restore and documentation reconciliation are complete. The delivery ledger contains no incomplete Event Readiness or Event Closeout items, and no derived Event Command truth is persisted.