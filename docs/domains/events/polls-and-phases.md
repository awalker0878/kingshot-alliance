# Event phases and polls

[← Events domain](README.md)

**Document type:** Living capability contract
**Status:** Current
**Owning domain:** Events

## 1. Purpose

Defines occurrence-scoped operational phases, generic polls, Player-specific votes, catalogue-driven defaults, and poll-deadline coordination for Events that enable the `phases` or `polls` capabilities.

## 2. Scope and non-scope

In scope:

- occurrence phase timelines;
- manager-created and catalogue-materialized phases;
- generic choice and time-vote polls;
- manager-supplied poll options;
- active-Player voting;
- vote totals and closed-poll results;
- catalogue-driven Swordland phase and time-vote defaults; and
- poll-close reminder coordination through Notifications.

Out of scope: roster selection, team/legion placement, Rally operations, objectives, scoring, and Event results.

## 3. Identity and model

Phases and polls belong to one concrete `EventOccurrence`. A poll option belongs to one poll. Votes are keyed by `poll_id + option_id + player_id` and record the authenticated User plus the active Player persona that cast the vote.

`players.user_id` is authoritative Player ownership. Vote requests never submit a subject Player identity. The server resolves the request-scoped active Player context and revalidates ownership, Event eligibility, and exact Event view permission before accepting a vote.

## 4. Invariants

1. Phase and poll availability is controlled by Event Type capabilities.
2. Catalogue defaults are materialized per occurrence; manager-edited records are not overwritten by later materialization.
3. Event rescheduling cancels the old occurrence and preserves its phases, polls, options, and votes as history; replacement occurrences receive fresh defaults.
4. One phase key and one poll key may exist per occurrence.
5. A poll requires a question key or explicit question.
6. An open poll requires at least two options and a future close time when one is configured.
7. `time_vote` permits one choice per Player and every option value must be a valid date-time.
8. Poll options become immutable after the first vote so existing votes never change meaning.
9. Re-voting replaces only the active Player's prior selections atomically.
10. The active Player context never grants Alliance or Kingdom management authority.
11. Poll-close reminders reference the exact poll and are valid only for polls belonging to the same Event.

## 5. Workflows

### Materialize defaults

When an occurrence is created, Events reads the persisted Event Type capability configuration. Supported phase definitions are materialized relative to the occurrence start. Supported poll definitions are materialized as draft polls. Swordland Showdown materializes voting, registration, matchmaking, and battle phases plus a draft battle-time poll whose choices are supplied by the Event manager.

### Manage phases

An exact-target Event manager may create or update phases. A phase can use a localized `name_key` or an explicit name and may have a bounded start/end window. Manually edited phases are marked as manager-owned configuration and are not rewritten by catalogue materialization.

### Configure a poll

An exact-target Event manager creates or updates a poll, its voting window, choice limit, and options. The poll may remain draft until the options and timing are ready. Once voting exists, options are locked.

### Vote

The authenticated User switches to the intended Player using the global Player Context. The vote action resolves that active Player server-side, verifies ownership, eligibility, and Event visibility, then atomically replaces that Player's selections within `max_choices`.

### Poll deadline reminder

An Event manager may configure a `before_poll_close` reminder for an open poll. Notifications calculates due time from the poll close timestamp and resolves the audience to Player-specific deliveries using current Event eligibility.

## 6. Authorization and privacy

Phase and poll management uses the Event's exact manage permission for its Player, Alliance, or Kingdom target. Voting requires the exact Event view permission in addition to Player ownership and eligibility.

No vote route or request field accepts a Player identity. A User who owns multiple Players must switch the global active Player context before voting as another owned Player.

Poll votes are private operational records while voting is open. Managers may see live totals for operations. Closed or expired poll results may be presented to eligible viewers when the Event surface exposes them.

## 7. Persistence and query semantics

Events owns:

- `event_phases`;
- `event_polls`;
- `event_poll_options`; and
- `event_poll_votes`.

`event_reminder_rules.poll_id` is Notifications-owned coordination state referencing a poll when `trigger_type = before_poll_close`.

Phase effective status is derived from stored terminal state plus the current time when a bounded phase has start/end timestamps. Poll voting availability is derived from stored poll status plus its optional open/close window.

## 8. Events, integrations and background processing

Phase/poll mutations emit audit and scope-partitioned outbox evidence. Vote mutations emit `event.poll.vote_cast`. Notifications owns poll-deadline reminder materialization and delivery state; Events owns the poll and vote facts that determine whether a reminder remains relevant.

## 9. Failure, idempotency and concurrency

- default materialization is idempotent by occurrence/key;
- manager-owned phase changes are preserved;
- poll updates lock the exact poll when needed;
- voting locks the poll and replaces one Player's vote set transactionally;
- duplicate selected option IDs are normalized before validation;
- cross-poll option IDs are rejected;
- voting outside the poll window fails closed;
- option mutation after voting starts is rejected;
- stale Player ownership, Player context, Event eligibility, or Event visibility fails closed.

## 10. Operations and observability

Diagnose occurrence, phase key/type/timing/effective status, poll key/type/status/window, option set, Player vote identity, actor User/Player, Event target scope, reminder trigger, and outbox partition independently.

## 11. Tests and validation

Tests protect catalogue-driven Swordland materialization, phase timing, independent Player votes, re-voting, option locking, capability rejection, vote attention state, Player-specific poll-deadline reminders, exact Event authorization, and absence of Player identity in vote routes.

## 12. Related documentation

- [Events domain](README.md)
- [Event participation and attendance](registration-and-attendance.md)
- [Event reminders](../notifications/event-reminders.md)
- [Authorization](../authorization/README.md)
- [Kingdoms / Player identity](../kingdoms/README.md)
