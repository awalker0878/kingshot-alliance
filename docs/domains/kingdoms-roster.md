# Kingdoms roster

[← Kingdoms domain](kingdoms.md)

**Status:** `KINGDOMS-001` Slice B / `K1-P2` implementation candidate  
**Implemented foundation dependency:** [Kingdoms](kingdoms.md)  
**Approved scope:** [Kingdoms roster intelligence increment](../product/kingdoms-roster-intelligence-increment.md)

This document is the review contract for the Slice B roster implementation. Until Slice B passes its protected gate, the [current capability matrix](../product/current-capability-matrix.md) remains authoritative for what is available in the validated runtime.

## Identity model

The roster keeps three concepts separate:

```text
User (global application identity)
  └─ AllianceMembership (alliance relationship)
       └─ optional link
            AllianceRosterEntry (alliance-owned observation/state)
              └─ KingdomPlayer (global neutral game identity)
                   └─ Kingdom
```

A `KingdomPlayer` is not a site user and does not imply an alliance membership.

### Stable game-player identifiers

When a stable game-player identifier is supplied, it is resolved only within the Alliance's current Kingdom. Two alliances in the same Kingdom may therefore reference the same neutral `KingdomPlayer`.

The neutral reference owns only:

- Kingdom;
- optional stable game-player identifier; and
- a reference/current name useful for neutral identity.

Alliance-specific mutable observations do not live on the neutral record.

### Names are not identity

Display names are never used as a deduplication key.

If no stable game-player identifier is known, two same-name entries create distinct neutral player records rather than guessing that they are the same person. Identity merge/reconciliation is not part of Slice B.

## Alliance roster entry

`AllianceRosterEntry` is tenant-owned by one Alliance and records the current manual roster relationship.

Current candidate fields include:

- Alliance and KingdomPlayer references;
- optional active same-alliance membership link;
- observed player name;
- optional game role/rank;
- state (`active`, `tracked`, `left`);
- joined date when known;
- left timestamp when marked left;
- private manager notes;
- last-observed timestamp; and
- source/provenance (`manual` in Slice B).

An Alliance can have only one roster entry for a given KingdomPlayer. A membership can link to only one roster entry inside the same Alliance.

## Membership linking

Linking a roster entry to an application membership is optional.

A submitted membership must:

- belong to the active Alliance;
- currently be active; and
- not already be linked to another roster entry in that Alliance.

Removing, leaving, or physically deleting a membership must not erase the game-player/roster identity. The physical FK is nullable and roster history survives; membership lifecycle behavior remains owned by `Memberships`.

## Authorization

Slice B introduces:

`kingdoms.manage` — manage the alliance game roster, membership links, and roster observations.

Built-in defaults:

| Role | Roster view (`alliance.view`) | `kingdoms.manage` |
| --- | --- | --- |
| Owner | Yes | Yes |
| Leader | Yes | Yes |
| Officer | Yes | Yes |
| Recruiter | Yes | No |
| Event Coordinator | Yes | No |
| Content Manager | Yes | No |
| Member | Yes | No |

Custom-role permission union remains authoritative. Controllers do not authorize by role name.

Roster read requires the active authenticated Alliance context and `alliance.view`. Management views require `kingdoms.manage`. Create/update/leave mutations additionally require recent password confirmation.

## Manual roster workflow

### Add a player

A roster manager supplies:

- observed player name;
- optional stable game-player identifier;
- optional active same-alliance application membership;
- optional game role/rank;
- `active` or `tracked` state;
- optional joined date; and
- optional private manager notes.

The action resolves/creates the neutral game identity and creates one Alliance-owned roster entry transactionally.

### Edit an entry

Managers can update alliance-owned observation/state fields and change/remove the membership link. The stable game-player identifier is intentionally not treated as casual editable display metadata after creation; later identity reconciliation requires an explicit design rather than silently changing identity.

### Mark left

Marking a player left:

- changes roster state to `left`;
- records a left timestamp;
- retains the KingdomPlayer identity;
- retains the roster entry and membership link for historical continuity; and
- is idempotent when repeated.

There is no destructive roster-delete workflow in Slice B.

## Member versus manager visibility

Ordinary alliance members may view the roster under `alliance.view`.

Member-facing data is limited to roster-operational fields such as observed name, stable game ID when recorded, game role, state, linked member display identity, and last-observed time.

Private manager notes and unnecessary account/contact fields are management-only and must not be part of the ordinary member payload.

The manager workspace can use membership email to disambiguate account links because it is protected by `kingdoms.manage`.

## Audit and durable events

Material privileged mutations create audit records and matching transactional-outbox messages in the same transaction:

- `kingdoms.roster_entry_created`;
- `kingdoms.roster_entry_updated`; and
- `kingdoms.roster_entry_left`.

Slice B does not create a Kingdom-specific scheduler or Horizon queue. The existing outbox publisher remains the durable publication boundary.

## Tenant-isolation invariants

- All roster-entry reads/mutations are scoped by the active Alliance.
- Submitted roster-entry IDs are re-resolved under `alliance_id`.
- Submitted membership IDs are re-resolved under the active Alliance.
- A shared KingdomPlayer does not expose another Alliance's roster state, notes, membership link, or future observations.
- Kingdom number/player ID is never a tenant authorization key.

## Explicit deferrals

Slice B does not implement:

- player power/level snapshots or historical observations (`K1-P3`);
- stale-data calculations beyond the current manual last-observed field;
- aggregate power, median, 7/30-day trends or roster intelligence (`K1-P4`);
- CSV import/export (`K1-P5`);
- public roster API/webhook exposure;
- transfer planning;
- diplomacy/NAP tracking; or
- automated game-data ingestion.

Do not add those capabilities to Slice B merely because the roster foundation makes them possible.
