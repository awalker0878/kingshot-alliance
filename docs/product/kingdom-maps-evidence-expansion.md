# KingdomMaps Evidence Expansion

Status: Implemented on `kingdommaps-research-evidence-expansion`; CI/PR verification pending

This program turns `GameWorld/KingdomMaps` from a trusted community JSON profile plus placement validator into a versioned, evidence-backed KingShot map-knowledge capability. The application is a fresh deployment: schema V2 replaces V1 outright. No compatibility aliases, dual reads, migration shims or fallback loaders are permitted.

## Goals

- make fact-level provenance/confidence a runtime invariant;
- encode official Century Games Alliance territory rules as official only for the exact facts they support;
- preserve community-observed geometry truthfully rather than presenting it as official;
- record and use the repository owner's 2026-09-06 authorization to reuse ksmapper data;
- expand fixed/facility knowledge to the researched 4 Fortress + 12 Sanctuary + 74 Outpost catalogue;
- represent authorized resource/terrain corpus facts (6,499 resource nodes, 501 lakes, 1,948 mountains);
- replace scalar factual geometry with explicit rectangular footprint/coverage contracts;
- implement official 75% Alliance-resource ownership geometry and Banner-HQ connectivity semantics;
- provide immutable release diffing, source/rights verification, operational commands and CI assurance.

## Evidence policy

Confidence is attached to a fact/property, not granted wholesale by the release or source.

Supported states:

```text
official
verified_observation
community_observed
disputed
unknown
```

Rules:

1. Only Century Games-published evidence may establish `official` facts.
2. Independent observations may establish `verified_observation` only through explicit review/policy; agreement never silently becomes official.
3. A single community source remains `community_observed`.
4. Shared lineage does not count as independent corroboration. Bleezy/planner data derived from ksmapper remains ksmapper lineage.
5. Conflicts are preserved as `disputed`; missing evidence remains `unknown`.
6. Reuse rights and factual confidence are independent concepts.

## ksmapper rights decision

On 2026-09-06 the repository owner/user explicitly confirmed that they possess the rights needed to use ksmapper data in Kingshot Alliance.

The V2 source registry records:

```text
source: ksmapper
rights_basis: user_authorized_reuse_2026-09-06
confidence_ceiling: community_observed
lineage: primary_ksmapper
```

This removes the prior reuse-rights blocker. It does not promote ksmapper facts to official or independently verified evidence.

## V2 release contract

The sole runtime release is currently:

```text
kingshot-evidence-backed-2026-09-06-v2
```

A release contains:

- immutable identity/status/release/observation metadata;
- game/season applicability where known;
- predecessor lineage where applicable;
- source registry with type, URI, confidence ceiling, rights basis and lineage;
- coordinate system and map bounds;
- typed rectangular object footprint/coverage definitions;
- HQ variants and fact-level provenance;
- zones with property-level facts where evidence differs;
- blocking/reference structures;
- placement/territory rules;
- resource/terrain layer state;
- immutable artifact manifests and SHA-256 values.

Released files are immutable. Territory plans and observations retain exact release ID/checksum pins.

## Research-backed official rule set

The V2 release represents official Century Games evidence for at least:

- maximum 285 Alliance Banners;
- minimum 75% Alliance-resource-mine inclusion within Alliance territory for production;
- Banner invalidity/non-production when disconnected from an Alliance HQ;
- Alliance HQ allowed on Badland;
- Alliance HQ allowed on Plains under the documented progression boundary;
- Alliance HQ prohibited on Fertile Land;
- Plains access following `Banner Raised High`;
- Fertile Land access following `Grand Conquest`;
- later-zone Banner placement depending on Alliance Growth technology including `Plains Enrichment` and `Cultivation Drive`.

Where the official source does not state a finer relationship, the schema does not invent one.

## Researched spatial/facility set

The V2 release and immutable facility artifact cover:

- King's Castle;
- four central Turrets;
- four Fortresses;
- twelve Sanctuaries;
- seventy-four Outposts with researched coordinates/levels;
- 1,200 × 1,200 map bounds;
- researched Badland, Plains, Fertile, Ruins and central forbidden regions;
- ksmapper corpus facts for 6,499 resource nodes, 501 lake features and 1,948 mountain features.

Blocking footprints/exclusions and corpus geometry retain community-observed confidence unless the exact property is supported by official evidence.

## Geometry contract

V2 removes scalar factual `size` and scalar coverage contracts. Object and structure geometry uses:

```text
footprint:
  width
  height

coverage:
  width
  height
```

`TerritoryCoverageGeometry` owns server-side rectangular footprint/coverage, covered-area ratio and territory-component calculations.

The official 75% rule is read from the released `alliance_resource_territory_ratio` rule and applied specifically to Alliance resource ownership. Governor-city coverage remains a TerritoryPlanning analytical semantic rather than being incorrectly promoted to the official 75% rule.

Banner connectivity is blocking only when a Banner-containing connected territory component has no HQ. A separate component anchored by another HQ is not incorrectly rejected.

## Consumers

The V2 geometry contract is consumed by:

- `PlacementValidator`;
- `Operations/TerritoryPlanning/TerritoryCoverageAnalyzer`;
- `Operations/TerritoryPlanning/TerritoryLayoutAnalyzer`;
- browser Territory Planner geometry;
- Intelligence spatial-observation bounds validation;
- Territory Reconciliation absence/bounds reasoning.

Browser/server behavior is pinned by the shared V2 golden fixture.

## Operational tooling

KingdomMaps registers:

```text
php artisan kingdom-maps:list
php artisan kingdom-maps:validate
php artisan kingdom-maps:verify-sources [release]
php artisan kingdom-maps:diff <from> <to> [--json]
```

Validation covers schema, artifact identity/checksum, provenance, source confidence ceilings, rights basis and lineage.

## CI

`.github/workflows/kingdom-maps-assurance.yml` runs for relevant PR/main changes and verifies:

- runtime release validation;
- source rights/lineage verification;
- focused KingdomMaps PHP tests;
- production release facts/artifacts;
- PHP/browser geometry parity.

Full repository CI remains authoritative for broader PHP, frontend, architecture and container quality.

## Implementation source matrix

The direct implementation mapping of researched fact families, evidence, confidence and schema representation is maintained in [KingdomMaps source/confidence matrix](kingdom-maps-source-confidence-matrix.md).

## Closeout

The code/data/document implementation is complete on the feature branch. The delivery ledger remains open only for PR check verification, actionable review-thread resolution and merge to `main`.
