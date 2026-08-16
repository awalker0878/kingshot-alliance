# Event battle plans

[← Events domain](README.md)

**Document type:** Living capability contract
**Status:** Current
**Owning domain:** Events

## 1. Purpose

Defines occurrence-scoped battle objectives, objective hierarchy, priority/status, optional time windows, and assignment to either durable Players or occurrence rosters for Event Type scopes that enable the `objectives` capability.

## 2. Scope and non-scope

In scope:

- top-level and nested objectives;
- objective type, description, priority, status, ordering, and occurrence-local time windows;
- assignment to one eligible `player_id` or one roster per assignment row;
- multiple assignments per objective;
- active-Player highlighting of direct and roster-derived responsibilities;
- manager create/update/assignment/removal operations; and
- audit/outbox evidence for material battle-plan changes.

Out of scope: score/result recording, automated in-game command execution, Rally participation state, and Event attendance.

## 3. Model and state

`EventObjective` belongs to exactly one `EventOccurrence`. A child objective may reference a parent objective only from the same occurrence.

`EventObjectiveAssignment` belongs to one objective and targets exactly one of:

- `player_id`; or
- `roster_id`.

Player identity is durable `Player`. `players.user_id` is authoritative ownership for self context and may appear on any number of Player rows for one User. Battle-plan assignment never uses `AllianceMembership` as participant identity.

## 4. Invariants

1. Objectives exist only when the Event Type scope enables `objectives`.
2. Objective hierarchy never crosses occurrence boundaries.
3. An objective cannot be parented beneath itself or one of its descendants.
4. Priority is between 0 and 100.
5. If objective timing is provided, both start and end are required and remain within the occurrence.
6. Each assignment targets exactly one Player or roster.
7. Player assignments require exact Event eligibility.
8. Roster assignments require a roster from the same occurrence.
9. Duplicate objective-to-Player and objective-to-roster assignments are idempotent updates, not duplicate rows.
10. Player deletion is restricted while objective-assignment history references that Player.
11. The active Player Context affects only persona/highlighting; it does not grant Event management authority.
12. One User owning multiple Players does not merge their objective assignments.

## 5. Workflows

### Build a battle plan

An exact-target Event manager creates top-level objectives and optional child objectives. Managers may assign priority, status, time window, free-text instructions, and an objective type appropriate to their plan.

### Assign responsibility

A manager assigns an objective either to a roster or directly to an eligible Player. Assigning the same target again updates assignment notes and assignment authorship rather than creating a duplicate.

### Player view

The Event Show workspace renders the full published battle plan. Assignments matching the active Player directly, or matching a roster the active Player currently occupies, are highlighted as that Player's responsibilities.

### Change responsibility

Managers remove an assignment without deleting the objective. Historical mutation evidence is retained in audit/outbox records even though the current assignment row is removed.

## 6. Authorization, tenancy and privacy

Battle-plan writes require the Event's exact manage permission for its Player, Alliance, or Kingdom target. Player Context is validated from `players.user_id` and is used only for actor persona/audit.

Any authenticated User with exact Event view permission may view the battle plan. Switching active Player does not grant Alliance or Kingdom authority.

## 7. Persistence and query semantics

Events owns `event_objectives` and `event_objective_assignments`.

Objectives are ordered by priority descending, then explicit sort order. Management queries expose eligible Players and occurrence rosters. Show queries derive `myAssignmentIds` for the active Player from both direct Player assignments and current roster memberships, excluding declined/removed roster assignments.

## 8. Events, integrations and background processing

Objective and assignment mutations emit audit evidence and scope-aware outbox messages using `player:{id}`, `alliance:{id}`, or `kingdom:{id}` partitioning inherited from the parent Event target.

No background worker executes objectives in game.

## 9. Failure, idempotency and concurrency

- cross-occurrence parent/roster references fail closed;
- ineligible Player assignment fails closed;
- hierarchy cycles are rejected;
- invalid time windows fail closed;
- duplicate target assignment updates the existing row;
- objective mutation serializes the occurrence/objective boundary before write;
- database constraints enforce target cardinality and same-occurrence references.

## 10. Operations and observability

Operational diagnosis uses Event ID, occurrence ID, objective ID, parent ID, priority/status, assignment ID, target Player/roster, actor User, actor Player persona, and Event target partition independently.

## 11. Tests and validation

Tests protect hierarchy, cycle rejection, Player eligibility, same-occurrence roster assignment, duplicate-target uniqueness, multi-Player User isolation, active-Player highlighting, database target cardinality, and absence of membership identity in battle-plan persistence.

## 12. Related documentation

- [Events domain](README.md)
- [Event rosters](rosters.md)
- [Event phases and polls](polls-and-phases.md)
- [Rallies](../rallies/README.md)
- [Authorization](../authorization/README.md)
- [Kingdoms / Player identity](../kingdoms/README.md)
