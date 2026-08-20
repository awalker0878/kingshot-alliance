# Primary user journeys

Status: Current

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
 -> link to owner-context rooms for every write
```

The command overview is a read-model composition surface. It does not own or copy the underlying business state.

## Gift Code trust and redemption

```text
Submit normalized code and source
 -> duplicate detection preserves distinct provenance
 -> shared trust begins pending
 -> choose current, all, or failed-only Governors
 -> continue at the official redemption center
 -> confirm delivery or report invalid/expired evidence
 -> inspect shared trust plus every per-Governor receipt
```

Trust and redemption state stay separate. Successful and conflicting negative evidence produces a visible dispute; a configured past expiry produces the terminal expired state. Invalid/expired reports require accessible confirmation and remain auditable. Expiry reminders are idempotent and only target owned Governors whose redemption is still in progress.

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
 -> record results
 -> analytical/history projections become available through Intelligence/ReadModels
```

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
# Bot connection and Event participation

1. A Governor with an active Alliance opens **Account & security → Bot connections** and chooses Discord or Telegram.
2. After password confirmation, the application shows one pairing code for ten minutes. Creating another code cancels the older unused code for that provider.
3. An approved Alliance bot claims the code using a credential with `actor-links:write`; the application stores a keyed provider-ID hash and a display hint.
4. The bot submits the linked Governor's Event response or registration change with `event-participation:write` and a unique idempotency key.
5. The existing Operations action authorizes the Governor and applies Event capability, registration-window, capacity and waitlist rules. Exact transport retries return the first receipt.
6. The Governor can revoke the link at any time; later bot writes fail before reaching Operations.
