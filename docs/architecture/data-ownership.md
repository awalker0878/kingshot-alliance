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
- **GameWorld/Progression** — immutable/versioned factual KingShot progression catalogue releases, source registry, reconciliation/conflict outcomes and source-scoped community convention records.
- **GameWorld/Governance** — Kingdom governance assignments.
- **GameWorld/KingdomTransfers** — transfer-domain state.
- **Alliance** capabilities — Alliance lifecycle, membership/leadership/access, recruitment and content.
- **Operations/TerritoryPlanning** — mutable Alliance/Kingdom territory plans, plan participants/objects/groups/preferences, deterministic layout analysis and immutable published plan revisions.
- **Operations/Results** — accepted Event result facts/metrics, including accepted Bear Hunt battle-report ledgers and recomputed damage aggregates.
- **Other Operations capabilities** — live operational Event/participation/planning/rally/KingPerk state, including Governor-saved tactical formation/loadout intent.
- **Intelligence/Evidence** — uploaded game evidence, immutable source/attempt provenance, extracted candidates/confidence, review/correction history, duplicate decisions, commit attempts and retention state; never the resulting domain fact.
- **Other Intelligence capabilities** — observations, ingestion, analytical/history state and sharing grants, including append-only Governor progression observations.
- **Communications/Delivery** — generic delivery/preference/attempt state.
- **Platform** capabilities — platform administration, Alliance platform administration, data governance, Event administration and integrations.

## Factual progression ownership boundary

Catalogue truth, Governor observations and tactical intent are deliberately separate.

```text
Versioned Hero / Gear / Building / Research / Pet / Master reference fact
    -> GameWorld/Progression

Source-scoped named troop-ratio convention
    -> GameWorld/Progression

Observed Governor Hero/gear/progression state at a point in time
    -> Intelligence/Roster

Governor-saved Hero/formation loadout or Event planning intent
    -> Operations/Rallies or the applicable Operations owner
```

A Roster observation may retain a scalar `progression_dataset_id`, checksum and canonical Hero IDs to preserve historical meaning. An Operations loadout may pin the same release. Neither reference allows Intelligence or Operations to mutate catalogue truth.

A later progression release never rewrites an older Governor observation or saved loadout. Missing observation data remains unknown and does not mean a Governor lacks a Hero or item unless the captured evidence explicitly represents a complete roster.

## Screenshot evidence ownership boundary

Evidence and accepted domain meaning are deliberately separate.

```text
Uploaded screenshot + checksum + machine attempts + review
    -> Intelligence/Evidence

Accepted Bear Hunt battle report + report entries + damage aggregate
    -> Operations/Results

Player identity / current Player facts
    -> GameWorld/Players
```

Evidence may retain scalar destination IDs/receipts for provenance. Operations may retain scalar `source_evidence_id`/commit identifiers for traceability. Neither reference permits cross-context Eloquent navigation or mutation.

Deleting or purging evidence does not delete an accepted Operations result. Correcting an accepted report is an Operations owner action that deterministically recomputes its aggregates.

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
occurrence_id
evidence_id
commit_attempt_id
kingdom_map_dataset_id
progression_dataset_id
progression_hero_id
territory_plan_revision_id
```

Keeping an identifier does not transfer ownership.

External Alliances/Governors included only for spatial planning are plan-local references when they have no application identity. TerritoryPlanning must not manufacture GameWorld/Alliance records to satisfy a drawing requirement.

## Relationship boundary

Eloquent relationships are an implementation convenience inside an ownership boundary. They are not a cross-context integration contract.

Cross-context navigation that makes a foreign aggregate appear locally owned is prohibited. The explicit Player -> Accounts User Eloquent relationship is not part of V3.

## Historical facts

Historical Event/Intelligence/contribution facts retain the identifiers and attribution relevant when the fact occurred. Later current membership or placement changes must not silently rewrite historical ownership/actor attribution.

Machine extraction attempts and review revisions are historical Evidence facts. A later retry, improved extractor, corrected Player name or deleted binary does not silently rewrite them.

Published territory-plan revisions are historical facts. Newer map datasets, current Player placement or later edits to the plan head must not rewrite them.

Published progression releases are historical reference facts. A newer source or reconciliation result creates a new immutable release; it does not mutate the checksum or meaning pinned by an existing observation/loadout.

## Database

A single PostgreSQL database may host several contexts. Shared physical storage does not weaken logical ownership or permission boundaries.
