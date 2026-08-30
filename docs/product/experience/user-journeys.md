# Primary user journeys

Status: Current

Implemented journeys are described in present tense. Journeys under **Capability Extension Program — journey contracts** carry an explicit program state; `Current complete capability` journeys are delivered, while `Selected extension` and `Evidence-gated extension` journeys remain implementation contracts until their delivery-ledger rows close.

## Account to game context

```text
Register/sign in
 -> satisfy account security requirements
 -> claim/select Player
 -> active Player resolved
 -> Alliance/Kingdom/game capabilities become available according to that Player
```

## Daily Governor briefing

```text
Select active Player
 -> compose unread notifications, actionable Gift Codes, Event actions and upcoming Events
 -> include recruitment follow-up only when the Player has recruitment authority
 -> with a concrete active Alliance and Intelligence view authority, include bounded recent factual Intelligence changes
 -> link to owner-context rooms for every write
```

The command overview is a read-model composition surface. It does not own or copy the underlying business state. Intelligence changes are informational unless their specific state is explicitly defined as attention-worthy, and they do not automatically inflate the global action count. Before a concrete active Alliance scope exists, the dashboard does not render a synthetic “no intelligence changes” result.

## Alliance Territory & Hive Planner

### Create and save an Alliance hive plan

```text
Select active Governor
 -> open Territory Command
 -> choose Alliance scope and a supported map dataset
 -> create draft plan
 -> place HQ / Banners / Governor cities / Bear Traps
 -> receive immediate browser preview of validity
 -> inspect exact server-equivalent violations, warnings and suggestions
 -> save coherent layout against expected revision
 -> continue editing or publish an immutable revision
```

The canvas is a visualization/editor surface, not the only accessible control. Every placed object is also available through synchronized semantic controls with object type, label, Alliance, X/Y coordinates, validation state and keyboard-editable actions.

A save is a coherent plan mutation, not one request per pointer movement. The save action revalidates active-Player authority, expected revision, map dataset availability, geometry and game placement rules in the owner-controlled transaction. A stale revision produces an explicit conflict and never silently overwrites another officer's saved work.

### Distinguish game rules from planning advice

```text
Move/place object
 -> evaluate map facts and sourced game rules
 -> invalid rule => blocking violation
 -> legal but undesirable planning choice => warning
 -> optional better position/metric => suggestion
```

A red/green visual alone is insufficient. The UI states why a placement is invalid and does not present an Alliance preference such as a target Bear radius as an official KingShot rule.

### Build a Bear hive

```text
Open Alliance territory plan
 -> select Bear Trap or placement centre
 -> choose a typed hive generator/preset
 -> preview generated HQ/Banner/city arrangement
 -> validate complete proposed group
 -> place as a group
 -> assign/link Governors where known
 -> customize by moving/rotating/grouping objects
 -> inspect distance and estimated march metrics
```

March-time analysis identifies the assumption/calibration used. Unknown or community-observed speeds are not labelled official. A Governor may be application-linked or plan-local when planning external/unknown participants.

### Analyze a layout

```text
Open saved plan/revision
 -> calculate territory coverage and connectivity
 -> identify covered/uncovered Governors
 -> inspect invalid/warning counts
 -> inspect banner usage/efficiency
 -> inspect average / median / maximum distance to selected Bear Trap or objective
 -> compare with another immutable revision/layout
```

Analysis is deterministic and does not mutate either compared layout. Missing/unknown map data remains unknown rather than becoming zero or a guessed success.

### Coordinate multiple Alliances

```text
Open Kingdom-scoped territory plan
 -> add application-linked or external Alliance participant
 -> set display label/color/visibility
 -> place or generate each Alliance layout
 -> toggle/lock layers while coordinating
 -> validate shared map conflicts
 -> publish one Kingdom plan revision
```

