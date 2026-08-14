# Rallies domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Rallies`  
**Primary authorization boundary:** Event scope/target permissions plus exact Rally-operating Alliance context; self actions use authenticated Player Context

## 1. Purpose and ownership

Rallies owns Player-saved troop formations, reusable Alliance Rally guidance, occurrence-specific recommended formations, Rally groups, Player assignments, assignment responses, and Rally participation evidence.

Events owns Event types, schedules, occurrences, participation, rosters, phases and polls. Rallies consumes an Event occurrence as the coordination boundary.

## 2. Scope

In scope are Player formations, 100%-total troop compositions, hero recommendations, effective-dated Alliance guidance, occurrence recommendations, Rally groups, lead/joiner/standby roles, numbered slots, Player confirmation/decline, and participated/absent evidence.

Rally groups may be operated by one Alliance within an Alliance, Player, or Kingdom Event. A Kingdom occurrence can contain multiple Alliance Rally groups concurrently.

## 3. Domain model

`PlayerFormation` belongs to one durable Player. `RallyGuidanceRule` belongs to one Alliance. `EventRecommendedFormation` belongs to one occurrence and one Rally-operating Alliance. `RallyGroup` belongs to one occurrence and one Rally-operating Alliance. `RallyAssignment` belongs to one Rally group and one durable Player.

Troop composition is infantry + cavalry + archer and must total exactly 100%. Guidance may include heroes, lead requirements, joiner guidance, source, rationale and an effective date window.

## 4. Core invariants

1. Rally participant identity is `player_id`.
2. Player formation ownership is authoritative through `players.user_id`; self actions require the exact active Player Context.
3. Rally-operating Alliance is explicit on guidance, recommendations and groups.
4. A Player must be Event-eligible and actively rostered in the exact Rally-operating Alliance before assignment.
5. A Player has at most one active Rally group assignment per occurrence + Rally Alliance.
6. A Rally group has at most one active lead.
7. Active numbered slots are unique within a Rally group.
8. `max_joiners` limits active joiners only; standby does not consume joiner capacity.
9. Declined/removed assignments release lead, slot and joiner capacity while remaining historical evidence.
10. Rally participation is separate from Event attendance.
11. Kingdom Events can coordinate multiple Alliances without changing Player identity or Event ownership.

## 5. Lifecycles and workflows

A Player selects their active Player Context and creates/updates/deletes saved formations. An authorized Alliance operator maintains reusable guidance. Event managers create occurrence recommendations and Rally groups for a valid operating Alliance, then assign eligible Players.

Managers may move a Player between groups in the same occurrence/Alliance; the previous assignment becomes removed evidence. The assigned Player can confirm or decline only through their active Player Context. Managers record participated/absent after the Rally.

## 6. Authorization and tenancy

Self formation and assignment-response actions require an authenticated User whose active Player is owned through `players.user_id`. No Player identifier is accepted for self-response routing.

Occurrence Rally mutations require the Event's exact manage permission for its Player, Alliance, or Kingdom scope. Alliance guidance requires `events.alliance.manage` for the exact Alliance. Selecting a Player does not grant Alliance or Kingdom authority.

## 7. Cross-domain contracts

Consumes Events occurrence/capability/authorization contracts, Alliances as operating context, Kingdoms Player/Kingdom/roster facts, Authorization permissions, and Audit/Platform evidence services.

Exposes saved formations, effective guidance, occurrence recommendations, Rally groups, assignments and Rally participation to first-party Event workspaces and later Results/Intelligence processing.

## 8. Persistence and data ownership

Rallies owns `player_formations`, `rally_guidance_rules`, `event_recommended_formations`, `rally_groups`, and `rally_assignments`. Event and Player records remain owned by their source domains and are referenced by foreign key.

## 9. Events, outbox and integrations

Material Rally mutations write audit evidence and outbox events. Event-related Rally messages use the parent Event scope target as the partition key (`player:{id}`, `alliance:{id}`, or `kingdom:{id}`) while retaining the operating Alliance in payload/evidence.

## 10. HTTP, UI and API surfaces

Player formation endpoints are `/player/formations`. Event Rally operations are under `/events/{occurrence}/...`; assignment self-response uses `/events/{occurrence}/rally-assignments/{assignment}/response`. Alliance guidance is under `/alliances/{alliance}/rally-guidance`.

Show presents saved formations, effective guidance, recommendations and the active Player's assignments. Manage provides Alliance-aware Rally planning and participation recording.

## 11. Background processing

Rally state is request-driven. The domain does not execute game actions. Future reminders/intelligence may consume Rally assignment facts through supported domain contracts.

## 12. Failure, idempotency and concurrency

Formation composition failures reject the mutation. Occurrence rows serialize assignment changes across groups, preventing simultaneous double-assignment. Group locks protect joiner capacity, lead and numbered-slot checks. Reconfirmation rechecks all constraints.

## 13. Security and privacy

`players.user_id` is authoritative for self-owned Player state. Event visibility/management and Rally Alliance eligibility are re-evaluated server-side. Free-text strategy fields are Alliance-private operational data and must not be used as secret storage.

## 14. Observability and operations

Operational diagnosis uses Event/occurrence ID, Rally Alliance ID, group ID, Player ID, role/status, audit actor User/Player, and scope-aware outbox partition. See [operations](operations/README.md).

## 15. Testing and architecture enforcement

Tests protect Player-context isolation, 100% compositions, joiner capacity, active lead/slot uniqueness, cross-group moves, cross-Alliance assignment rejection, multi-Alliance Kingdom Rally plans, exact authorization.

## 16. Explicit non-capabilities

Rallies does not own Event scheduling, Event attendance, Player ownership, Alliance roster lifecycle, Kingdom authorization, or automated in-game execution.

## 17. Capability documents

- [Interfaces](interfaces/README.md)
- [Security](security/README.md)
- [Operations](operations/README.md)
- [Testing](testing/README.md)

## 18. Related documentation

- [Events](../events/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Authorization](../authorization/README.md)
- [`app/Domain/Rallies/README.md`](../../../app/Domain/Rallies/README.md)
