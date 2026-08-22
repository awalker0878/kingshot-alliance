# GameWorld — KingdomMaps

Status: Implemented — Architecture V3; release assurance in progress

Implementation: `app/Contexts/GameWorld/KingdomMaps`

KingdomMaps owns neutral, versioned KingShot map truth used by planning and other consumers. It does not own Alliance planning intent.

## Ownership

KingdomMaps owns:

- map dataset identity and schema version;
- represented game-version/season boundary when known;
- coordinate bounds and coordinate-system metadata;
- fixed structures, terrain/reference layers and exclusion/no-build geometry;
- building footprints/coverage definitions only when they are sourced game/map facts;
- sourced game placement rules;
- provenance, observation time, confidence/status and checksum for every released dataset;
- the minimal canonical geometry values required to interpret and validate the current dataset schema.

KingdomMaps does not own:

- saved Alliance/Kingdom territory plans;
- plan-local external Alliances or Governors;
- hive preferences, preferred Bear radius or optimization targets;
- Event objectives/assignments;
- march-speed assumptions that are not sourced map/game truth.

Those planning concerns belong to `Operations/TerritoryPlanning`.

## Dataset contract

A released dataset is immutable. At minimum it exposes:

```text
KingdomMapDataset
  id
  schema_version
  game_version nullable
  observed_at
  source_label
  source_uri nullable
  confidence
  checksum
  coordinate_system
  bounds
  zones
  structures
  terrain/reference layers when evidence-gated
  resource-node references when evidence-gated
  sourced placement rules
```

The runtime checksum is SHA-256 over the immutable dataset file. A newer dataset supersedes rather than mutates an older release. Published territory-plan revisions retain the exact dataset ID/checksum that was used when they were published.

## Current community-observed profile

The first profile, `kingshot-community-observed-2026-08-21-v1`, is deliberately labelled `community_observed`. Its source boundary is `Bleezy-D/Alliance-Layout-Planner` at commit `c0162ed5f3b41bb997bac970f0c73d1545e622fb` (observed 2026-08-21). The upstream README declares the project MIT licensed and describes its map as community-derived; the upstream repository does not contain a separate `LICENSE` file. The application therefore records the source and license note without presenting these coordinates, footprints or placement observations as official Century Games data.

Community planners remain discovery/evidence sources. Their coordinates, node sets, footprint sizes or rules do not become official product truth merely because they are useful. Community-observed values may be represented only with truthful provenance/confidence and a version/observation boundary. Unknown stays unknown.

## Geometry contract

The implemented schema currently needs two immutable geometry primitives:

- `Coordinate` for exact integer KingShot positions;
- `Rectangle` for bounds, footprints, zones, structure footprints and exclusion checks.

Object rotation is represented as the validated integer set `0 | 90 | 180 | 270`; distance is a deterministic calculation result used by TerritoryPlanning analysis rather than a separately persisted geometry object. Circle, polygon or richer footprint abstractions are not part of the current contract and should be introduced only when a versioned dataset actually requires them.

`PlacementValidator` is the authoritative server implementation. Browser geometry mirrors the same behavior for immediate preview and is contract-tested against the shared `tests/v3/Fixtures/territory-geometry.json` fixture. The parity contract covers map bounds, footprint/object collision, fixed-structure collision/exclusion, zone restrictions, object caps, Bear-radius planning warnings, disconnected-territory warnings, coverage and analysis calculations.

## Rule taxonomy

A **map fact** describes what exists. A **game placement rule** determines whether a placement is legal and must be sourced/versioned. A **planning preference** is an officer choice and cannot be promoted into KingdomMaps as a game rule.

The current validator therefore distinguishes:

- **violations** for dataset-backed legality failures such as map bounds, collisions, exclusion zones and object caps;
- **warnings** for legal but undesirable planning state such as preferred Bear radius or disconnected planned territory;
- **suggestions** for optional planning improvements such as adding an HQ, Banner coverage or Bear Trap.

Laravel remains save authority even when the browser preview has already evaluated the same geometry.