External Alliances and Governors do not need fake application records. Plan-local references remain explicit. Cross-Alliance planning access is authorized through current Player/Kingdom/plan scope; owning another privileged Player does not automatically grant access while a different Player is active.

### Publish, share and recover

```text
Finish draft
 -> publish immutable revision pinned to map dataset/checksum
 -> export PNG/SVG or schema-versioned JSON
 -> optionally reference revision from supported Operations workflow
```

```text
Need correction
 -> clone/restore revision into editable head
 -> make changes
 -> publish a new revision
```

Published history is immutable. Restoring creates a new editable state; it does not rewrite the historical revision used by a Bear Hunt, Castle Battle or Kingdom planning workflow.

### Import a layout

```text
Choose JSON import
 -> parse schema
 -> normalize external identifiers/coordinates
 -> validate dataset and complete proposed layout
 -> preview additions/conflicts/warnings
 -> confirm commit
```

An importer never writes models while parsing. Invalid schema, unsupported map dataset, illegal geometry or authorization failure stops before persistence.

## Gift Code trust and redemption

```text
Submit normalized code with manual/community context
 -> append unverified evidence; duplicate identity never overwrites provenance
 -> canonical trust remains pending until qualified evidence exists
 -> filter the cursor-paginated catalogue and open bounded detail
 -> choose current, all, failed/incomplete, or selected owned Governors
 -> copy each Governor ID and code, then open the official redemption center
 -> record one observed outcome and continue to the next Governor
 -> resume incomplete/retry-due work from catalogue or Command Overview
```

Trust and redemption state stay separate. Registered-source evidence and platform moderation drive global trust; a single Governor result—including `wrong_kingdom`—does not invent global invalidity or applicability. Conflicting qualified evidence produces a visible dispute, and accepted expiry is revisioned. Negative outcomes require a prior official handoff, terminal success stays terminal, and retryable results have bounded backoff.

Availability, expiring and trust-change notifications use the Governor's Communications preferences, recheck current ownership/redemption state, deep-link to Gift Code detail and deduplicate by trust/expiry revision. Platform moderation is verified-email/MFA/password-confirmed and separate from Alliance rank. Approved-source acquisition is source-policy controlled, idempotent and operationally health-reported; missing or undocumented provider behavior remains inactive or quarantined.

The Notification Center lets a Governor select up to 50 visible inbox deliveries, preview either mark-read or dismiss against current ownership and state, confirm the eligible count, and inspect every success, failure, or skip. Dismissal uses the accessible destructive-action confirmation. Failed delivery IDs stay selected for review and selective retry; dismissed rows leave the inbox without hiding the just-completed result receipt.

## Recurring Alliance announcement

```text
Create a member-notifying announcement
 -> publish and send an actor-only test
 -> choose weekdays, local time, IANA time zone and optional end
 -> inspect the next occurrence in the rule time zone
 -> materialize one idempotent run per due occurrence
 -> inspect recipient and channel outcomes
 -> retry only eligible failures or cancel future recurrence
```

The rule, run and provider-delivery states are distinct. A queued run is not presented as provider success. Editing or archiving the source content stops active recurrence, while historical run and audit evidence remains inspectable.

## Alliance member

```text
Active Player
 -> current Alliance membership
 -> rank/specialist authority
 -> Alliance content / recruitment / membership / event capabilities as permitted
```

## Event coordinator

```text
Select scoped Event
 -> plan occurrence/capabilities
 -> manage participation/roster/polls/battle plan/rallies
 -> reference applicable published territory-plan revision when spatial coordination is needed
 -> record results
 -> analytical/history projections become available through Intelligence/ReadModels
```

The territory-plan reference is immutable revision identity. `BattlePlans` continues to own objectives/assignments and does not become the owner of HQ/Banner/city/terrain state.

The Event agenda exposes bulk cancellation only for manageable Event series. Selection is bounded to 50 concrete Event IDs, preview distinguishes eligible, completed, unavailable and already-cancelled Events, confirmation names the eligible count, and failed Event IDs remain selected for correction and retry.

