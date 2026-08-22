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

TerritoryPlanning does not own map coordinates/structures as world truth, Player identity, Alliance membership, Event objectives or provider delivery state.

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

## Historical truth

Editable head state is normalized. Publishing creates an immutable schema-versioned snapshot pinned to the selected KingdomMap dataset ID/checksum. Restore/clone creates new editable state and never changes an existing published revision.

Downstream Operations integrations reference `territory_plan_revision_id`, not a mutable plan head.

## External references

A Kingdom plan may contain Alliances/Governors that do not exist as application records. These remain explicit plan-local references. The planner never creates fake Player or Alliance records merely to draw a map.

## Validation contract

Validation returns structured results:

```text
violations[]  blocking sourced game/map rule failures
warnings[]    legal placements that violate planning preferences
suggestions[] optional deterministic improvements
```

A boolean-only placement contract is insufficient.

## Accessibility

Canvas rendering is never the sole control surface. Every material planned object and mutation has a synchronized semantic DOM representation with exact coordinates and non-color status messages.

## BattlePlans boundary

`Operations/BattlePlans` continues to own Event objectives and assignments. Spatial objects and territory revisions are not stored as objective metadata. An Event may reference a published TerritoryPlanning revision through an explicit scalar reference/read composition.
