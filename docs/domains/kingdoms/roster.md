# Kingdoms roster

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as part of `KINGDOMS-001`  
**Owning domain:** `Kingdoms`

## 1. Purpose

This document defines the living contract for neutral Kingshot player identity and Alliance-owned roster state. Snapshot history, derived intelligence, controlled CSV migration, and transfer handoff extend the roster through their own contracts without changing its tenant/identity boundary.

## 2. Scope and non-scope

In scope:

- neutral `KingdomPlayer` identity;
- Alliance-owned `AllianceRosterEntry` state;
- optional same-Alliance Membership linkage;
- manual add/edit/mark-left workflow;
- roster search/filter and member/manager visibility; and
- audit/outbox behavior for material roster mutations.

Out of scope:

- snapshot history details (see [Snapshots](snapshots.md));
- aggregate/trend calculations (see [Intelligence](intelligence.md));
- CSV migration details (see [CSV migration](csv-migration.md));
- transfer planning details (see [Transfer planning](transfer-planning.md));
- public Kingdoms API/webhook contracts; and
- automatic name-based identity merge.

## 3. Model and state

The roster keeps application identity, membership, tenant observation, and neutral game identity separate:

```text
User (global application identity)
  └─ AllianceMembership (Alliance relationship)
       └─ optional link
            AllianceRosterEntry (Alliance-owned observation/state)
              └─ KingdomPlayer (global neutral game identity)
                   └─ Kingdom
```

A `KingdomPlayer` is not a site User and does not imply an Alliance membership.

### Stable game-player identifiers

When supplied, a stable game-player identifier is resolved only within the Alliance's current Kingdom. Two Alliances in the same Kingdom may therefore reference the same neutral `KingdomPlayer`.

The neutral reference owns Kingdom, optional stable game-player ID, and neutral current/reference display name. Alliance-specific mutable observations do not live on the neutral record.

### Names are not identity

Display names are never automatic deduplication keys. Without a stable game-player ID, two same-name entries may represent distinct neutral players. CSV name matches require explicit manager resolution.

### AllianceRosterEntry

An `AllianceRosterEntry` owns:

- Alliance and KingdomPlayer references;
- optional active same-Alliance membership link;
- observed player name;
- optional game role/rank;
- state (`active`, `tracked`, `left`);
- joined date when known;
- left timestamp when marked left;
- private manager notes;
- last roster-observation timestamp; and
- source/provenance.

An Alliance can have only one roster entry for one KingdomPlayer. A membership can link to only one roster entry inside the same Alliance.

## 4. Invariants

1. Stable game-player ID within the Kingdom is the only automatic player identity key.
2. Display names never auto-merge identity.
3. Neutral `KingdomPlayer` identity never grants cross-Alliance roster access.
4. A submitted membership link must belong to the active Alliance, be active, and not already link another roster entry in that Alliance.
5. Membership removal/leave does not erase game-player/roster identity or snapshot history.
6. Mark-left retains roster/player/history and is idempotent; normal destructive roster delete is not supported.
7. `alliance.view` controls member-safe roster reads; `kingdoms.manage` controls management.
8. Privileged roster mutations require recent password confirmation.
9. Alliance→Kingdom setting remains `alliance.manage`, not `kingdoms.manage`.

## 5. Workflows

### Add a player

A manager supplies observed player name, optional stable ID, optional active same-Alliance membership, optional role/rank, active/tracked state, joined date, and optional private manager notes. The action resolves/creates neutral identity and creates one tenant-owned roster entry transactionally.

### Edit a roster entry

Managers may change Alliance-owned observation/state fields and membership link. Stable game-player identity is not casual editable display metadata; changing identity requires explicit reconciliation rather than silently rebinding the roster entry.

### Mark left

Mark-left changes state to `left`, records `left_at`, retains neutral identity, roster entry, membership link, and history, and is safe to retry.

### Membership linkage gaps

The manager workspace surfaces both:

- active application memberships with no roster profile; and
- roster profiles with no application membership.

### Search/filter

Member roster filtering is tenant-scoped by name/stable ID, roster state, linked/unlinked membership, game role/rank, and current/stale/missing observation quality.

Current/stale/missing is derived from accepted snapshot history; missing means no snapshot, stale means latest accepted snapshot older than the documented 30-day threshold.

## 6. Authorization, tenancy and privacy

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

Custom-role permission union remains authoritative; controllers do not authorize by role name.

Ordinary member payloads may include observed/current game identity, role/state, linked member display name, and allowed latest observation fields. They exclude membership IDs/emails, manager notes, snapshot actor identity, and import-management metadata.

All predicates begin from the active Alliance. A search term matching another Alliance in the same Kingdom returns no cross-tenant roster data.

## 7. Persistence and query semantics

Roster entries are tenant-owned; KingdomPlayer is global neutral reference. Shared neutral identity never exposes another tenant's roster state, notes, membership link, snapshots, imports, or metrics.

Latest observation fields are projected from snapshots rather than copied into a mutable roster total/history replacement.

## 8. Events/integrations/background processing

Material roster mutations create audit records and transactional-outbox events such as:

- `kingdoms.roster_entry_created`;
- `kingdoms.roster_entry_updated`; and
- `kingdoms.roster_entry_left`.

The accepted runtime adds no Kingdoms-specific scheduler/worker for roster state. `kingdoms.*` events remain internal and excluded from generic external webhook fan-out.

## 9. Failure, idempotency and concurrency

- Repeated mark-left is idempotent.
- Membership links are re-resolved under the active Alliance and uniqueness rules.
- Stable-ID resolution is Kingdom-scoped.
- Cross-tenant roster/membership IDs fail closed.
- Display-name collision is not automatically resolved.

## 10. Operations and observability

Roster management should distinguish lifecycle state, membership linkage, snapshot quality, stable-ID identity, and provenance. Do not repair identity/history by direct destructive edits.

See [Kingdoms roster intelligence operations](operations/kingdoms-roster-intelligence.md).

## 11. Tests and validation

Accepted tests/evidence cover:

- same-Kingdom shared neutral identity with tenant isolation;
- stable-ID matching and no name-based automatic merge;
- membership-link tenant/active/uniqueness rules;
- member versus manager data minimization;
- mark-left history preservation/idempotency;
- cross-tenant search/mutation isolation; and
- internal-only Kingdoms event exposure.

See the [KINGDOMS-001 exit report](product/kingdoms-roster-intelligence-exit-report.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Snapshots](snapshots.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [Transfer planning](transfer-planning.md)
- [KINGDOMS-001 security review](security/kingdoms-roster-intelligence-security-review.md)