Contribution managers may select up to 50 pending records in the approval queue, preview current authority and record state, confirm the eligible count, inspect every approval, skip or failure, and retry only failed records. Approval continues through the single-record owner action so bulk handling cannot bypass the immutable correction and reversal history.

## Recruitment pipeline

```text
Open Recruitment Hall with an authorized Player
 -> search or filter by stage and source
 -> review a bounded candidate page
 -> continue with the scope-bound cursor or return to the first filtered page
 -> optionally select up to 50 visible candidates and preview one stage transition
 -> confirm the eligible count and inspect every success, failure or skip
 -> correct and retry only failed candidates
 -> open one candidate for assignment, stage history, notes, duplicate review and conversion
```

Candidate filters remain in the URL. A cursor belongs to one Alliance and one normalized filter set; an invalid or stale cursor returns a validation error rather than mixing candidate lists. Bulk preview never bypasses current transition rules, and the controlled `joined` handoff remains a single-candidate workflow.

Alliance membership administration uses the same bounded, Alliance-scoped cursor contract. Summary counts describe the complete membership set rather than only the visible page. Authorized leaders may select up to 50 concrete memberships, preview hierarchy, Kingdom, exclusivity and capacity checks, confirm only eligible status changes, inspect every result, and retry only failed memberships after correction.

## King Perks coordinator

```text
Kingdom/Event preparation context
 -> publish planning window
 -> assign appointment/skill schedule
 -> enforce occupancy and cooldown
 -> communicate due reminders through delivery pipeline
 -> close/record operational outcomes
```

## Platform administrator

```text
Authenticate User + required account assurance
 -> validate Platform Administrator grant
 -> perform cross-tenant platform operation
```

Platform administration never substitutes for selecting a Player when performing game-domain behavior.

## Knowledge review and contextual Event guidance

1. A Content manager saves a source label, review date, optional game version/source URL and one or more related Event types with a guide or Event instruction.
2. The workspace derives its review deadline, surfaces due-soon and overdue items in one queue, and opens the existing editor for correction.
3. Saving a correction creates a new immutable revision containing both provenance and contextual links; restoring a revision restores both and returns the item to draft.
4. After publication, a matching Alliance Event page shows the member-visible guidance. Content remains the writer; the Event Calendar read model performs the cross-context lookup.

## Platform diagnostic recovery

1. A password-confirmed Citadel Warden reviews aggregate queue and outbox health plus bounded recent failure cards.
2. Error fingerprints group matching failures without displaying exception text, provider payloads or notification bodies.
3. The Warden may search a request UUID or trace ID for the ordered, metadata-free audit timeline.
4. Automatic outbox attempts stop at the configured limit. The Warden may release only an exhausted failed unpublished message for one fresh bounded cycle; the same message and idempotency key remain in place and the release is audited.

## High-risk mutation and recovery

```text
Open owner-context action
 -> review scope and irreversible effects
 -> confirm in the accessible modal
 -> submit once while controls remain busy
 -> receive one localized, typed success receipt or field/action error
 -> inspect audit/delivery state
 -> retry, cancel, correct or escalate only through the capability's supported recovery path
```

Browser-native prompts are not part of a supported journey. A high-risk action must preserve keyboard focus, expose its title and description to assistive technology, prevent duplicate submission, and keep the active Alliance/Player scope visible.

The application layout announces successful mutation receipts consistently. A receipt confirms the action and may include structured counts; durable audit history, per-item results, delivery state, and recovery controls remain inside the owning workflow.

## Bot connection and Event participation

