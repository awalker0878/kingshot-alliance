# Kingdom-scoped roles

[← Authorization domain](README.md)

**Status:** Current — EVENTS-002 P1

## Purpose

Kingdom-scoped roles provide authority for operations whose target is a `Kingdom`, independent of Alliance membership or Alliance R1–R5 rank.

An Alliance R5 is not automatically a Kingdom administrator. A user may be R5 in one or more Alliances and still have no `events.kingdom.*` permission.

## System roles

| Role | Permissions |
| --- | --- |
| Kingdom Admin | `events.kingdom.view`, `events.kingdom.create`, `events.kingdom.manage`, `kingdom.roles.manage` |
| Kingdom Event Coordinator | `events.kingdom.view`, `events.kingdom.create`, `events.kingdom.manage` |
| Kingdom Viewer | `events.kingdom.view` |

Assignments are bound to exactly one Kingdom. Authority in Kingdom A grants nothing in Kingdom B.

## Persistence

Authorization owns:

- `kingdom_roles` — per-Kingdom system role instances;
- `kingdom_role_permissions` — role-to-global-permission mapping; and
- `kingdom_role_assignments` — User-to-role assignments within one Kingdom, including the assigning User.

The assignment table uses a composite foreign key back to `(kingdom_role_id, kingdom_id)` so a role from one Kingdom cannot be attached through another Kingdom context.

## Bootstrap and delegation

A platform administrator may bootstrap a Kingdom role assignment. Platform-administrator status itself does not satisfy `KingdomAuthorization` and therefore does not grant Kingdom Event authority.

After bootstrap, a Kingdom Admin may assign or remove Kingdom roles inside that exact Kingdom through `kingdom.roles.manage`.

## Player Event identity

Player-scoped Event authorization is separate from Kingdom roles. A `Player` is considered the actor's own player only when a current `AllianceRosterEntry` links that player to an active `AllianceMembership` owned by the actor.

For another player's Player Event, R4/R5 management authority is evaluated against the target player's current Alliance roster context via `events.player.manage`. Historical or left roster links do not grant authority.

## Unified Event authorization

`EventAuthorization` requires all three dimensions to agree:

1. the permission belongs to the requested `EventScope`;
2. the supplied target model matches that scope; and
3. the scope-specific authorization service grants the permission for the exact target.

This prevents powerful permissions from one context being reused against another scope.
