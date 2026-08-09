# Kingdoms roster

[← Kingdoms domain](kingdoms.md)

**Status:** Accepted as part of `KINGDOMS-001`  
**Scope:** [Kingdoms roster intelligence increment](../product/kingdoms-roster-intelligence-increment.md)  
**Acceptance evidence:** [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md)

This document is the living contract for neutral Kingshot player identity and alliance-owned roster state. Snapshot history, derived intelligence and controlled CSV migration extend this roster through their own living contracts without changing its tenant/identity boundary.

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

The neutral reference owns only Kingdom, optional stable game-player identifier, and a reference/current name useful for neutral identity. Alliance-specific mutable observations do not live on the neutral record.

### Names are not identity

Display names are never used as an automatic deduplication key. If no stable game-player identifier is known, two same-name entries may represent distinct neutral player records. CSV name matches require explicit manager resolution; automatic name-based identity merge is not part of the accepted contract.

## Alliance roster entry

`AllianceRosterEntry` is tenant-owned by one Alliance and records the current roster relationship:

- Alliance and KingdomPlayer references;
- optional active same-alliance membership link;
- observed player name;
- optional game role/rank;
- state (`active`, `tracked`, `left`);
- joined date when known;
- left timestamp when marked left;
- private manager notes;
- last roster-observation timestamp; and
- source/provenance.

An Alliance can have only one roster entry for a given KingdomPlayer. A membership can link to only one roster entry inside the same Alliance.

## Membership linking

Linking a roster entry to an application membership is optional. A submitted membership must belong to the active Alliance, currently be active, and not already be linked to another roster entry in that Alliance.

Removing or leaving an application membership does not erase the game-player/roster identity or snapshot history. Membership lifecycle remains owned by `Memberships`.

The manager workspace surfaces both linkage gaps: active application memberships with no roster profile and roster profiles with no application membership.

## Authorization

`kingdoms.manage` protects roster management, membership links, snapshot recording, CSV migration, management export and manager-only comparison detail.

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

Roster read requires active authenticated Alliance context and `alliance.view`. Management views require `kingdoms.manage`. Create/update/leave and snapshot/import mutations additionally require recent password confirmation at their route boundary.

Alliance→Kingdom association is a separate Alliance-setting operation under `alliance.manage`.

## Manual roster workflow

### Add a player

A roster manager supplies observed player name, optional stable game-player identifier, optional active same-alliance membership, optional game role/rank, active/tracked state, optional joined date and optional private manager notes. The action resolves/creates the neutral game identity and creates one Alliance-owned roster entry transactionally.

### Edit an entry

Managers can update alliance-owned observation/state fields and change/remove the membership link. A stable game-player identifier is identity, not casual editable display metadata; changing identity requires an explicit reconciliation design rather than silently rebinding a roster entry.

### Mark left

Marking a player left changes roster state to `left`, records a left timestamp, retains the KingdomPlayer identity, roster entry, membership link and snapshot history, and is idempotent when repeated. There is no normal destructive roster-delete workflow.

## Roster search and current observation state

The member roster supports tenant-scoped search/filter by player name or stable ID, roster state, linked/unlinked application membership, game role/rank and current/stale/missing observation quality.

After snapshot history is available, current/stale/missing semantics are driven by the latest accepted snapshot: missing means no snapshot exists; stale means the latest snapshot is older than the documented 30-day freshness threshold. Historical rules are defined in [Kingdoms player snapshots](kingdoms-snapshots.md).

All predicates are applied after the active Alliance constraint. A search term matching another alliance in the same Kingdom returns no cross-tenant roster data.

## Member versus manager visibility

Ordinary alliance members may view roster-operational fields under `alliance.view`, including observed/current game identity, role/state, linked member display name and allowed latest observation data.

The ordinary member payload does **not** expose membership IDs, membership email addresses, private manager notes, snapshot actor identity or import-management metadata. The manager workspace may use the additional identity/linkage fields required to manage the roster because it is protected by `kingdoms.manage`.

## Audit and durable events

Material privileged roster mutations create audit records and matching transactional-outbox messages in the same transaction, including `kingdoms.roster_entry_created`, `kingdoms.roster_entry_updated`, and `kingdoms.roster_entry_left`.

The accepted increment adds no Kingdom-specific scheduler or worker. Kingdoms outbox events are internal durability events and are excluded from generic external webhook fan-out until an explicit integration contract approves external exposure.

## Tenant-isolation invariants

- All roster-entry reads/mutations are scoped by the active Alliance.
- Submitted roster-entry and membership IDs are re-resolved under the active Alliance.
- Roster search/filter queries start from `alliance_id` and never treat Kingdom identity as authorization.
- A shared KingdomPlayer does not expose another Alliance's roster state, notes, membership link, snapshots, imports or metrics.
- Kingdom number/player ID is never a tenant authorization key.

## Related accepted contracts

- [Kingdoms player snapshots](kingdoms-snapshots.md)
- [Kingdoms roster intelligence](kingdoms-intelligence.md)
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md)
- [Whole-increment security review](../security/kingdoms-roster-intelligence-security-review.md)

Transfer planning, diplomacy/NAP tracking, cross-alliance rankings, automated scoring, automated game-data ingestion and public Kingdoms API/webhook contracts remain outside `KINGDOMS-001`.