1. A Governor with an active Alliance opens **Account & security → Bot connections** and chooses Discord or Telegram.
2. After password confirmation, the application shows one pairing code for ten minutes. Creating another code cancels the older unused code for that provider.
3. An approved Alliance bot claims the code using a credential with `actor-links:write`; the application stores a keyed provider-ID hash and a display hint.
4. The bot submits the linked Governor's Event response or registration change with `event-participation:write` and a unique idempotency key.
5. The existing Operations action authorizes the Governor and applies Event capability, registration-window, capacity and waitlist rules. Exact transport retries return the first receipt.
6. The Governor can revoke the link at any time; later bot writes fail before reaching Operations.

## Ask your Alliance

```text
Authenticate + select active Governor with an active Alliance
 -> open Ask your Alliance
 -> choose one of nine localized bounded suggestions or enter a supported question
 -> owner capabilities authorize before returning any private candidate data
 -> resolve a bounded Event/content/observation/Game Data/self-state/intelligence-change target or return a neutral missing/ambiguity/unsupported state
 -> query only the minimum authorized owner projection
 -> compose typed evidence and server-created citations
 -> show operational fact / Game data / Alliance strategy / Observation provenance
 -> ask another question or open the canonical source/owner workflow
```

The canonical first-use example is **“What time is Swordland and am I rostered?”**. The answer combines the authorized Event occurrence and only the active Governor's own roster assignment. The delivered extensions also support bounded source-backed Progression facts, self Participation/BattlePlan state, authorized self transfer assessment, immutable Event-attached published Territory revisions and typed authorized Intelligence change signals. Alliance-authored guidance and territory plans are labelled **Alliance strategy**; recorded intelligence and derived changes over observations are labelled **Observation**; source-backed Progression is labelled **Game data**. A question such as **“Put me on the Swordland roster”** performs no mutation and may offer a navigation-only handoff to the normal owner workflow. Unsupported general KingShot questions are not answered from model memory, and a factual signal is never converted into an unsupported strategic conclusion.

# Capability Extension Program — journey contracts

Status: **Current complete and evidence-gated journeys. Each journey below states its current product state.**

These journeys are the UX contract for their corresponding delivery rows. Current-complete journeys are implemented behavior. Evidence-gated journeys remain unavailable or bounded to qualified families until their gates pass.

## Ask a source-backed Game Data question

Program state: **Current complete capability**.

```text
Authenticate + select active Governor
 -> ask a bounded Progression question such as "What generation is Amadeus?"
 -> Assistant resolves `game_fact`
 -> GameWorld/Progression owner query selects a concrete immutable release before returning data
 -> match one supported canonical row or return ambiguity/not-found/conflict/unknown
 -> compose answer from typed owner projection
 -> show Game data provenance with dataset identity/version/checksum, source IDs and confidence
 -> open canonical Progression source/detail when desired
```

The Assistant does not scrape the web, query model memory, or persist a second knowledge base. A conflicting or unknown Progression row stays conflicting/unknown.

## Ask about my own operational state

Program state: **Current complete capability**.

```text
Ask "Did I register for Swordland?" / "What is my objective?" / "Can I transfer to Kingdom 123?"
 -> resolve the bounded self intent
 -> authorize the Event/Transfer/resource scope before retrieval
 -> query only the active Governor's Participation/BattlePlan/Transfer state
 -> preserve owner states such as waitlisted, missing, stale, conflicting or needs verification
 -> cite the owner records used
```

A self intent never reveals another Governor's private operational state.

## Handoff a write-like Assistant question to the owner workflow

Program state: **Current complete capability**.

```text
Ask "Put me on the Swordland roster"
 -> Assistant recognizes a write-like request
 -> perform no mutation
 -> when a safe canonical destination is resolved, offer "Open Swordland roster"
 -> navigate with bounded resource identity only
 -> destination owner workflow reauthorizes current Governor/context
 -> user performs or confirms the normal owner action
```

The Assistant never silently submits the action and never receives a privileged mutation capability.

## Event readiness before an Event

Program state: **Current complete capability**.

