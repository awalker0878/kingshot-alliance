# Event rosters

[← Events domain](README.md)

**Document type:** Living capability contract
**Status:** Current
**Owning domain:** Events

## 1. Purpose

Defines occurrence-scoped Player selection, hierarchical rosters, mutually exclusive assignment groups, combatant/substitute/team/legion placement, Player confirmation, availability warnings, and contextual Alliance snapshots for Events that enable the `rosters` capability.

## 2. Scope and non-scope

In scope:

- occurrence rosters and optional parent/child hierarchy;
- combatant, substitute, team, legion, and general roster types;
- capacity and explicit slot assignment;
- mutually exclusive placement within an assignment group;
- manager assignment, move, and removal;
- active-Player confirmation/decline;
- response/availability/registration warnings;
- rostered reminder audiences; and
- contextual Alliance snapshots for multi-Alliance Kingdom Events.

Out of scope: Rally formation guidance, objective/battle-plan assignment, score/result recording, and automated game-state ingestion.

## 3. Identity and model

Roster membership is keyed by durable `player_id`. `AllianceMembership` is never the participant identity.

`event_rosters` belongs to one `EventOccurrence`. A roster may optionally have a parent roster from that same occurrence and assignment group. `event_roster_members.alliance_id` is a nullable contextual snapshot of the Player's active Alliance when assignment occurs; it is not tenancy, ownership, or authorization.

A Player may be unclaimed (`players.user_id = null`) and still be manager-rostered. Only a claimed Player can self-confirm because active Player Context is authoritative for self-service.

## 4. Invariants

1. Rosters exist only for Event Type scopes with the `rosters` capability.
2. A roster belongs to exactly one occurrence.
3. Parent/child rosters must belong to the same occurrence and assignment group.
4. A roster with child rosters is structural and does not accept Player assignments directly.
5. Capacity applies to active slot-occupying assignments only.
6. `declined` and `removed` assignments do not occupy capacity or a unique active slot.
7. A Player may have at most one active assignment in the same occurrence + assignment group.
8. Moving a Player within an assignment group preserves the prior row as `removed` history and creates/reactivates the target assignment.
9. Confirmation/decline records the authenticated User and exact active Player persona separately.
10. Self-response routes never accept a subject Player identifier.
11. Contextual `alliance_id` never grants Event or roster authority.
12. Stored assignment warnings are assignment-time evidence; current warnings are recalculated for management views.

## 5. Workflows

### Materialize catalogue rosters

When an occurrence is created, Events reads persisted roster capability configuration and materializes any stable roster shape. Swordland creates Combatants and Substitutes. Summit League creates two Legion parents with Combatants/Substitutes children. Flamedragon creates its combatant roster. Events without a stable roster shape may use manager-created rosters.

### Assign or move a Player

An exact-target Event manager selects an eligible Player and leaf roster. The occurrence and target roster are serialized. Conflicting active assignments in the same assignment group are marked removed, then capacity/slot constraints are checked and the target assignment is written as `assigned`.

### Confirm or decline

The User switches to the intended Player in global Player Context. The server revalidates `players.user_id`, exact Event view/self authorization, and assignment ownership. Confirming a previously declined assignment rechecks capacity, slot ownership, and assignment-group conflicts before occupying the slot again.

### Remove

An exact-target manager marks the assignment removed and records manager User/Player authorship. The historical row remains for audit and later intelligence.

## 6. Authorization and privacy

Roster creation/assignment/removal uses the Event's exact manage permission for its Player, Alliance, or Kingdom target. Self confirmation uses exact Event view permission plus active Player ownership and Event eligibility.

Switching active Player never grants Alliance or Kingdom management authority. Alliance snapshots on Kingdom Event roster rows are presentation/history context only.

## 7. Persistence and query semantics

Events owns `event_rosters` and `event_roster_members`.

Active occupancy includes `assigned`, `confirmed`, `participated`, and `absent`. Declined/removed rows remain queryable history but do not count against capacity. A partial unique index protects active slot numbers per roster, while action-level occurrence locking protects assignment-group moves and capacity checks.

Management queries expose all eligible durable Players, including unclaimed Players, plus current response, preferred role/team, availability window, registration state, and warnings.

## 8. Events, integrations and background processing

Roster mutations emit audit and target-partitioned outbox evidence. Notifications resolves the `rostered` reminder audience from current `assigned` and `confirmed` assignments for the exact occurrence, then delivers only to Players with an authoritative owning User.

No background worker changes roster assignment state implicitly.

## 9. Failure, idempotency and concurrency

- cross-occurrence roster/parent identifiers fail closed;
- parent rosters reject direct Player placement;
- full rosters reject new occupying assignments;
- active slot conflicts fail closed;
- same-group moves are serialized and atomic;
- reassigning an existing Player to the same roster resets confirmation and refreshes warnings;
- confirmation after decline rechecks capacity/group/slot constraints;
- stale Player ownership or Event visibility fails closed.

## 10. Operations and observability

Diagnose occurrence, roster key/type/group, parent, capacity, Player, contextual Alliance, slot, status, stored/current warnings, manager User/Player, response User/Player, and target partition independently.

## 11. Tests and validation

Tests protect catalogue roster materialization, hierarchy, capacity, mutually exclusive moves, decline/re-confirm behavior, Player-context anti-impersonation, rostered reminders, attention state, unclaimed Player management, multi-Alliance Kingdom snapshots, leaf-only assignment, and absence of membership identity in roster persistence.

## 12. Related documentation

- [Events domain](README.md)
- [Event participation](registration-and-attendance.md)
- [Event phases and polls](polls-and-phases.md)
- [Event reminders](../notifications/event-reminders.md)
- [Authorization](../authorization/README.md)
- [Kingdoms / Player identity](../kingdoms/README.md)
