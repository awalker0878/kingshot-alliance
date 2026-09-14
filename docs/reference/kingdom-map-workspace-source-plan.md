# Kingdom Map workspace source plan

Status: Source reference — supplied by the user for PR #165, reconciled 2026-09-14.

Original attachment: `Pasted markdown(4).md`; SHA-256 `c4ecd5795c6267f618daba94fddff395a068235eb615a3817b2f3cf6979c0101`. Empty table scaffolding was removed for readability; the planning claims below describe the original audit, not current implementation. Current decisions and evidence belong in the [implementation](../product/kingdom-map-workspace-implementation.md), [acceptance matrix](../product/kingdom-map-workspace-acceptance.md) and [delivery ledger](../product/kingdom-map-workspace-delivery-ledger.md). The subsequent new-deployment instruction supersedes proposed legacy plan-document adapters: schema 2 remains the sole plan contract, map V2 the sole map contract.

Build the Kingdom Map as **one recognizable Kingshot
workspace for exploration, hive design, kingdom coordination, and comparison
with observed positions**, using authentic authorized artwork throughout.

The existing architecture is a useful foundation. The
largest gaps are drawable world data, artwork, dependable editing interactions,
and consistent exports. Completing those requires several coordinated
workstreams—not just replacing the rectangles with images.

This plan is based on main at **044a6be16e54b3bc2ee5ae9ca9adf6a9c9c5923c**,
rechecked before delivery. No application changes, commits, issues, pull
requests, merges, or deployments were made.

**The baseline contains substantial functionality, but it
is not a verified finished product.**

The lockfiles resolve Laravel **13.30.1**, Inertia
Laravel **3.3.1**, Vue **3.5.41**, Inertia Vue **3.6.1**, Vite **8.2.1**,
TypeScript **5.9.3**, and Playwright **1.62.1**. The declared frontend
toolchain is Node 24/npm 11. Keep this stack and the existing
Laravel/Vue/Inertia application. PHP lockfile, frontend lockfile.

**Capability**

**Classification**

**Evidence and implication**

Immutable V2 map releases

Implemented and evidenced

KingdomMapDatasetQuery, schema validation, artifact
 checksums, and the passing KingdomMaps Assurance workflow provide a reusable
 foundation.

Placement validation and analysis parity

Implemented and evidenced within existing fixtures

The JavaScript check passes 11 validation cases plus an
 analysis fixture. Coverage is narrower than the proposed product.

Fixed Castle/Turret/Fortress/Sanctuary geometry

Partial

Records exist and render as rectangles; searchable
 inspection, pictures, and richer interactions are missing.

Facility catalogue

Partial

An immutable artifact contains 90 coordinate records: 4
 Fortresses, 12 Sanctuaries, 74 Outposts. The canvas does not render the
 facilities collection.

Resource and terrain layers

Missing drawable data

The release records 6,499 resources, 501 lakes, and 1,948
 mountains as authorized\_source\_corpus\_reference; these are not materialized
 coordinate layers.

Authentic object artwork

Missing

Existing Kingshot SVGs are frontend illustrations. No
 object artwork manifest or complete icon/sprite/detail-image collection
 exists.

Basic editing

Partial

Placement, exact coordinates, selection, move, duplicate,
 delete, grouping, undo/redo, and keyboard nudging exist.

Drag cancellation

Defective

TerritoryCanvas binds pointercancel to onPointerUp, which
 can emit a move.

Rotation

Partial

Rotation is stored, but geometry and rendering do not
 consume it. Group rotation does not transform group positions. Existing
 square footprints obscure this gap.

Hive generation

Partial

HiveLayoutGenerator supplies swirl and banner\_pad; it does
 not generate against the selected release, existing obstacles, and complete
 constraints.

Saving and immutable publication

Partial

Owner Actions, expected revisions, auditing, and immutable
 snapshots exist. The UI can publish persisted state while showing unsaved
 edits.

Draft recovery

Partial

The editor restores localStorage snapshots automatically
 using a plan/revision key; actor isolation, recovery choice, and conflict
 handling need completion.

Multi-alliance plans

Partial

Linked/external identities, colors, visibility, counts,
 and kingdom scope exist; delegated editing, review, and richer layer controls
 need work.

Plan versus Observed

Partial

Authorized reconciliation and uncertainty handling exist,
 with feature tests. Its separate SVG visualization uses circles rather than
 the planner’s renderer/artwork.

JSON interchange

Partial

Schema-1 plan documents have preview/commit and atomicity
 tests. Exact pin requirements and new workspace fields need a stricter shared
 contract.

PNG/SVG export

Defective and incomplete

Rectangle exports omit artwork and several editor
 settings; nonzero origins are mishandled.

Accessibility/localization

Partial

Semantic object controls and territory translations exist.
 Large-list bounds, touch workflows, focus behavior, and export localization
 need completion.

Complete interactive journeys/performance

Unverified

Existing browser tests check rendering and screenshots,
 rather than the full editing lifecycle or realistic scene performance.

The principal implementation evidence is in the editor,
canvas, export engine, map release, and browser tests.

Checks actually performed during this planning audit:

- Existing      JavaScript geometry check: **passed**, 11 validation cases and one      analysis fixture.
- Existing      export source-contract check: **passed**. It does not demonstrate PNG      rendering or artwork fidelity.
- Additional      export probe: **reproduced the origin defect**. With bounds starting at      (100,200), a city at (120,220) exports at (120,-122) instead of (20,78).
- Exact      fetched facility bytes: **matched the release’s SHA-256 manifest**; all      90 facility records contain integer coordinates.
- PHP      execution: unavailable locally. No local PHP, browser, visual, or      performance-suite success is claimed.

GitHub reports the following results for the pinned main
commit:

**Gate**

**Observed result**

KingdomMaps Assurance

Passed

CodeQL

Passed

CI

Failed PHP regression; frontend passed;
 container/staging/recovery skipped

Architecture V3 Verification

Failed architecture contracts

Visual Regression

Failed visual database preparation

PR #163 is merged. **Draft PR #164** continues hardening;
its inspected head was 8b12a54bb20c7f908838ef380b60edcb82b180de. Treat its
changes as unmerged dependencies, particularly Operations access and shared
frontend/verification changes.

The map-evidence and observed-reality branches inspected are
behind main. The older feature/alliance-territory-hive-planner branch has
diverged and contains the retired V1 map contract: do not use it as the
implementation base.

Documentation also needs reconciliation: map documents still
describe branch implementation or pending merge, while another ledger says
merge ready. Neither label proves that the present canvas exposes the complete
dataset.

**Preserve the existing ownership model and introduce
presentation contracts around it.**

**Owner**

**Responsibility**

GameWorld/KingdomMaps

Immutable releases, coordinate interpretation, fixed
 facts, factual geometry, sourced restrictions, provenance, confidence

Operations/TerritoryPlanning

Editable intent, participants, slots, groups, annotations,
 templates, analysis preferences, review, publication, sharing

Intelligence/Evidence

Private source artifacts and their processing/review
 lifecycle

Intelligence/Observations

Accepted observed positions, identity uncertainty, capture
 coverage, freshness, invalidation

ReadModels/TerritoryPlanning

Authorized composition of map, plan, roster, and
 observations

Existing Operations event owners

Objectives and event behavior; references to immutable
 published territory revisions

Communications/Delivery

Notification preferences, routing, delivery attempts,
 retries

Frontend presentation registry

Artwork selection, loading, anchors, display variants,
 export imagery; no placement authority

