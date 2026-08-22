# Data ownership

Status: Current — Architecture V3

Data ownership follows bounded-context capabilities, not table proximity or Eloquent reachability.

## Core rule

Every writable business aggregate has one owning context/capability. Other contexts may reference its identifier, query supported facts, consume events or invoke owner Actions, but they do not mutate the aggregate directly.

## Ownership summary

- **Accounts/Identity** — User account identity.
- **GameWorld/Players** — Player identity/claim and active Player state.
- **GameWorld/Kingdoms** — Kingdom/reference placement state.
- **GameWorld/KingdomMaps** — immutable/versioned map datasets, coordinate/geometry facts, provenance and sourced game placement rules.
- **GameWorld/Governance** — Kingdom governance assignments.
- **GameWorld/KingdomTransfers** — transfer-domain state.
- **Alliance** capabilities — Alliance lifecycle, membership/leadership/access, recruitment and content.
- **Operations/TerritoryPlanning** — mutable Alliance/Kingdom territory plans, plan participants/objects/groups/preferences, deterministic layout analysis and immutable published plan revisions.
- **Other Operations capabilities** — live operational Event/participation/planning/rally/KingPerk/result state.
- **Intelligence** capabilities — observations, ingestion, analytical/history state and sharing grants.
- **Communications/Delivery** — generic delivery/preference/attempt state.
- **Platform** capabilities — platform administration, Alliance platform administration, data governance, Event administration and integrations.

## Territory planning ownership boundary

Map truth and plan intent are deliberately separate.

```text
KingShot structure/zone/map coordinate fact
    -> GameWorld/KingdomMaps

Sourced rule that makes placement illegal
    -> GameWorld/KingdomMaps

Alliance plan to place HQ/Banner/city/Bear Trap
    -> Operations/TerritoryPlanning

Alliance preference such as target Bear radius
    -> Operations/TerritoryPlanning
```

A plan may reference a `kingdom_map_dataset_id` but cannot mutate the dataset. A published plan revision stores/pins the dataset identity/checksum needed to reproduce its historical meaning.

`BattlePlans` owns Event objectives/assignments, not spatial objects. Supported Events may hold a scalar reference to an immutable TerritoryPlanning revision.

## Scalar cross-context references

Cross-context identity is normally represented by stable scalar identifiers:

```text
user_id
player_id
alliance_id
kingdom_id
event_id
kingdom_map_dataset_id
territory_plan_revision_id
```

Keeping an identifier does not transfer ownership.

External Alliances/Governors included only for spatial planning are plan-local references when they have no application identity. TerritoryPlanning must not manufacture GameWorld/Alliance records to satisfy a drawing requirement.

## Relationship boundary

Eloquent relationships are an implementation convenience inside an ownership boundary. They are not a cross-context integration contract.

Cross-context navigation that makes a foreign aggregate appear locally owned is prohibited. The explicit Player -> Accounts User Eloquent relationship is not part of V3.

## Historical facts

Historical Event/Intelligence/contribution facts retain the identifiers and attribution relevant when the fact occurred. Later current membership or placement changes must not silently rewrite historical ownership/actor attribution.

Published territory-plan revisions are historical facts. Newer map datasets, current Player placement or later edits to the plan head must not rewrite them.

## Database

A single PostgreSQL database may host several contexts. Shared physical storage does not weaken logical ownership or permission boundaries.
