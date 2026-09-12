# GameWorld — KingdomMaps

Status: Implemented — Architecture V3; evidence-backed V2 release assurance pending merge

Implementation: `app/Contexts/GameWorld/KingdomMaps`

KingdomMaps owns neutral, versioned KingShot map truth used by planning and other consumers. It does not own Alliance planning intent.

## Ownership

KingdomMaps owns:

- immutable map-release identity, schema version and checksum;
- represented game-version/season boundary when known;
- coordinate bounds and coordinate-system metadata;
- fixed structures, facility catalogues, terrain/reference layers and exclusion/no-build geometry;
- building footprints/coverage definitions only when they are sourced game/map facts;
- sourced game placement and territory rules;
- per-fact/source provenance, confidence ceilings, source lineage and reuse-rights basis;
- the minimal canonical geometry values required to interpret and validate the current release schema.

KingdomMaps does not own:

- saved Alliance/Kingdom territory plans;
- plan-local external Alliances or Governors;
- hive preferences, preferred Bear radius or optimization targets;
- Event objectives/assignments;
- march-speed assumptions that are not sourced map/game truth.

Those planning concerns belong to `Operations/TerritoryPlanning`.

## Runtime release contract

Fresh deployment policy: schema V2 is the sole supported runtime contract. There is no V1 dual-read, migration shim or compatibility alias.

A released dataset is immutable and exposes at minimum:

```text
KingdomMapDataset
  id
  schema_version = 2
  release_status = released
  released_at
  observed_at
  game_version nullable
  season nullable
  predecessor_id nullable
  confidence
  checksum
  sources
  coordinate_system
  bounds
  object_types
    footprint { width, height }
    coverage { width, height } nullable
    variants/facts where applicable
  zones
  structures
  artifacts
  facilities (hydrated from immutable artifact)
  placement_rules
  resource_layers
```

The runtime checksum is SHA-256 over the immutable release file. Artifact manifests carry their own SHA-256 values and are verified before hydration. A newer release supersedes rather than mutates an older release. Published territory-plan revisions retain the exact release ID/checksum used when they were published.

`KingdomMapDatasetQuery::current()` resolves the newest released dataset by `released_at`; consumers that persist map truth must continue to pin the exact release rather than silently track `current()`.

## Current evidence-backed release

The current release is `kingshot-evidence-backed-2026-09-06-v2`.

It deliberately combines multiple evidence classes without flattening them into one truth label:

- Century Games Help Center facts are eligible for `official` confidence only on the specific rules they support;
- ksmapper coordinates/terrain/resource facts are reusable under the repository owner's explicit 2026-09-06 authorization and remain `community_observed` unless independently verified;
- independent community references can corroborate observations but do not become official evidence;
- deterministic application geometry can be `verified_observation` only for behavior the application itself defines and tests.

The release records source lineage so two references derived from the same upstream data cannot masquerade as independent corroboration. `KingdomMapSourceVerifier` enforces rights basis, lineage and confidence ceilings, including the recorded ksmapper authorization.

## Released researched facts

The V2 release includes:

- 1,200 × 1,200 KingShot coordinate bounds;
- Badland, Plains, Fertile, Ruins and central forbidden-zone geometry as community-observed map facts;
- King's Castle, four Turrets, four Fortresses and twelve Sanctuaries as blocking/reference structures with evidence-preserving confidence;
- an immutable facility artifact containing 4 Fortresses, 12 Sanctuaries and 74 Outposts;
- ksmapper corpus metadata for 6,499 resource nodes, 501 lake features and 1,948 mountain features under the recorded user-authorized reuse basis;
- official Century Games rule facts including the 285 Banner cap, the 75% Alliance-resource territory threshold, Banner-to-HQ connectivity, HQ legality in Badland/Plains and prohibition on Fertile Land, plus the documented Plains/Fertile progression prerequisites.

Corpus counts do not by themselves imply official confidence. Where the full source corpus is represented by a release reference rather than expanded inline, `data_state` records that distinction explicitly.

## Geometry contract

KingdomMaps uses:

- `Coordinate` for exact integer KingShot positions;
- `Rectangle` for bounds, object footprints, territory coverage, zones, structures and exclusions;
- explicit `{ width, height }` footprints and coverage rectangles rather than scalar `size`/radius compatibility values.

`TerritoryCoverageGeometry` is the shared server geometry service for footprint, coverage, covered-area ratio and connected-component calculations. The official 75% rule is read from the released `alliance_resource_territory_ratio` fact rather than hard-coded in consumers.

The official 75% rule applies to Alliance resource ownership. Governor-city coverage remains a TerritoryPlanning analysis semantic and is not promoted to an official game rule without separate evidence.

Object rotation remains the validated integer set `0 | 90 | 180 | 270`; richer polygons/circles are not introduced until a released factual layer requires them.

`PlacementValidator` is the authoritative server implementation. Browser geometry mirrors the same V2 behavior and is contract-tested against `tests/Contexts/GameWorld/KingdomMaps/Fixtures/territory-geometry.json`. The parity contract covers map bounds, rectangular footprint/object collision, fixed-structure collision/exclusion, zone restrictions, object caps, Banner-HQ connectivity, Bear-radius planning warnings, territory connectivity, coverage and analysis calculations.

## Rule taxonomy

A **map fact** describes what exists. A **game placement/territory rule** determines legal or producing state and must be sourced/versioned. A **planning preference** is an officer choice and cannot be promoted into KingdomMaps as a game rule.

The validator distinguishes:

- **violations** for dataset-backed legality/state failures such as map bounds, collisions, exclusions, zone restrictions, Banner cap and Banner components disconnected from any HQ;
- **warnings** for legal but undesirable planning state such as preferred Bear radius or multi-component territory that still has valid HQ anchoring;
- **suggestions** for optional planning improvements.

Laravel remains save authority even when the browser preview has already evaluated the same geometry.

## Operational assurance

KingdomMaps registers bounded-context console adapters through `KingdomMapsServiceProvider`:

```text
kingdom-maps:list
kingdom-maps:validate
kingdom-maps:verify-sources
kingdom-maps:diff <from> <to> [--json]
```

The dedicated `KingdomMaps Assurance` GitHub Actions workflow validates released schema/artifact/provenance state, verifies source rights and lineage, runs the focused PHP suite and executes PHP/browser geometry parity. Generic repository CI remains responsible for full PHP/frontend/architecture/container assurance.