These boundaries already follow ADR 0009. Preserve the
atomic composition established by ADR 0069.

Establish these explicit contracts before parallel
implementation:

**Contract**

**Required content**

**C1 — Map layer availability**

Release ID/checksum, layer key, materialization state,
 available/expected counts, coverage extent, artifact hashes, confidence,
 unavailable reason

**C2 — Scene projection**

Stable entity reference,
 factual/planned/observed/annotation kind, geometry reference, asset key,
 layer, authorized display data, selection/validation state

**C3 — Layout command**

Expected revision, typed complete layout, validated
 variant/slot references, bounded annotations/preferences, mutation ID

**C4 — Mutation result**

Accepted revision, normalized layout or checksum,
 validation results, mutation receipt; structured conflict/authority/retry
 errors

**C5 — Analysis result**

Input checksums, algorithm version, assumptions, component
 results, uncertainty, unavailable metrics

**C6 — Visual rendition**

Plan revision/checksum, map pin, asset-pack ID/checksum,
 layer/export specification, locale/font references, output checksum

Map schema **V2 remains the sole map runtime contract**.
Plan import/snapshot schema versions are a different namespace: evolving
today’s schema-1 *plan documents* must not reintroduce schema-1 *map
datasets*.

New artwork versions must never modify map releases or
placement outcomes. Historical publications without an artwork pin should
identify the pack used for a new rendition; they cannot claim to reproduce an
unrecorded historical appearance.

**The product should connect four complete journeys.**

**Experience**

**Journey**

**Completion criteria**

Kingdom Explorer

Open a released map, search a facility, jump to
 coordinates, inspect its picture and provenance, save a view

Works without creating a plan; unavailable layers are
 explicit; keyboard and touch users can reach the same objects

Alliance Hive Builder

Choose HQ variant/Bear position, generate alternatives,
 assign Governors, resolve violations, save and reopen

Requested slots exist or a precise shortfall is reported;
 server accepts the layout; assignments and preferences persist

Multi-Alliance Kingdom Planning

Add linked/external participants, distinguish their
 territory, delegate permitted edits, compare proposals, review and publish

No fake canonical identities; layer permissions hold
 server-side; publication preserves the reviewed revision

Plan versus Observed

Select a publication and accepted observation, inspect
 drift and uncertain matches, review coverage differences

Both inputs remain pinned; partial evidence cannot imply
 disappearance; comparison does not mutate either source

A desktop wireframe should allocate the central area to the
map:

**Left: approximately 224 px**

**Center: flexible map area**

**Right: approximately 300 px**

Object/facility search

Compact command bar: mode, undo/redo, save, review,
 publish, export

Inspector title and object picture

Palette with authentic icons

Map, selection/drag preview, labels and coordinate grid

Type/variant, Alliance, exact coordinates, slot identity

Layers and Alliance filters

Minimap; fit map/selection/Alliance; fullscreen

Footprint, coverage, provenance, issues

Saved views/bookmarks

Status strip: coordinates, scale, validation, save state

Contextual actions and object comments

At tablet widths, show one side drawer at a time. At mobile
widths:

**Screen region**

**Contents**

Top

Plan/title, explicit Inspect/Pan/Edit mode, save state

Main area

Map with unobstructed navigation and selected-object
 marker

Floating controls

Search, coordinate jump, zoom, fit, layers

Bottom sheet

Palette, inspector, object list, or validation—one active
 sheet

Persistent action area

Relevant placement confirmation or save/retry action

Support fullscreen, minimap, coordinate readout/jump,
searchable facilities and objects, saved views, bookmarks,
fit-to-map/selection/Alliance, and shortcut discovery.

Interaction rules:

- Inspect      selects without moving; Pan navigates; Edit enables placement and      transforms.
- Wheel/trackpad      zoom preserves the world point beneath the pointer.
- Two      pointers enter pinch/pan; adding a second pointer cancels an object drag.
- Drag      previews show footprint, artwork, snap coordinates, and validation before      acceptance.
- Escape,      pointercancel, capture loss, and interrupted navigation discard the      gesture.
- Camera      persistence is separate from layout persistence.
- Keyboard      shortcuts operate only within the workspace and never consume typing in      fields.

Required screen states are explicit:

**State**

**User-facing behavior**

Loading

Map frame and controls remain stable; show which essential
 layer is loading

Empty plan

Offer place, generate hive, or import; retain world
 reference map

Read-only

Inspection and authorized export remain available

Forbidden/revoked

Clear private workspace state and deny further
 reads/writes

Stale evidence

Show capture age and retain uncertainty

Conflict

Preserve local intent; show server revision and
 object-level differences

Unavailable layer

Disabled/marked layer with reason and coverage information

Missing artwork

Typed fallback plus visible asset failure; bounded retry

Failed save

Keep working changes; distinguish validation, conflict,
 authority, and temporary failure

Recovery available

Offer review/recover/discard against the current
 authorized revision

**Use a complete object catalogue with distinct factual and
planning identities.**

For every record, require a stable key, category, owner,
coordinates or geometry reference, provenance, display label, and supported
interactions. Fixed objects can be inspected/bookmarked/referenced; editable
objects additionally use authorized layout commands. Observations retain
capture identity and uncertainty. Annotations never participate in game-rule
collision checks.

The following values are **encoded in the inspected release**,
not a claim that all mechanics were independently reverified during this audit.

**Class**

**Identity and status**

**Geometry/coverage in baseline**

**Variants, restrictions, and interaction**

HQ

Plan object key; Operations-owned

3×3 footprint; 15×15 coverage

Badland and Plains variants exist in map facts but need a
 first-class plan variant field; release cap 2

Banner

Plan object key

1×1; 7×7 coverage

Release cap 285; HQ connectivity; zone/progression
 evidence

Governor city

Plan object key plus linked or plan-local identity

2×2; no territory-emitting coverage

Open/reserved/assigned slots; release cap 100 per Alliance

Bear Trap

Plan object key

3×3; no territory-emitting coverage

Release cap 2; preferred radius is an officer preference

King’s Castle

kings\_castle; fixed GameWorld fact

6×6; recorded exclusion 6 tiles

Inspect, picture, target-distance reference; cannot be
 dragged

Turrets

Existing directional structure keys

2×2

Four positions; orientation labels do not automatically
 require rotated artwork

Fortresses

Existing fortress\_\* keys

6×6; recorded exclusion 60; city exception

Four; join facility metadata to structural geometry
 without drawing duplicates

Sanctuaries

Existing sanctuary\_\* keys

6×6; recorded exclusion 60; city exception

Twelve; same identity-join requirement

Outposts

Existing facility keys

Coordinates materialized; footprint absent from facility
 records

74 records, levels 1–4; display as reference markers until
 sourced footprints exist

Resources

Stable source-record key

Coordinates/footprints not materialized

Type/level/ownership semantics require source ingestion;
 distinguish Alliance mines from other resources

Lakes/mountains

Stable source-feature key

Geometry not materialized

Rendering and placement blocking require explicit
 geometry; pictures cannot define movement rules

Map regions

Existing zone keys

Nested rectangular regions

Badland, Plains, Fertile, Ruins, forbidden area; render
 nesting correctly

Restricted areas

Release geometry/rule key

Current zone/structure exclusions

Patterned overlays; inspector explains supported
 restrictions

Annotations

Plan-local annotation key

Point, line, bounded area, or text geometry

Labels, arrows, rally targets, notes; no game placement
 authority

