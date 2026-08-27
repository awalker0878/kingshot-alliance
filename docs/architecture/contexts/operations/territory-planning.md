# Operations — TerritoryPlanning

Status: Active delivery — Architecture V3

Implementation target: `app/Contexts/Operations/TerritoryPlanning`

TerritoryPlanning owns mutable Alliance/Kingdom spatial planning intent built against immutable map truth supplied by `GameWorld/KingdomMaps`.

## Ownership

TerritoryPlanning owns:

- territory plans and plan scope;
- participating application-linked or plan-local external Alliances;
- planned HQs, Banners, Governor cities, Bear Traps and other supported planned objects;
- groups, generated hive layouts and plan-local labels;
- planning preferences and assumptions;
- deterministic layout/march/coverage analysis;
- optimistic plan revision/concurrency state;
- immutable published plan revisions/snapshots;
- import preview/commit and export representations;
- references from supported Operations workflows to immutable published plan revisions.

TerritoryPlanning does not own map coordinates/structures as world truth, Player identity, Alliance membership, Event objectives, provider delivery state, Evidence artifacts or observed current hive state.

Observed HQ/Banner/Bear/Governor coordinates are `Intelligence/Observations` facts. TerritoryPlanning does not gain ownership of them merely because the planner renders a comparison.

## Write boundary

Writes follow:

```text
HTTP adapter
 -> TerritoryPlanning Action
 -> transaction
 -> revalidate active Player + concrete plan scope
 -> lock plan head when required
 -> verify expected revision
 -> validate dataset/rules/geometry
 -> persist coherent normalized layout
 -> audit
```

Pointer movement is browser working state and is not one HTTP mutation per drag. A save submits a coherent proposed layout against an expected revision.

Screenshot Evidence and accepted spatial observations never invoke a TerritoryPlanning mutation. There is no `sync observed to plan`, `apply screenshot`, or equivalent implicit write path. If a future product action intentionally creates planning intent from an observation, it must use the normal TerritoryPlanning Action boundary and produce ordinary revision history; it may never rewrite a published revision.

## Historical truth

Editable head state is normalized. Publishing creates an immutable schema-versioned snapshot pinned to the selected KingdomMap dataset ID/checksum. Restore/clone creates new editable state and never changes an existing published revision.

Downstream Operations integrations and Plan vs Observed reconciliation reference `territory_plan_revision_id`, not a mutable plan head.

A later observation, screenshot, map dataset release or edit to working state cannot change the historical snapshot/checksum of a published revision.

## Plan vs Observed composition

TerritoryPlanning remains desired state. It supplies only owner projections needed for comparison:

- authorized plan detail and revision choices;
- an immutable published revision snapshot;
- existing deterministic coverage geometry services.

`ReadModels/TerritoryPlanning` owns the authorized cross-context composition:

```text
immutable published Territory revision
        +
authorized Intelligence spatial observation
        +
pinned GameWorld/KingdomMaps geometry
        -> derived reconciliation projection
```

The read model derives drift, missing/not-observed, unexpected objects, structure movement, coverage deltas, freshness and compatibility. None of those values are persisted as TerritoryPlanning truth.

The comparison must preserve uncertainty. Observation coverage/completeness determines whether absence can become `missing`; ambiguous Banner matching remains ambiguous; stale data stays visibly stale; incompatible map coordinate systems fail closed.

## External references

A Kingdom plan may contain Alliances/Governors that do not exist as application records. These remain explicit plan-local references. The planner never creates fake Player or Alliance records merely to draw a map.

Observed Governor identity may be reviewed against an application Player or against one of these plan-local identities, but that reviewed observation does not create/rename either identity owner.

## Validation contract

Validation returns structured results:

```text
violations[]  blocking sourced game/map rule failures
warnings[]    legal placements that violate planning preferences
suggestions[] optional deterministic improvements
```

A boolean-only placement contract is insufficient.

`TerritoryCoverageAnalyzer` may be reused by the reconciliation read model to evaluate planned and observed geometry. Reuse does not transfer observed-state ownership into Operations and no derived coverage result is stored.

## Accessibility

Canvas rendering is never the sole control surface. Every material planned object and mutation has a synchronized semantic DOM representation with exact coordinates and non-color status messages.

The Plan vs Observed surface follows the same rule: visual overlays are supplemental; exact planned/observed coordinates, distance, identity, coverage and discrepancy status are available through semantic DOM/table content.

## BattlePlans boundary

`Operations/BattlePlans` continues to own Event objectives and assignments. Spatial objects and territory revisions are not stored as objective metadata. An Event may reference a published TerritoryPlanning revision through an explicit scalar reference/read composition.