```text
Open Event Command for an authorized occurrence
 -> compose only capabilities enabled/applicable to this Event
 -> inspect schedule/cancellation
 -> inspect registration/response/capacity/waitlist
 -> inspect roster coverage
 -> inspect applicable polls/objectives/assignments
 -> inspect Event-linked Alliance strategy freshness
 -> inspect referenced immutable Territory revision when applicable
 -> inspect reminder/announcement delivery readiness
 -> inspect required Rally plans
 -> derive ready / needs attention from owner projections
 -> open the owner workflow for each blocker
```

There is no persisted `event_ready` boolean. Missing/unknown owner state cannot silently count as complete. Alliance Content remains Alliance strategy, not game truth. Queued Communications delivery is not presented as sent.

## Event closeout after an Event

Program state: **Current complete capability**.

```text
Event ends
 -> Event Command switches to derived closeout view when applicable
 -> inspect attendance recording completeness
 -> inspect recorded Rally participation completeness
 -> inspect Results state
 -> inspect Evidence still processing / awaiting review / failed commit
 -> inspect unmatched Governor review
 -> inspect unresolved owner corrections
 -> expose Debrief when authoritative data is available
 -> derive closeout required / complete
 -> deep-link every unresolved item to its owner workflow
```

Closeout remains read-side composition. Correcting attendance, Rallies, Results or Evidence uses the existing owner Actions and recovery semantics.

## Import Kingdom Transfer evidence

Program state: **Current complete capability**.

```text
Open authorized Transfer participant/window
 -> choose Add in-game evidence
 -> upload supported private screenshot
 -> scan/checksum/store immutable Evidence
 -> classify with a versioned Transfer screenshot schema
 -> extract only fixture-proven fields
 -> review field confidence/source meaning
 -> correct/exclude unresolved fields
 -> preview typed Transfer observations and resulting deterministic eligibility effect
 -> commit reviewed meaning
 -> GameWorld/KingdomTransfers reauthorizes scope and validates window/participant/target
 -> append/reuse idempotent owner observations
 -> recompute eligibility through existing evaluator
 -> Evidence records the destination receipt
```

A screenshot may record an observed required Transfer Pass count but cannot invent the unpublished Transfer Score → Pass formula. Retrying after destination success returns the same destination meaning rather than adding another observation.

## Import Governor progression evidence

Program state: **Current complete capability**.

```text
Open authorized Governor progression workflow
 -> upload supported profile/Hero/Gear screenshot
 -> Evidence classifies/extracts a narrow fixture-proven schema
 -> select the concrete immutable Progression release used for normalization
 -> match observed Hero/Gear/Charm identity to canonical catalogue IDs
 -> expose ambiguous/unresolved matches for human review
 -> approve reviewed meaning
 -> Intelligence/Roster reauthorizes and appends dated observation pinned to dataset identity/checksum
 -> Evidence records destination receipt
 -> progression history shows the new observation without rewriting prior observations
```

OCR names never create or rename Players or canonical Heroes. Not seeing an item does not prove non-ownership unless the evidence schema explicitly proves a complete capture.

## Plan a factual progression goal

Program state: **Current complete capability**.

```text
Open Progression Goal Planner
 -> choose current authorized Governor observation
 -> inspect its captured-at/source and pinned/current normalization context
 -> choose a target canonical level/tier/entity from a concrete Progression release
 -> show factual prerequisite/step path supported by that release
 -> mark missing/conflicting prerequisite data explicitly
 -> optionally save user planning intent pinned to the dataset
```

The planner does not call a target best/optimal/recommended and does not display resource/time totals for a family whose calculator evidence gate has not passed.

## Qualify a calculator family

Program state: **Evidence-gated extension**.

```text
Select one numeric family
 -> inventory every required input/output row and unit
 -> attach source/version/observed boundary
 -> reconcile conflicts and unknowns
 -> create immutable checksummed calculation dataset
 -> implement typed calculation contract and golden fixtures
 -> produce machine-readable qualification report
 -> if every gate passes: mark only this family qualified
 -> otherwise: keep runtime calculator disabled with explicit blockers
```