The official HQ page currently supports HQ placement in
Badland/Plains and the cited region milestones. The release’s separate
Banner-technology source URL returned 404 during this audit; locating and
recording its replacement is a data-resolution task. [Century Games HQ guidance](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/9407-what-is-plains-may-i-build-the-hq-on-fertile-land-which-is-the-best-place-to-set-up-my-hq/?utm_source=chatgpt.com).

Define layer composition consistently across editor and
export:

**Order**

**Layer**

**Default behavior**

1

Terrain

Visible when materialized; full opacity

2

Regions

Muted fill/borders; nested-region precedence

3

Restricted/no-build areas

Patterned overlay; validation remains active when hidden

4

Fixed structures

Recognizable artwork and searchable references

5

Facilities

Deduplicated structural/facility projection

6

Resources

Type/level filters; availability/coverage visible

7

Alliance territory

Translucent color plus pattern; per-Alliance control

8

Planned buildings/cities

Editable only under current permissions

9

Observed positions

Distinct outlines; evidence-authorized only

10

Coverage/connectivity

Optional derived overlay

11

Comparison results

Drift vectors and uncertainty indicators

12

Annotations

Per-category and author filters

13

Validation/selection

Highest-priority actionable overlays

Each layer supports visibility, opacity where meaningful,
legend entries, filtering, and applicable presentation locking. Save presets
such as **Explore**, **Hive**, **Kingdom coordination**, **Validation**,
and **Observed comparison**. Hiding or locking a layer never changes server
authorization or validation.

**Artwork is a required delivery stream with explicit
missing inputs.**

Your supplied Kingshot artwork authorization is the
project’s authorization basis. Record it without expanding it to unrelated
assets or changing factual confidence.

Every supported family needs a palette/list icon, map
sprite, and inspector picture. The same authorized master can produce all
three. Current source availability and implementation coverage are separate:
authentic masters are absent from the inspected repository, and the
registry/pipeline is also missing.

**Required artwork mapping**

**Supported variants**

**Icon**

**Sprite**

**Detail picture**

headquarters.badland

Badland HQ

Required

Required

Required

headquarters.plains

Plains HQ

Required

Required

Required

banner.default

Standard Banner

Required

Required

Required

governor\_city.default

Base city; additional skins only when supplied/mapped

Required

Required

Required

bear\_trap.default

Bear Trap

Required

Required

Required

castle.kings\_castle

Castle

Required

Required

Required

turret.default

Four position labels; artwork variants if genuinely
 distinct

Required

Required

Required

fortress.default

Four fixed identities

Required

Required

Required

sanctuary.default

Twelve fixed identities

Required

Required

Required

outpost.builders\_guild

Levels 1, 3

Required

Required

Required

outpost.armory

Levels 2, 4

Required

Required

Required

outpost.scholars\_tower

Levels 1, 3

Required

Required

Required

outpost.arsenal

Levels 2, 4

Required

Required

Required

outpost.forager\_grove

Level 1

Required

Required

Required

outpost.harvest\_altar

Level 1

Required

Required

Required

outpost.drill\_camp

Level 2

Required

Required

Required

outpost.frontier\_lodge

Level 3

Required

Required

Required

resource.\<verified-type>

Actual ingested types/levels

Required

Required

Required

terrain.lake, terrain.mountain

Actual supported feature families

Required

Required

Required

Regions/restrictions/annotations

Application presentation symbols

Required

Vector treatment

Legend/inspector illustration

The Outpost families and level combinations above come from
the facility artifact. Different level badges may share art only after visual
review confirms that this matches the source artwork.

Required source deliveries, acquired from supplied files or
an authorized source:

- Building      masters: both HQs, Banner, Governor city, Bear Trap, Castle, Turret,      Fortress, Sanctuary.
- Outpost      masters: the eight named families and supported level appearances.
- Resource/terrain      masters: matched to the actual source corpus.
- Source      index: original filename, origin, game/art version when known,      authorization reference, checksum.
- Complete      spatial corpus: resource coordinates/types/footprints and lake/mountain      geometry, with a pinned source snapshot.

A proposed manifest entry should resemble this **unapproved
template**, with missing values preventing release:

{


"schema\_version": 1,

  "pack\_id":
"kingshot-map-art-1",


"asset\_key": "headquarters.badland",

  "object\_type":
"headquarters",


"variant\_key": "badland\_headquarters",

  "source":
{


"original\_file": null,


"source\_reference": null,


"authorization\_basis": "User-supplied Kingshot artwork
authorization",


"sha256": null

  },


"representations": {

    "icon":
{ "sizes": [32, 64], "files": [] },


"sprite": { "sizes": [128, 256, 512],
"files": [] },


"detail": { "sizes": [512, 1024], "files":
[] }

  },

  "anchor":
{ "x": 0.5, "y": 1.0, "reviewed": false },


"visual\_bounds": null,

  "orientation":
"upright",


"label\_key":
"territory.objects.headquarters.badland",


"load\_priority": "visible-first",


"fallback\_key": "fallback.headquarters",


"review\_status": "source\_required"

}

Every produced file additionally records MIME type, pixel
dimensions, encoded bytes, checksum, trimming/padding transform, and approved
orientation mappings.

Automate decoding, format validation, metadata removal,
resizing, compression, checksums, naming, and manifest validation. Require
human visual review for identity, transparency edges, cropping, ground contact,
silhouette recognition, and consistency across representations.

Use controlled same-origin, content-addressed URLs. Load
icons first, visible sprites next, and detail pictures on demand. Bound
requests, decoded-image caches, and retries. Evaluate atlases using measured
request/texture costs; do not make a giant atlas mandatory.

Sanitize SVGs through an allowlist or rasterize them during
preparation. Reject scripts, event handlers, foreignObject, uncontrolled
external references, oversized dimensions, and unsupported content. Missing
assets use recognizable typed fallbacks but **cannot pass final artwork
acceptance**.

Update the independently-authored-only statement in frontend
architecture guidance to permit this authorized, provenance-recorded asset
pipeline.

**Extend Canvas 2D behind a shared scene model.**

**Option**

**Assessment**

**Decision**

Improved Canvas 2D

Fits existing code; no renderer dependency;
 straightforward image drawing and raster export; requires deliberate
 culling/indexing

**Recommended baseline**

Focused PixiJS adapter

Sprite batching and scene tooling are useful; adds
 dependency, GPU lifecycle, texture management, and separate SVG-export
 considerations

Adopt only if the early representative-scene benchmark
 demonstrates a material need

Full DOM/SVG world

Useful for small overlays and semantic controls;
 unsuitable as the default representation of thousands of world objects

Use selectively

