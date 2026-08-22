# GameWorld — KingdomMaps

Status: Active delivery — Architecture V3

Implementation target: `app/Contexts/GameWorld/KingdomMaps`

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
- canonical geometry value objects/contracts required to interpret map facts.

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
  terrain/reference layers
  resource-node references when evidence-gated
  sourced placement rules
```

A newer dataset supersedes rather than mutates an older release. Published territory-plan revisions retain the exact dataset ID/checksum that was used when they were published.

## Evidence boundary

Community planners are discovery evidence. Their coordinates, node sets, footprint sizes or rules do not become official product truth merely because they are useful. Community-observed values may be represented only with truthful provenance/confidence and a version/observation boundary. Unknown stays unknown.

## Geometry contract

Canonical concepts are immutable values such as `Coordinate`, `BoundingBox`, `Rectangle`, `Circle`, `Polygon`, `Footprint`, `Rotation` and `Distance`. Server validation is authoritative. Browser geometry may mirror the same rules for immediate preview only and must be contract-tested against shared golden fixtures.

## Rule taxonomy

A **map fact** describes what exists. A **game placement rule** determines whether a placement is legal and must be sourced/versioned. A planning preference is not a game rule and therefore cannot live in KingdomMaps.