Qualification of Governor Gear does not qualify Charms, Hero Gear, troops, research or buildings.

## Use a qualified calculator

Program state: **Evidence-gated extension; unavailable until that family's qualification report passes**.

```text
Choose qualified family
 -> select canonical current and target steps
 -> validate both against the qualified immutable dataset
 -> run typed server/domain calculation
 -> show factual delta with dataset/checksum/calculation version, sources and assumptions
 -> return unavailable/conflicting when required inputs are unknown/conflicting
 -> optionally save scenario pinned to all versions/inputs
```

Calculators answer the selected scenario. They are not recommendations.

## Compare Territory plan with observed state

Program state: **Current complete capability**.

```text
Open immutable published Territory revision
 -> choose latest authorized observed-state evidence set
 -> verify observation/map/plan version compatibility
 -> compare planned HQ/Banner/Bear Trap/Governor positions with observations
 -> show matched / displaced / not observed / unexpected / stale / unknown states
 -> show deterministic distance/coverage effects when supported
 -> provide text equivalents for every spatial comparison result
 -> correct observation through Evidence/observation workflow or correct desired plan by creating a new editable plan revision
```

Observed coordinates never rewrite a published plan or GameWorld map dataset. A missing observation is not coordinate zero and is not proof an object does not exist.

## Review Intelligence change signals

Program state: **Current complete capability**.

```text
Authorized owner history changes
 -> authorize concrete Alliance/Governor/source scope before retrieval
 -> derive bounded deterministic signal from explicit source observations/facts
 -> retain source record IDs/timestamps, provenance and typed threshold/window/rule version
 -> expose signal in an authorized ReadModel surface
 -> compose it into scoped Command Overview / Kingdom Intelligence / Assistant as applicable
 -> when subscribed, deliver through Communications preferences and fingerprint-based idempotency
```

Delivered examples include tracked Alliance power/member-count change, Governor progression change/staleness, expiring Transfer observation validity, Bear Hunt trends and Recruitment workflow changes backed by explicit history. Missing data is never converted to zero. Ordinary Alliance history does not emit disappearance merely because a tracked Alliance was not seen; complete-source absence must be proven by the source contract. A numeric change never becomes an inferred conclusion such as "preparing to attack".

On Command Overview, the feed is shown only after a concrete active Alliance scope exists. A scoped feed with no signals may show the localized empty state; an unscoped dashboard does not claim that there are no changes.

## Operate a verified Kingshot Event profile

Program state: **Current complete capability**.

```text
Choose a canonical Event type
 -> inspect verification/profile/provenance state
 -> schedule an application occurrence
 -> resolve the closed typed workflow dimensions
 -> show only supported specialized owner workflows
 -> keep candidate/conflicting/unsupported profiles disabled
```

Scheduling is baseline Events behavior. A name or category never activates a Rally, result schema, Evidence intake, Debrief or other specialized path.

## Build a factual Rally roster

Program state: **Current complete capability**.

```text
Open verified Event management
 -> select occurrence
 -> review registered/rostered Governors
 -> create Rally groups in Operations/Rallies
 -> assign leads, joiners and standbys
 -> review unassigned, duplicate, declined, missing-lead and freshness reason codes
 -> correct through Participation/Rosters/Rallies owner controls
 -> use the existing Event plan as the published operational handoff
```

The builder does not score Governors, optimize lineups or predict performance.

## Review one Governor capability profile

Program state: **Current complete capability**.

```text
Authorize active Alliance and Intelligence scope
 -> open one Alliance Roster entry
 -> inspect latest/prior progression observations and provenance
 -> inspect authorized Event, Bear Hunt, Rally and BattlePlan history
 -> inspect authorized Transfer assessment and Evidence state
 -> follow owner links for correction or additional evidence
```