PixiJS documentation currently recommends its WebGL renderer
for production, describes WebGPU caveats, and lists its Canvas fallback as
forthcoming. A PixiJS choice therefore cannot assume a built-in Canvas
fallback. Its performance guidance also requires deliberate culling and texture
management. [Renderer documentation](https://pixijs.com/8.x/guides/components/renderers?utm_source=chatgpt.com), [performance guidance](https://pixijs.com/8.x/guides/concepts/performance-tips?utm_source=chatgpt.com).

Implement a renderer-independent scene containing image,
rectangle, path, text, and interaction-bound primitives. Canvas and SVG exports
consume that scene. Vue retains controls and semantic lists; no individual DOM
element is required for each world object.

Rendering design:

- Spatial      index for reference geometry, planned footprints, and selectable visual      bounds.
- Viewport      culling with artwork-overhang margins.
- Separate      static and dynamic draw work; cache useful static regions.
- requestAnimationFrame      invalidation instead of immediate redraw on every reactive change.
- Resize      backing buffers only when viewport/DPI changes.
- Label      priorities and collision suppression; selected objects retain labels.
- Bounded      decoded-image cache; release buffers and object URLs on      replacement/unmount.
- Far      zoom: compact symbols and aggregate resource indication. Medium:      recognizable icons. Near: detailed sprites and exact placement overlays.
- Offscreen      processing is a progressive optimization; feature detection and a      main-thread fallback remain required.

These techniques align with [Canvas optimization guidance](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API/Tutorial/Optimizing_canvas?utm_source=chatgpt.com) and the [OffscreenCanvas API](https://developer.mozilla.org/en-US/docs/Web/API/OffscreenCanvas?utm_source=chatgpt.com).

Coordinate fidelity is shared infrastructure:

- Preserve      southwest game origin and increasing world Y.
- Logical      footprint, coverage rectangle, artwork dimensions, and interaction target      are distinct.
- Position      upright artwork using an approved ground-contact anchor, normally the      footprint’s bottom center.
- For a      cropped/exported world rectangle, subtract its X/Y origin before      projection.
- Apply      90°/270° footprint width/height swapping only through the shared geometry      contract.
- Group      rotation transforms every member around a declared pivot; it is not just a      rotation-field update.
- Upright      building artwork remains upright unless an approved directional asset      exists.
- Use      deterministic z-order based on layer and ground position.
- RTL      changes control layout, not world axes.
- Unknown      Outpost footprints remain reference markers, not invented collision      rectangles.

The performance targets below are **proposed gates, not
measured achievements**:

**Measure**

**Proposed target**

Existing build budgets

Preserve initial JS ≤230,400 bytes; app entry ≤20,480;
 largest page chunk ≤73,728; stylesheet ≤131,072 under the existing checker

First useful map

≤2.5 s desktop; ≤4 s mobile at 10 Mbps/100 ms RTT, cold
 cache

Map-specific initial data plus essential imagery

≤1.5 MiB compressed; remaining detail assets/layers
 demand-loaded

Pointer-to-preview latency

p95 ≤50 ms
 desktop; ≤100 ms mobile

Interactive frame time

p95 ≤16.7 ms
 desktop; ≤33.3 ms mobile

Decoded artwork cache

≤96 MiB desktop; ≤48 MiB mobile

Workspace JS heap

Target ≤160 MiB desktop; ≤96 MiB mobile, measured
 separately from image/GPU allocations

Supported editable scale

Existing caps: 5,000 objects, 50 Alliances, 500 groups

Complete reference scene

90 facilities joined with structures, plus all
 materialized resources/terrain

Stress scene

25,000 reference/synthetic items; synthetic data clearly
 test-only

Standard raster export

Up to 16 megapixels desktop/8 mobile; larger output uses
 bounded tiles or an explicit supported limit

Regression threshold

Investigate >10% deterioration against approved
 measurements or any absolute budget breach

Measure on a documented 16-GB desktop/laptop, a midrange
Android device, an iPhone, and a tablet. Record exact models/browser versions.
Use released fixtures, materialized corpus fixtures, dense hives, and
reproducible synthetic stress scenes. Existing build thresholds come from
performance-budgets.json.

**Complete editing around coherent commands and current
authority.**

**Operation**

**Required semantics**

Place/move/exact coordinate edit

Integer snapping, live preview, one command per accepted
 gesture, full layout revalidation

Duplicate

New stable keys; preserve chosen metadata; explicitly
 handle Governor assignments to avoid duplicate occupancy

Delete

Explain linked comments/assignments; preserve historical
 references

Multiselect/box selection

Defined containment/intersection mode; include partially
 offscreen members correctly

Group/rotate

Stable group identity; declared pivot; all members
 transformed and validated together

Align/distribute/bulk coordinates

Preview affected objects and complete geometry result
 before acceptance

Locks

Default atomic refusal when a selected operation includes
 locked members; offer an explicit “editable objects only” action

Undo/redo

Semantic command history with bounded memory; preserve
 coherent group operations

Templates

Versioned relative layout, compatible object/variant
 definitions, explicit identity remapping and placement preview

Hive generation

Deterministic constraints, requested slot count, spacing,
 selected Bear, HQ connection, obstacles, and explicit infeasibility
 diagnostics

Introduce typed object fields for variant\_key, individual
lock state, slot state, and stable plan-local Governor references. Keep
plan-local identities independent from application Players and Alliances. Store
annotations separately from placeable game objects.

The save lifecycle should be:

Use existing owner Actions and scope-lock ordering.
Revalidate current active-Governor authority inside the transaction. Reuse the
shared authority-context transport rather than inventing a second
header/interceptor.

Recommended persistence behavior:

- Debounced      autosave after a valid completed command, approximately two seconds idle.
- Manual      save flushes the same queue.
- One      save in flight; later local commands remain pending.
- Idempotency      key binds actor, plan, operation, and request hash. Reusing it with      different content fails.
- Current      authorization is checked even when returning an idempotent receipt.
- Revision      conflicts return structured base/current information; preserve local      intent for explicit three-way reconciliation.
- Server      recovery drafts may retain invalid working layouts, but remain separate      from validated plan heads.
- Recovery      is owner-scoped, size/retention bounded, and reauthorized. Private drafts      should not be automatically persisted in unscoped browser storage.
- Publish      waits for successful save, confirms the accepted layout checksum, and      publishes that exact revision.
- A      reviewed checksum becomes stale after another edit.
- Import/restore      installs the returned normalized layout and resets appropriate history; a      partial Inertia reload must not leave stale local refs active.

The present SaveTerritoryPlan remains the authoritative
mutation foundation. Add strict scalar validation at the owner boundary rather
than relying on integer coercion or HTTP-only checks.

**Make analysis useful, explainable, and reproducible.**

Retain and extend TerritoryCoverageAnalyzer,
TerritoryLayoutAnalyzer, and the shared geometry fixtures.

Required analysis includes:

- Territory      union and HQ-anchored/disconnected components.
- Covered/uncovered      Governor footprints under explicitly documented planning semantics.
- Resource      access and Alliance-resource ownership only where corresponding      geometry/type evidence exists.
- Banner      count, marginal useful coverage, and redundant coverage.
- Hive      density with a stated denominator.
- Distances      to selected Bears, facilities, Castle, and custom targets.
- Side-by-side      alternatives with identical map pins and comparable assumptions.

For optional aggregate scoring, implement explicit
components and saved weights. A reasonable initial planning preset is coverage
40%, useful Banner efficiency 25%, target distance 20%, and density 15%. Each
component needs its formula, normalization range, and explanation. These
weights are officer preferences. Missing components produce an unavailable
score unless the user explicitly selects a reduced component set.

Suggestions use deterministic inputs, algorithm version,
seed where applicable, bounded search, and constraints. Return candidates plus
explanations and limitations. Preview/accept is required; suggestions never
silently mutate the plan or claim global optimality.

Keep three distance concepts separate:

1. Straight-line      distance under the declared coordinate/anchor convention.
2. Approximate      time from a visible calibration or seconds-per-tile assumption.
3. Actual      pathfinding, available only after verified movement topology exists.

Do not infer movement barriers from decorative terrain art.

Plan-versus-Observed should reuse
TerritoryReconciliationQuery, while replacing the separate circle-only
visualization with the shared scene:

- Pin      published revision, observation, map checksums, and comparison policy.
- Distinguish      moved, unexpected, missing, not observed, unresolved identity, ambiguous      match, and incompatible coordinates.
- Preserve      current partial-observation behavior: positive coverage can be      demonstrated; absent coverage may remain unknown.
- Explain      match candidates and tolerance.
- Show      freshness and observation extent.
- Paginate      histories and semantic results; never silently truncate without      continuation.
- Support      authorized multi-alliance comparisons without broadening access to private      evidence.
- Observations      never overwrite plans.

**Reuse integrations through explicit contracts.**

**Integration**

**Owner and contract**

**Authorization/retry requirements**

**Acceptance**

Governor assignment

Membership/Players owner queries → authorized choices

Bounded search; selected off-page identity revalidated;
 current membership checked on save

Revoked/moved Governor cannot remain newly assigned
 through a stale tab

Event positioning

Existing attach/detach Actions and published-revision
 queries

Event scope and plan scope; immutable revision reference

Editing head does not change event positioning

Evidence intake

Evidence upload/review/commit → Observations

Existing private artifact lifecycle and exactly-once
 handoff

Duplicate commit does not duplicate observations

Object comments/review

TerritoryPlanning owner Actions

Plan/layer authority; stable object key; revision/checksum
 binding

Deleted/moved object retains understandable historical
 comment context

Notifications

NotificationDelivery workflow → NotificationIntent

After-commit intent, bounded recipient selection,
 preferences, source reauthorization, idempotency

Retry produces one intended message; revocation suppresses
 delivery

Audit

Existing AuditRecorder

Same transaction as state changes; no raw layout/private
 evidence in logs

Late audit failure rolls back the command

Add territory notification source authorization to the
existing workflow-owned registry. Do not reuse officer.brief as a generic type
with unsuitable permissions. Notify for review requests, assignments,
publication, and explicit briefings—not pointer movement.

Optimistic concurrency is the required collaboration model.
Live presence and simultaneous co-editing are genuinely optional enhancements.
They require a separate design for authenticated channels, expiring presence,
reconnect, command sequencing, and conflict recovery; they are not
prerequisites for complete asynchronous collaboration.

**Import, export, sharing, and permissions need their own
completion gates.**

JSON imports should use a shared strict contract for preview
and commit. Require exact map ID/checksum, valid references, supported
versions, bounded bytes/depth/counts, and deterministic duplicate handling.

- Default      import mode: replace the editable layout after preview.
- Explicit      append mode: remap keys and resolve identity/group collisions.
- Preview      returns diagnostics and a document hash; commit verifies the same content      and current revision.
- Unknown      future versions fail clearly.
- Existing      schema-1 plan files use an explicit tested document adapter when the plan      format evolves.
- CSV      coordinate interchange specifies columns, integer semantics, type/variant      mapping, and assignment behavior. Neutralize spreadsheet-formula injection      on exported text.
- External      planner formats need named adapters with mapping fixtures.

Export scope must support viewport, selection, Alliance, and
full map. PNG/SVG output includes chosen layers, recognizable artwork, labels,
grid/coordinates, annotations, legend, title, plan revision, map release,
observation freshness where included, and asset-pack version.

Use the common scene and embedded approved artwork. Add text
wrapping and legend pagination, nonzero-origin transforms, deterministic font
handling, output-size estimation, bounded raster allocation, tiled output where
supported, cancellation, and resource cleanup. Thumbnails and officer briefing
exports are explicit render presets.

Authorize export creation and download against current
scope. A private thumbnail must never be written beneath public /images/, which
the existing service worker caches. Previously downloaded files cannot be
revoked.

**Operation**

**Required authority**

View plan

Existing Alliance/Kingdom territory-view permission and
 current scope

Edit/layout/import

Existing territory-manage permission; delegated layer
 grant where introduced

Publish/archive

Whole-plan management; publication validates the
 saved/reviewed revision

Export

Current view permission for every included data source

Observe/upload/review evidence

Existing Intelligence permissions; plan access alone is
 insufficient

Review/comment

Explicit plan review grant plus current plan/layer
 visibility

Share/revoke

Explicit share authority within the owner scope

Shared-link access

Authenticated recipient, valid scope/token, current source
 authorization

Platform administration

No Alliance/Kingdom/Intelligence bypass

Private links pin a revision and allowed layers, expire, and
support revocation. Tokens are stored hashed, excluded from logs, and checked
at access time. Sharing does not create canonical membership. Public sharing is
a separate optional product decision.

Canvas must have a synchronized searchable semantic object
list with exact coordinates and keyboard actions. Bound or virtualize it while
preserving focus and off-page selection. Use text/patterns alongside color,
touch controls of approximately 44 CSS pixels, reduced motion, visible focus,
live announcements, and the repository’s localization primitives across all 17
territory locales. The bitmap itself does not expose object semantics to
assistive technology. [Canvas accessibility considerations](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API?utm_source=chatgpt.com).

**Execute the work in the following dependency order.**

Path abbreviations below expand to exact repository
directories:

**Prefix**

**Directory**

GW/

app/Contexts/GameWorld/KingdomMaps/

TP/

app/Contexts/Operations/TerritoryPlanning/

RM/

app/ReadModels/TerritoryPlanning/

FE/

resources/js/features/territory-planner/

UI/

resources/js/pages/Kingdom/Territory/

Every file marked **new** is proposed, not present in the
audited baseline. Effort is relative: **S** narrow slice, **M** several
connected changes, **L** substantial cross-surface work.

1. **KM-00      — Establish the executable baseline.**
         **Outcome/owner:** Delivery lead records what exists, fails, and      remains unknown.
         **Files:** New      docs/product/kingdom-map-workspace-implementation-plan.md,      kingdom-map-workspace-asset-catalogue.md,      kingdom-map-workspace-acceptance.md, and      kingdom-map-workspace-delivery-ledger.md; update stale map/frontend status      references.
         **Contract/steps:** Record C1–C6 proposals, immutable source pins,      overlapping PR ownership, defect reproductions, and test evidence.
         **Dependencies/assets:** None. **Acceptance:** Every requirement      maps to a task and gate; baseline failures remain visible. **Risk/effort:**      Confusing merged and proposed behavior; **S**.
2. **KM-01      — Establish strict workspace contracts.**
         **Outcome/owner:** TP/GW/RM agree on typed data before UI expansion.
         **Reuse/files:** Extend FE/engine/types.ts, TP/Actions/SaveTerritoryPlan.php,      TP/Services/TerritoryPlanImport.php,      TP/Services/TerritoryPlanSnapshotBuilder.php; new      TP/ValueObjects/TerritoryLayoutDocument.php,      RM/Queries/TerritoryWorkspaceQuery.php, FE/engine/scene.ts.
         **Contract/steps:** C1–C6; strict integers/enums/references; variant,      slot, annotation, and presentation-version design; additive migration      plan.
         **Dependencies/assets:** KM-00; asset keys only. **Acceptance:**      Direct owner calls and HTTP reject the same malformed data; map V1 remains      rejected. **Risk/effort:** Historical schema confusion; **M**.
3. **KM-02      — Materialize complete reference layers.**
         **Outcome/owner:** GW provides drawable, validated      facilities/resources/terrain.
         **Reuse/files:** Extend GW/Services/KingdomMapArtifactLoader.php,      KingdomMapSchemaV2Validator.php, KingdomMapSourceVerifier.php, and      PlacementValidator.php; proposed immutable artifacts      resources/data/kingdom-maps/artifacts/kingshot-resources-r1.json,      kingshot-terrain-r1.json, and successor release resources/data/kingdom-maps/kingshot-spatial-complete-v2-r1.json.
         **Contract/steps:** C1; acquire pinned corpus, validate      coordinates/types/geometry, join duplicate facilities, resolve stale      sources, implement blocking geometry in PHP/TS.
         **Dependencies/assets:** KM-01; complete spatial source required. **Acceptance:**      Counts, extents, hashes, rendering delivery, and placement behavior agree;      no fabricated records. **Risk/effort:** Missing source geometry; **L**.
4. **KM-03      — Deliver approved artwork and registry.**
         **Outcome/owner:** Frontend/artwork owner supplies recognizable      representations everywhere.
         **Files:** New resources/data/kingdom-map-art/manifest.v1.json,      FE/assets/registry.ts, FE/assets/loader.ts,      scripts/import-kingdom-map-art.mjs, scripts/check-kingdom-map-art.mjs, and      content-addressed assets under public/images/kingshot/map/; update      frontend artwork guidance.
         **Contract/steps:** C2/C6; import, sanitize, derive variants, review      anchors, generate mappings and contact sheets.
         **Dependencies/assets:** KM-01; all listed master packs. **Acceptance:**      Every core family resolves icon/sprite/picture; all approved files      validate; no fallback counts as complete. **Risk/effort:** Missing      masters/poor trimming; **L**.
5. **KM-04      — Build the shared renderer and coordinate engine.**
         **Outcome/owner:** Frontend renders accurately and responsively.
         **Reuse/files:** Refactor FE/components/TerritoryCanvas.vue; new      FE/engine/camera.ts, spatial-index.ts, canvas-renderer.ts, labels.ts;      extend geometry.ts and server coverage geometry.
         **Contract/steps:** C2; common transforms, rotated footprints, culling,      scheduled redraw, asset loading, semantic hit targets.
         **Dependencies/assets:** KM-01; representative KM-03 art and KM-02      fixtures. **Acceptance:** Nonzero-origin/rotation fixtures,      cancellation behavior, initial performance benchmark, editor/export scene      parity. **Risk/effort:** Art/geometry divergence; **L**.
6. **KM-05      — Complete Kingdom Explorer.**
         **Outcome/owner:** RM/frontend offer exploration without a plan.
         **Files:** New UI/Explorer.vue, FE/components/ObjectPalette.vue, LayerPanel.vue,      ObjectInspector.vue, Minimap.vue, CoordinateNavigator.vue; extend RM      controller/provider routes.
         **Contract/steps:** C1/C2; search, pictures, coordinate jump,      bookmarks, saved views, fullscreen, fit controls, responsive drawers.
         **Dependencies/assets:** KM-02–04. **Acceptance:** Find an      Outpost/facility, inspect its picture/provenance, save a view, reopen it      on desktop/touch/keyboard. **Risk/effort:** Large lists and overloaded      mobile controls; **L**.
7. **KM-06      — Complete editing operations.**
         **Outcome/owner:** TP frontend supports predictable manipulation.
         **Files:** Refactor UI/Editor.vue; new      FE/composables/useTerritoryCommands.ts, useTerritorySelection.ts,      FE/engine/transforms.ts; reuse inspector/palette.
         **Contract/steps:** C3; command history, previews, selection, group      transforms, individual locks, alignment/distribution, bulk coordinates.
         **Dependencies/assets:** KM-01/KM-04/KM-05. **Acceptance:** Mixed      locks, offscreen group members, pointer cancellation, undo/redo, exact      edits, and illegal previews behave consistently. **Risk/effort:**      Partial operations silently changing intent; **L**.
8. **KM-07      — Complete saving, recovery, conflicts, and publication.**
         **Outcome/owner:** TP preserves the exact layout users intend to      save/publish.
         **Files:** Extend save/publish/import/restore Actions and receipts; new      TP/Models/TerritoryRecoveryDraft.php,      TP/Actions/SaveTerritoryRecoveryDraft.php,      FE/composables/useTerritoryPersistence.ts, and      database/migrations/2026\_09\_14\_000100\_add\_territory\_workspace\_persistence.php.
         **Contract/steps:** C3/C4; serialized autosave, mutation IDs,      actor-scoped recovery, conflict review, normalized-state installation,      save-before-publish.
         **Dependencies/assets:** KM-01/KM-06; no new art. **Acceptance:**      Simultaneous edits never overwrite newer work; lost responses retry      safely; publication checksum matches reviewed saved state. **Risk/effort:**      Races and private draft retention; **L**.
9. **KM-08      — Complete hive templates and Governor slots.**
         **Outcome/owner:** TP produces usable, assignable layouts.
         **Files:** Extend TP/Services/HiveLayoutGenerator.php; new      TP/Services/TerritoryTemplateInstantiator.php,      TP/Actions/SaveTerritoryTemplate.php, TP/Models/TerritoryPlanIdentity.php,      FE/components/HiveBuilderPanel.vue; extend bounded roster choices in RM.
         **Contract/steps:** C3/C5; map-aware generation, spacing, alternative      previews, open/reserved/assigned slots, stable external identities.
         **Dependencies/assets:** KM-02/KM-06/KM-07; HQ/Banner/Bear/city art. **Acceptance:**      Exact requested count or explicit infeasibility; all accepted layouts      save; identity/slot/template round trips pass. **Risk/effort:**      Generator produces attractive but illegal layouts; **L**.
10. **KM-09      — Complete analysis and suggestions.**
          **Outcome/owner:** TP provides explainable strategic comparisons.
          **Files:** Extend TerritoryLayoutAnalyzer.php,      TerritoryCoverageAnalyzer.php, FE/components/MarchAnalysisPanel.vue; new      TP/Services/TerritorySuggestionGenerator.php,      FE/components/LayoutComparisonPanel.vue.
          **Contract/steps:** C5; metrics, weights, provenance, uncertainty,      bounded deterministic suggestions and preview/accept.
          **Dependencies/assets:** KM-02/KM-08; target imagery from registry. **Acceptance:**      Known-fixture metrics, reproducibility, unavailable-data handling, and no      implicit mutation. **Risk/effort:** Invented precision/optimization      claims; **L**.
11. **KM-10      — Integrate observed reality into the shared workspace.**
          **Outcome/owner:** RM/Intelligence expose understandable drift without      losing provenance.
          **Files:** Extend RM/Queries/TerritoryReconciliationQuery.php, its      controller, UI/Reconciliation.vue, and Observation owner projections; new      FE/components/ObservationComparisonPanel.vue.
          **Contract/steps:** C2/C5; shared artwork, extent/freshness overlays,      matching explanations, bounded histories/results, authorized participant      comparison.
          **Dependencies/assets:** KM-04/KM-09; corresponding object sprites. **Acceptance:**      Partial evidence preserves unknown absence; incompatible maps fail closed;      publications and observations remain unchanged. **Risk/effort:**      Private evidence leaks and false “missing” results; **L**.
12. **KM-11      — Add asynchronous collaboration and review.**
          **Outcome/owner:** TP supports coordinated work and explicit approval.
          **Files:** New TP/Actions/CommentOnTerritoryObject.php,      AssignTerritoryPlanSlot.php, ReviewTerritoryPlan.php,      GrantTerritoryPlanAccess.php; corresponding models; new      FE/components/PlanReviewPanel.vue and additive collaboration migration.
          **Contract/steps:** Stable object keys, exact reviewed checksum,      revision notes, per-layer grants, bounded discussions.
          **Dependencies/assets:** KM-07/KM-08; registry icons. **Acceptance:**      Edits invalidate stale review; comments survive historical lookup;      delegated editors cannot change other layers. **Risk/effort:** Grant      escalation; **L**.
13. **KM-12      — Complete event, notification, audit, and roster integrations.**
          **Outcome/owner:** Existing owners receive explicit territory      contracts.
          **Files:** Extend event attach/query components; new      app/Workflows/NotificationDelivery/Actions/QueueTerritoryNotifications.php,      Services/TerritoryNotificationPublisher.php,      TP/Queries/TerritoryNotificationEligibilityQuery.php; extend      CurrentNotificationSourceAuthorization.php.
          **Contract/steps:** Immutable revision references, after-commit      intents, preference-aware delivery, bounded recipient pages and replay      keys.
          **Dependencies/assets:** KM-11 and relevant hardening closeout;      briefing thumbnails. **Acceptance:** Retry/revocation/event-profile      integration tests pass; no movement spam. **Risk/effort:** Stale      authority or duplicate fan-out; **M**.
14. **KM-13      — Complete interchange.**
          **Outcome/owner:** TP imports and exports complete portable plan      documents.
          **Files:** Extend TerritoryPlanImport.php, ImportTerritoryPlan.php,      controller, snapshot builder; new      TP/Services/TerritoryCoordinateTableAdapter.php,      FE/components/ImportPreviewPanel.vue.
          **Contract/steps:** C3/C4; version adapters, exact pins, strict limits,      document hash, replace/append modes, deterministic key mapping.
          **Dependencies/assets:** KM-01/KM-07/KM-08; asset references, no      embedded untrusted executables. **Acceptance:** Full round trips,      duplicate handling, malicious input rejection, stale-preview denial,      atomic failure. **Risk/effort:** Lossy conversion; **M**.
15. **KM-14      — Complete artwork-bearing visual exports.**
          **Outcome/owner:** TP/frontend produce reliable PNG/SVG, thumbnails,      and briefs.
          **Files:** Refactor FE/engine/export.ts; new      FE/engine/export-layout.ts, FE/components/ExportDialog.vue,      TP/Actions/CreateTerritoryRendition.php,      TP/Models/TerritoryVisualRendition.php; extend authorized read      projections.
          **Contract/steps:** C6; scopes, common scene, embedded assets, legends,      origin handling, font/locale support, pixel budgets, cancellation.
          **Dependencies/assets:** KM-03/KM-04/KM-09/KM-10/KM-13. **Acceptance:**      Pixel/geometry comparison with editor; reopened SVG/PNG; no hidden/private      layer leakage. **Risk/effort:** Memory, clipping, tainted canvases; **L**.
16. **KM-15      — Complete private sharing.**
          **Outcome/owner:** TP shares approved immutable views safely.
          **Files:** New TP/Actions/CreateTerritoryShare.php,      RevokeTerritoryShare.php, TP/Queries/TerritorySharedRevisionQuery.php,      TP/Models/TerritoryShare.php, UI/Shared.vue, and share migration.
          **Contract/steps:** Revision/layer scope, hashed token, authenticated      recipient, expiration/revocation, current authority at access/download.
          **Dependencies/assets:** KM-11/KM-14; pinned presentation pack. **Acceptance:**      Cross-scope, expired, revoked, switched-Governor, and stale-tab access      denied. **Risk/effort:** Treating possession of a link as unrestricted      authority; **M**.
17. **KM-16      — Complete accessible, localized interaction.**
          **Outcome/owner:** Frontend supports equivalent      keyboard/touch/assistive journeys.
          **Files:** New FE/components/TerritoryObjectList.vue,      KeyboardHelpDialog.vue; extend all territory locale modules, validation      labels, export text, and playwright.config.ts with tablet coverage.
          **Contract/steps:** Semantic list, bounded rows, focus restoration,      non-color states, touch sizes, reduced motion, long translations, RTL      controls.
          **Dependencies/assets:** KM-05–15; localized asset labels. **Acceptance:**      Complete keyboard/touch journeys and reviewed desktop/tablet/mobile/RTL      evidence. **Risk/effort:** Virtualization breaking focus or semantics; **M**.
18. **KM-17      — Prove security and performance budgets.**
          **Outcome/owner:** Each owner closes boundary risks; frontend meets      measured budgets.
          **Files:** Extend scripts/check-performance-budgets.mjs, relevant owner      tests, asset validation, and public/service-worker.js if public-cache      bounds require it; new      tests/ReadModels/TerritoryPlanning/Browser/TerritoryPerformance.spec.ts.
          **Contract/steps:** Load/import/export limits, current-scope cache      isolation, malformed image/SVG cases, representative-device profiling,      memory cleanup.
          **Dependencies/assets:** Complete materialized fixtures and approved      art. **Acceptance:** Absolute budgets and regression thresholds pass;      private data never enters public caches. **Risk/effort:** Benchmarks      passing only on synthetic/light scenes; **L**.
19. **KM-18      — Release verification and documentation closeout.**
          **Outcome/owner:** Delivery lead produces a reviewable complete      candidate.
          **Files:** Update four product documents, context/frontend guidance,      runbooks, and .github/workflows/kingdom-maps-assurance.yml path coverage.
          **Contract/steps:** Traceability audit, all journeys, artifact/asset      review, required workflows, schema/recovery verification, rollback      rehearsal.
          **Dependencies/assets:** All essential packages. **Acceptance:** One      immutable candidate SHA contains all passing required evidence; no      undocumented blocked layer or placeholder artwork. **Risk/effort:**      Mistaking a green narrow gate for product completion; **M**.

The dependency structure permits parallel implementation
after contracts are settled:

Data acquisition and artwork preparation can proceed
together. Renderer infrastructure can proceed against approved fixtures. Final
renderer validation requires representative art and materialized data.
Accessibility and security checks accompany each package; KM-16/17 complete the
cross-product proof.

**Make acceptance traceable to behavior, files, assets, and
executed evidence.**

**Test ID**

**Requirements/tasks**

**Files/suites to extend**

**Required evidence**

T01

Release integrity, KM-01/02

tests/Contexts/GameWorld/KingdomMaps/Feature/KingdomMapDatasetV2Test.php

Corrupt/missing artifacts rejected; exact
 counts/extent/pins verified

T02

Geometry, KM-02/04/06

Shared geometry fixture, existing PHP parity tests,
 geometry script

Nonzero origins, edges, rotations, exclusions, union
 coverage, randomized invariants

T03

Art, KM-03

New art-check script and
 tests/ReadModels/TerritoryPlanning/Frontend/AssetRegistry.test.ts

Complete icon/sprite/detail mapping; malformed assets
 rejected; reviewed contact sheets

T04

Scene fidelity, KM-04/05

New Frontend/TerritoryScene.test.ts and explorer browser
 cases

Facility deduplication, layer states, culling, hit tests,
 approved artwork

T05

Editing, KM-06

New Frontend/TerritoryCommands.test.ts,
 Browser/TerritoryEditing.spec.ts

Pointer cancellation, mixed locks, group rotation, undo,
 offscreen selection

T06

Persistence, KM-07

Lifecycle, atomicity, scope-ordering tests; new
 persistence browser cases

Concurrent saves, lost-response retry, actor switch,
 recovery, exact publication

T07

Hive/identity, KM-08

New
 tests/Contexts/Operations/TerritoryPlanning/Feature/TerritoryHiveGenerationTest.php

Requested counts, infeasibility, slots, membership
 revocation, templates

T08

Analysis, KM-09

Existing analysis/parity tests plus suggestion tests

Exact metric components, deterministic candidates,
 missing-data handling

T09

Reconciliation, KM-10

Existing observed-reality/boundary tests

Partial/complete evidence, ambiguous matching,
 stale/incompatible inputs, no writes

T10

Collaboration/integration, KM-11/12

New review/access tests; existing event integration;
 Workflow notification tests

Stale review, layer isolation, immutable event links,
 retry and preferences

T11

Interchange, KM-13

Existing import tests plus coordinate-table tests

Round trips, duplicate keys, unsupported versions,
 malicious/deep/oversized inputs

T12

Export/share, KM-14/15

Extend export script; new browser export/share tests

Artwork, legends, origins, bounds, fonts, revocation,
 unauthorized-layer exclusion

T13

Accessibility/localization, KM-16

Territory browser suite and localization check

Keyboard completion, screen-reader review, touch, long
 labels, all locale keys, RTL

T14

Performance/security, KM-17

Performance suite, owner authorization tests, cache/asset
 tests

Device traces, dense scenes, memory, bounded work,
 private-cache isolation

T15

Complete product, KM-18

Cross-surface browser journeys and release evidence

Explore → create → assign → fix → compare → review →
 publish → export → reopen → revoke

Organize new tests within the current owner-based Unit,
Feature, Integration, Frontend, and Browser directories. Do not recreate
retired tests/v3 or flat test folders.

Runnable commands derived from the repository include:

npm run check\:territory-geometry

npm run check\:territory-export

npm run check\:territory-localization

php artisan kingdom-maps\:validate

php artisan kingdom-maps\:verify-sources

php artisan kingdom-maps\:list

php artisan test --fail-on-empty-test-suite
tests/Contexts/GameWorld/KingdomMaps

php artisan test --fail-on-empty-test-suite
tests/Contexts/Operations/TerritoryPlanning

php artisan test --fail-on-empty-test-suite
tests/ReadModels/TerritoryPlanning

npm run test\:visual --
tests/ReadModels/TerritoryPlanning/Browser/TerritoryPlanner.spec.ts

composer test\:architecture

composer lint\:check

composer types\:check

npm run check

PHP/browser commands require the repository’s normal
prepared environment; they were not executed locally in this audit.

Use focused tests per coherent commit. Independent Node,
PHP, and artifact checks can run concurrently when they do not share mutable
fixtures. Keep current browser-worker constraints unless fixture isolation is
explicitly improved. Run full relevant release verification once on the final
candidate, and rerun only when subsequent changes invalidate evidence.

Visual proof must cover desktop, tablet, and mobile at
world/regional/hive zoom; all artwork families; dense scenes; readonly mode;
invalid placement; partial observations; missing art; long labels; RTL; and
artwork-bearing exports. Review screenshot differences rather than
automatically replacing fingerprints.

**Release through additive changes and retain a concrete
rollback path.**

- Resolve      or incorporate the relevant hardening work before claiming the final      candidate passes. Baseline CI failures remain separately attributed.
- Preserve      existing map releases and published snapshots; introduce new immutable      artifacts and additive workspace tables.
- Gate      new workspace capabilities by server-owned configuration during rollout.
- Pilot      Explorer, then editing, then collaborative publication/sharing with      representative plans.
- Record      candidate SHA, map hashes, asset manifest hash, migration evidence,      screenshots, performance traces, workflow URLs, and rollout configuration.
- Expand      KingdomMaps workflow path coverage to include relevant Vue components,      assets, read models, and browser contracts.
- Roll      back capability exposure or presentation-pack selection without deleting      plans, publications, source artifacts, or recovery history.
- Stop      new writes first if a persistence defect appears; retain authorized      historical reads.
- Avoid      destructive down-migrations against new user data. Use a forward repair      where necessary.

The four proposed product documents should contain:

**Document**

**Proposed authoritative contents**

kingdom-map-workspace-implementation-plan.md

Baseline SHA; product journeys; ownership; C1–C6; renderer
 decision; packages; dependencies; rollout

kingdom-map-workspace-asset-catalogue.md

Every family/variant; source and authorization basis;
 original/derived hashes; icon/sprite/picture paths; anchors; reviewer;
 approved/missing status

kingdom-map-workspace-acceptance.md

Requirement → task → production file → asset → test
 mapping; actual result; evidence URL; tested SHA; limitations

kingdom-map-workspace-delivery-ledger.md

Stable task ID; owner; dependency; state; commits;
 executed checks; unresolved finding; completion evidence

Use ledger states planned, in\_progress, blocked\_input, implemented\_unverified,
and verified. “Verified” requires behavior and evidence, not merely a merged
file.

**Risk/assumption**

**Resolution owner/task**

**Gate**

Full resource/terrain corpus is absent

GW, KM-02

Materialized validated geometry; otherwise explicitly
 blocked, never “complete”

Authentic masters are absent

Artwork owner, KM-03

Approved core coverage across all three representations

Some sourced mechanics/URLs are stale

GW, KM-02

Replacement evidence and lineage; preserve old immutable
 releases

Outpost footprints are unknown

GW, KM-02

Keep reference-marker semantics until supported geometry
 exists

Existing hardening gates fail

Hardening owners plus KM-00/18

Passing containing final candidate; no silent exemptions

Canvas may miss large-scene targets

Frontend, KM-04/17

Early benchmark; focused PixiJS adapter only if justified

Recovery storage exposes private data

TP/frontend, KM-07/17

Current-actor isolation and retention/cache tests

Historical artwork was not pinned

TP/presentation, KM-01/14

Explicit rendition provenance; no false
 historical-fidelity claim

Live game integration is unavailable

Product assumption

Manual/imported observations remain clearly identified

Live co-editing, public sharing, decorative skins

Optional enhancements

Separate justified scope; essential product remains fully
 delivered

**Start implementation with KM-00, followed immediately by
the contract and regression slice.**

Inspect these exact files together:

- resources/js/pages/Kingdom/Territory/Editor.vue
- resources/js/features/territory-planner/components/TerritoryCanvas.vue
- resources/js/features/territory-planner/engine/export.ts
- resources/js/features/territory-planner/engine/types.ts
- app/Contexts/Operations/TerritoryPlanning/Actions/SaveTerritoryPlan.php
- app/Contexts/Operations/TerritoryPlanning/Actions/PublishTerritoryPlan.php
- app/Contexts/GameWorld/KingdomMaps/Services/KingdomMapArtifactLoader.php
- tests/ReadModels/TerritoryPlanning/Browser/TerritoryPlanner.spec.ts
- scripts/check-territory-export.mjs

The first proposed commits should:

1. Add      the four planning documents with this baseline, requirements, asset gaps,      and ownership.
2. Establish      MapLayerAvailability, TerritoryScene, TerritoryLayoutDocument,      mutation-result, and visual-rendition interfaces.
3. Add      meaningful regression cases for cancelled dragging, nonzero-origin export,      and publishing with unsaved edits.
4. Record      the failing baseline evidence before repairing those behaviors in their      owning packages.

**Exit criteria:** exact baseline recorded; all requested
capabilities assigned; missing inputs have owners and gates; no unmerged
behavior counted as implemented; the first three defects have reproducible
tests; and data, artwork, renderer, and persistence work can proceed against
agreed interfaces.
