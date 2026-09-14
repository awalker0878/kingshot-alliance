# Kingdom Map workspace implementation

This is the canonical design for Kingdom Explorer, Alliance Hive Builder, Multi-Alliance Kingdom Planning, and Plan versus Observed Reality. Execution status belongs only in the [delivery ledger](kingdom-map-workspace-delivery-ledger.md); runnable acceptance belongs in the [acceptance matrix](kingdom-map-workspace-acceptance.md); factual and artwork coverage belongs in the [asset catalogue](kingdom-map-workspace-assets.md).

## Ownership and supported contracts

GameWorld/KingdomMaps owns immutable V2 map releases, explicit rectangular geometry, sourced rules and provenance. Operations/TerritoryPlanning owns editable plans, their preferences, analysis, immutable revisions and publication. Intelligence owns accepted observations and evidence. ReadModels/TerritoryPlanning composes authorized reads; Workflows/NotificationDelivery orchestrates notifications through current owner contracts.

There is one supported map schema (V2) and one supported plan/interchange schema (2). Unsupported plan schema 1, scalar footprints and alternate payload names fail explicitly. Historical releases and revisions under the current contracts remain valid history. Presentation assets have independent versions and never determine footprints, sourced rules or factual confidence.

All protected operations resolve the active Governor and current scope. A hidden layer grants no authority. Owner Actions acquire scope locks, recheck current authority and expected revision, validate the complete candidate and return normalized state. Publication pins the saved layout checksum and map release checksum; it cannot publish unacknowledged local edits. Conflict responses preserve local work for explicit resolution without unscoped persistent browser drafts.

External identities are plan-local references. Real Governor assignment resolves through the existing roster contracts; an external participant is never inserted as a canonical Player or Alliance. Collaboration is asynchronous review with optimistic concurrency; no live synchronization is implied.

## Canonical workspace contracts

| Contract | Meaning and consumer |
| --- | --- |
| C1 layer availability | Materialized record counts and geometry determine availability; source corpus counts alone do not. Explorer, analysis and legends disclose partial/unavailable coverage. |
| C2 scene projection | A single typed scene projects released facts and authorized plan/observation objects. Canvas2D, hit testing, semantic lists and PNG/SVG exports share origins, geometry, identity and asset keys. |
| C3 layout command | Local bounded commands carry complete before/after state for undo/redo. Illegal transforms are rejected; pointer movement does not issue server writes. |
| C4 mutation receipt | The owner returns revision, normalized snapshot and layout checksum. The client adopts the receipt before publishing or continuing a revision-dependent mutation. |
| C5 analysis | Results identify map release, layout input checksum, algorithm version, assumptions and diagnostics. Finite-search failure is not proof that no global solution exists. Suggestions require explicit acceptance. |
| C6 visual rendition | Exports identify scope, world bounds, selected layers, title, legends, immutable release/revision/checksums and presentation pack. Art loading failures are explicit and memory/output limits are enforced. |

Canvas2D is the canonical interactive renderer. A spatial index, viewport culling, coalesced redraws and bounded artwork decoding support map-scale scenes. World coordinates retain their axis convention in RTL interfaces. Visual sprite anchors, legal footprints, coverage and minimum interaction targets are distinct.

## Product behavior

Explorer opens released facts without requiring creation of a plan. Search, bounded semantic object lists, pictures, provenance, layers, minimap, coordinates, fit controls, fullscreen and explicit inspect/pan/edit modes connect to the same scene. Responsive layouts retain access to the palette and inspector on tablet and mobile.

Editing supports exact placement and coordinates, snapping and previews, move/duplicate/delete, multiselect, grouping, legal transforms, locks, alignment and undo/redo. Hive previews use the selected immutable map and the existing layout, returning the requested count or explicit constraint diagnostics. Headquarters prerequisites and city-coverage preferences are disclosed as requirements of the planner, never invented game mechanics. Templates and alternative layouts require deliberate preview and acceptance.

Coverage/connectivity, uncovered positions, resource access, Banner efficiency, density and distance analysis distinguish sourced rules, observations, derived values and officer preferences. Straight-line distance and estimated travel are labeled separately from verified pathfinding. Unmaterialized resource data cannot support a complete resource-access claim.

Observed comparison retains the published plan, selected evidence, freshness and collection coverage. Uncertain identities, moved/unexpected objects and not-observed versus confirmed-missing states are explicit. No observation writes back into intent without an authorized user command.

Object comments, reviews, revision notes, assignments and scoped grants use current authorization and optimistic concurrency. Private read-only shares pin an immutable revision, recipient and permitted Alliance layers; access is revocable and checked again at use time. Notifications use real owner entry points with preferences, bounded fan-out, idempotency and send-time authority.

## Delivery order and assurance

Contracts and data precede scene/rendering; rendering and canonical persistence precede full editing and hive previews. Collaboration, observations and exports integrate through those authorities. Accessibility, localization, security and performance verification accompany each vertical slice. See the acceptance matrix for complete package coverage; no package is optional because an external source is blocked.

Verification starts from an empty disposable PostgreSQL database with locked dependencies. Focused checks during implementation do not certify final completion. The exact delivered commit requires all normal PHP, architecture, type/style, frontend/build, data/geometry, authorization/integration, browser/visual, dependency, recovery and deployment-representative gates. Screenshots are inspected and performance measurements distinguish actual released data from synthetic stress fixtures. The draft PR remains draft while required artwork, data, behavior or evidence is absent.
