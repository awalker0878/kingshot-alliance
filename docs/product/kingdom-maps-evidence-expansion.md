# KingdomMaps Evidence-backed Map Knowledge Expansion

Status: Selected extension — implementation in progress

## Purpose

KingdomMaps is the GameWorld-owned source of immutable, versioned KingShot map facts used by TerritoryPlanning and Intelligence reconciliation. This expansion turns the capability from a checked-in JSON profile plus placement validator into a versioned, evidence-aware map knowledge capability with explicit source lineage, fact confidence, release validation, researched facility coverage, and operational verification.

## Fresh-deployment rule

This is a fresh deployment. Schema V2 replaces schema V1 directly. Do not add compatibility readers, legacy shims, dual-read paths, migration adapters, or fallback-to-V1 behavior.

## Data-rights decision

The repository owner/user has explicitly confirmed on 2026-09-06 that they hold the rights needed to use the ksmapper map data in this project. KingdomMaps may therefore store and use ksmapper-derived map facts, including resource-node and terrain facts, subject to normal provenance and confidence rules.

This authorization affects rights to reuse the data; it does **not** change evidentiary confidence. ksmapper-derived facts remain `community_observed` unless independently verified. They must never be promoted to `official` merely because reuse is authorized.

## Source policy

KingdomMaps distinguishes source authority from reuse rights.

- **Century Games Kingshot Help Center**: official source for game rules such as the 285 Banner limit, the 75% Alliance-resource territory requirement, Banner/HQ connectivity, Plains/Fertile progression gates, and HQ placement restrictions.
- **Kingshot Wiki (`kingshotwiki.com`)**: structured community reference used for facility coordinates and facility metadata. Coordinates may be promoted only to the confidence justified by corroboration recorded in the release.
- **ksmapper**: user-authorized community-observed spatial source for map geometry, fixed structures, resource nodes, terrain masks, and map-placement observations.
- **Planner geometry**: deterministic application-owned geometry rules such as object/object collision. These are not game-source claims.

Two sources that reproduce the same upstream ksmapper dataset do not count as independent corroboration.

## Schema V2 release contract

Every released dataset is immutable and exposes at least:

- `id`, `schema_version=2`, `release_status`, `released_at`, `observed_at`;
- `game_version`, `season`, and applicability metadata when known;
- `predecessor_id` and a human-readable change summary;
- coordinate-system and map-bounds metadata;
- source registry with source type, URI, confidence ceiling, rights basis, and lineage;
- property/fact provenance references;
- typed object definitions with rectangular `footprint` and `coverage` geometry;
- map zones and placement restrictions;
- fixed structures/facilities;
- resource-node and terrain-layer metadata/facts when available;
- sourced game placement rules;
- release checksum computed from the immutable file bytes.

## Researched facts incorporated

The V2 release incorporates the following researched facts without overstating confidence:

- Alliance Banner limit: **285** per Alliance — official Century Games rule.
- Alliance resource production requires **at least 75%** of the resource mine to be within Alliance territory — official Century Games rule.
- Alliance Banners become invalid when disconnected from the Alliance HQ — official Century Games rule.
- HQ may be built in **Badland** and **Plains**, but not **Fertile Land** — official Century Games rule.
- Plains access follows the **Banner Raised High** milestone; Fertile Land access follows **Grand Conquest** — official Century Games rule.
- Banner placement on Plains/Fertile Land depends on Alliance Growth technology including **Plains Enrichment** and **Cultivation Drive** — official Century Games rule.
- Fixed facility catalogue: 4 Fortresses, 12 Sanctuaries, and 74 Outposts with researched coordinates.
- ksmapper reports **6,499 canonical resource nodes**, **501 lake cells/features**, and **1,948 mountain cells/features** in its observed spatial corpus. Those facts are usable because reuse rights have been confirmed, but retain community-observed confidence unless separately verified.
- ksmapper also describes map-planning limits of 2 HQs, 2 Bear Traps, and 100 Governor cities; where Century Games evidence is absent these remain community-observed, not official.

## Object identity

V2 separates the two Alliance HQ facts:

- `badland_headquarters`
- `plains_headquarters`

TerritoryPlanning may present a single user-facing HQ tool, but the factual map model must preserve the variant because legal placement and progression requirements differ.

## Evidence rules

Confidence values are:

- `official`
- `verified_observation`
- `community_observed`
- `disputed`
- `unknown`

A release may contain facts at multiple confidence levels. Release-level confidence is a summary only and must not overwrite property-level provenance.

Each fact/properties group must reference one or more source IDs. Promotion to `verified_observation` requires independent corroboration or an explicit reviewer decision with evidence. `official` requires a Century Games-controlled source or another explicitly designated official source.

## Release lifecycle

The supported lifecycle is:

1. ingest or author candidate facts;
2. validate schema and source references;
3. compare semantically with the current release;
4. review additions/removals/changed facts and confidence;
5. verify source-lineage and reuse-rights declarations;
6. publish a new immutable release;
7. retain predecessor/checksum identity so published TerritoryPlans remain pinned to the exact release they used.

A released file is never edited in place after publication; corrections create a successor release.

## Geometry policy

- Rectangular footprints are first-class (`width`, `height`).
- Coverage geometry is explicit and separate from footprint geometry.
- Polygon/circle/mask geometry is introduced only when sourced facts require it.
- Territory ownership uses the official 75% requirement instead of hard-coded corner heuristics.
- Placement validation remains deterministic in both PHP and browser geometry implementations.

## Consumer boundaries

KingdomMaps owns map truth and validation facts. It does not own:

- saved Alliance/Kingdom plans;
- plan-local external Alliances/Governors;
- Bear-hive optimization preferences;
- event assignments/objectives;
- unsourced march-speed assumptions.

Those remain Operations/TerritoryPlanning concerns. Intelligence owns observations/evidence about live Kingdom state but consumes the pinned KingdomMaps release to interpret spatial evidence.

## Completion criteria

The extension is complete only when:

- V1 is removed and V2 is the only runtime dataset schema;
- nested V2 schema validation is enforced;
- source/provenance/rights validation is enforced;
- researched facility facts and official rules are represented with truthful confidence;
- ksmapper resource/terrain facts are represented as authorized community-observed data;
- semantic release diffing exists;
- 75% territory coverage is implemented;
- production dataset loading is directly tested;
- PHP/browser geometry parity remains green;
- KingdomMaps validation is part of CI;
- documentation and delivery ledger are reconciled to current truth.
