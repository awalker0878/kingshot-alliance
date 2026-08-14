# EVENTS-002 — Scoped KingShot Event Operations

[← Events domain](../README.md)

**Status:** Implemented

## Outcome

Deliver one permission-aware KingShot Event operations domain supporting Player, Alliance, and Kingdom scopes.

```text
Event Type = what is happening
Scope      = player | alliance | kingdom
Target     = Player | Alliance | Kingdom
Capability = which operational modules apply
Permission = who may act against the exact target
```

## Alliance rank dependency

- R5 = the single Alliance owner/leader.
- R4 = officer.
- R3/R2/R1 = member ranks.
- Event Coordinator is additive and may grant Alliance Event operations without changing rank.
- Alliance rank never grants Kingdom Event authority.

## Core Event vocabulary

`EventScope`: `player`, `alliance`, `kingdom`.

`EventCapability`: `responses`, `registration`, `waitlist`, `attendance`, `phases`, `polls`, `rosters`, `substitutes`, `teams`, `legions`, `rally_guidance`, `formations`, `objectives`, `scoring`, `results`.

## Permission vocabulary

```text
events.player.view/create/manage
events.alliance.view/create/manage
events.kingdom.view/create/manage
events.types.manage
```

## Delivery sequence

### EVT-P0 — Rank and Event architecture

- establish R1–R5 rank and specialist-role separation;
- establish `EventScope` and `EventCapability`;
- establish scoped Event permissions;
- enforce rank and scope architecture tests.

### EVT-P1 — Scoped authorization

- implement Kingdom roles/assignments with exact-Kingdom isolation and last-admin safety;
- implement `KingdomAuthorization`, Player Event authorization, and unified `EventAuthorization`;
- implement permission-aware creation contexts;
- expose Kingdom role management through an authorized, password-confirmed surface;
- test Player, Alliance, and Kingdom authorization independently.

### EVT-P2 — Event Type catalogue

- create `event_types`, `event_type_scopes`, and `event_type_capabilities`;
- populate the KingShot Event Type catalogue during migration;
- implement Event Type registry and capability resolution;
- implement platform administration for scope defaults and capabilities;
- add catalogue persistence and vocabulary tests.

### EVT-P3 — Scoped scheduling and core Event experience

- create Event, template, and occurrence persistence with exactly one scope target;
- implement recurrence and occurrence materialization;
- implement creation/update/publish/cancel actions;
- implement scoped Event queries;
- implement Events agenda/calendar, Event detail/show, Create Event, and Event management workspace;
- restore authenticated calendar/export surfaces on the scoped model;
- add scoped scheduling, recurrence, route, UI-contract, and isolation tests.

### EVT-P4 — Participation and reminders

- implement responses/availability;
- implement registration, waitlist, cancellation, and promotion;
- implement attendance;
- implement reminder rules/deliveries and queueing;
- implement Needs Your Attention;
- connect participation modules into detail/manage pages;
- add concurrency, idempotency, reminder, and accessibility tests.

### EVT-P5 — Phases and voting

- implement occurrence phases;
- implement generic polls/options/votes;
- implement time-voting workflows;
- render phase/poll modules from capabilities.

### EVT-P6 — Rosters

- implement rosters and roster members;
- implement combatants, substitutes, teams, and legions;
- implement confirmations and availability-aware assignment;
- implement desktop and mobile roster planning.

### EVT-P7 — Rally operations

- implement durable `Player` formations;
- implement Event recommended formations and Rally guidance;
- implement Rally groups and assignments;
- support Alliance Rally groups within Kingdom Events;
- integrate Rally modules into Event detail/manage pages.

### EVT-P8 — Battle planning

- implement objectives, hierarchy, priorities, and assignments;
- implement battle-plan workspace and objective state.

### EVT-P9 — Results and intelligence

- implement occurrence and Player results;
- implement scoring and participation history;
- implement reliability and Event planning intelligence.

### EVT-P10 — UX hardening

- localize the Event operational shell for every supported locale with safe English fallback for untranslated catalogue prose;
- complete responsive/mobile behavior and accessibility;
- keep calendar, attention, reminders, and intelligence queries bounded as Event/Player volume grows;
- make create/manage navigation permission-aware;
- enforce active-Player visibility for Player-specific prompts and reminders;
- complete security, localization, accessibility, and query-bound regression coverage.

## Persistence model

An Event contains one and only one scope target:

```text
player   → player_id
alliance → alliance_id
kingdom  → kingdom_id
```

`Player` is the durable participant identity for Event participation and history.

## Capability-driven UI

Event pages and creation flows render modules from configured capabilities. Bear Hunt exposes Rally guidance and formations. Swordland exposes polls, rosters, substitutes, and objectives. Kingdom of Power exposes phases, teams, objectives, scoring, and results.

## Authorization rule

Ranks and roles explain why authority exists; permissions are authoritative; scope and target decide where authority applies. Controllers do not authorize by rank-name comparisons.