Unavailable sections do not leak counts. No aggregate member strength or quality score is calculated.

## Review Bear Hunt performance

Program state: **Current complete capability**.

```text
Open verified Bear Hunt occurrence
 -> load accepted Alliance/Governor results
 -> load attendance and recorded Rally actuals
 -> load Evidence review/unmatched state
 -> compare with the bounded previous accepted runs
 -> inspect deterministic difference/trend states
 -> hand unresolved Evidence back to its review workflow
```

Missing results are not zero and comparisons do not imply causes or recommended formations.

## Onboard an additional Kingshot Event

Program state: **Current complete evidence-gated onboarding process**.

```text
Identify named Event
 -> establish acceptable Kingshot source provenance
 -> review canonical identity and supported owner workflows
 -> list unsupported/evidence-gated mechanics
 -> add server-owned candidate/profile record
 -> enable only after the identity/evidence gate passes
 -> add typed readiness/closeout and UX coverage
```

KvK remains blocked at the first step in the current product snapshot.

## Coordinate a Transfer campaign

Program state: **Current complete capability**.

```text
Open Recruitment candidate
 -> review candidate state/history
 -> review Governor Evidence and Transfer observations
 -> inspect KingdomTransfers eligibility for target/window
 -> coordinate through Communications owner handoff
 -> record expected arrival as planning/coordination context
 -> observe transferred/active Alliance membership from its owner
```

The workspace does not copy candidate, eligibility, delivery or membership truth into a campaign table.

## Follow a Kingdom or Governor Intelligence timeline

Program state: **Current complete capability**.

```text
Authorize concrete Intelligence scope
 -> select Alliance or Governor
 -> compose bounded owner history chronologically
 -> show owner, observed time, source/Evidence, confidence and scope
 -> link derived signals to their before/after facts
 -> open the canonical owner history when more context is needed
```

Observed change is never converted into intent, threat or strategy.

## Resolve Territory and hive execution differences

Program state: **Current complete capability**.

```text
Open immutable published Territory revision
 -> attach/use it for an operation
 -> collect reviewed dated observations
 -> compare desired and observed state with typed tolerance
 -> resolve observation issues in Evidence/Observations
 -> create a replacement plan revision when desired state changes
```

Published revisions and observations remain immutable and separate.

## Review Alliance Command and officer briefs

Program state: **Current complete capability**.

```text
Authorize concrete Alliance officer scope
 -> derive current Event/Rally/BattlePlan/Roster/Transfer/Intelligence/Territory/Evidence attention
 -> order deterministic factual reason codes
 -> follow navigation-only owner handoffs
 -> optionally receive idempotent Daily / Upcoming Event / Post-Event briefs
 -> inspect delivery/retry state in Communications
```

Alliance Command stores no generic tasks. Queued delivery is not reported as delivered.

## Ask the expanded Alliance Assistant

Program state: **Current complete capability**.

```text
Ask a supported Kingshot operational question
 -> interpret one typed bounded intent
 -> authorize the exact owner projection
 -> answer with owner/source/provenance Evidence
 -> preserve missing/stale/conflicting/unsupported state
 -> for write-like language, return a context-preserving owner-workflow link only
```

The Assistant never mutates owner state or invents a free-form Kingshot answer.

## Extension-program recovery rule

For every capability-extension journey:

```text
failure / stale authority / duplicate / conflict / unavailable source
 -> preserve owner and provenance boundaries
 -> show explicit localized state
 -> perform no compensating hidden write
 -> retry/review/correct only through documented owner recovery path
```

The canonical acceptance criteria, ownership/provenance rules and delivery order for these journeys live in [Capability Extension Program](../capability-extension-program.md), [Kingshot Capability Expansion Program](../kingshot-capability-expansion-program.md) and the [Capability delivery ledger](../capability-delivery-ledger.md).
