# KingdomMaps — Implementation Source / Confidence Matrix

Status: Implemented in evidence-backed schema V2; CI verification pending

This matrix is the implementation record produced from the KingdomMaps research pass. Confidence applies to the specific fact or fact family, not to an entire source or release.

| Fact key / family | Value / geometry / rule | Applicability | Primary evidence | Corroboration / lineage | Confidence | KingdomMaps representation |
| --- | --- | --- | --- | --- | --- | --- |
| `map.coordinate_system` | KingShot X/Y, southwest-origin, integer tile grid | Current researched map generation | ksmapper | Bleezy-derived planners share ksmapper lineage and do not count independently | `community_observed` | `coordinate_system` |
| `map.bounds` | `0,0` + `1200×1200` | Current researched map generation | ksmapper | multiple community planners describe 1200×1200 but lineage may overlap | `community_observed` | `bounds` |
| `alliance.banner.max` | 285 per Alliance | Current official Alliance rule | Century Games Help Center | community planners agree | `official` | `object_types.banner.facts.max_per_alliance` + `banner_max_285` rule |
| `alliance.resource.minimum_territory_ratio` | `0.75` | Alliance resource mine production | Century Games Help Center | community territory descriptions | `official` | `alliance_resource_territory_ratio.parameters.minimum_covered_ratio` |
| `alliance.banner.hq_connectivity` | disconnected Banner becomes invalid/non-producing | Current official Alliance rule | Century Games Help Center | community territory references agree | `official` | blocking `banner_hq_connectivity` rule |
| `alliance.hq.allowed.badlands` | true | Current official HQ placement | Century Games Help Center | community planners agree | `official` | HQ variant + rule fact |
| `alliance.hq.allowed.plains` | true after documented progression boundary | Current official HQ placement | Century Games Help Center | community planners agree | `official` | `plains_headquarters` variant |
| `alliance.hq.allowed.fertile` | false | Current official HQ placement | Century Games Help Center | community planners agree | `official` | `headquarters_legal_zones` + Fertile fact |
| `alliance.plains.access` | follows `Banner Raised High` | Current official Alliance progression | Century Games Help Center | none required | `official` | fact rule |
| `alliance.fertile.access` | follows `Grand Conquest` | Current official Alliance progression | Century Games Help Center | none required | `official` | fact rule |
| `alliance.banner.zone_technology` | Plains/Fertile Banner placement depends on Alliance Growth technology including Plains Enrichment/Cultivation Drive | Current official Alliance progression | Century Games Help Center | none required | `official` | fact rule; no invented finer mapping |
| `object.headquarters.footprint` | `3×3` | researched map generation | ksmapper | planner lineage | `community_observed` | rectangular footprint |
| `object.headquarters.coverage` | `15×15` territory rectangle | researched map generation | ksmapper | planner lineage | `community_observed` | rectangular coverage |
| `object.banner.footprint` | `1×1` | researched map generation | ksmapper | community planner references | `community_observed` | rectangular footprint |
| `object.banner.coverage` | `7×7` territory rectangle | researched map generation | ksmapper | community planner references | `community_observed` | rectangular coverage |
| `object.governor_city.footprint` | `2×2` | researched map generation | ksmapper | community planner references | `community_observed` | rectangular footprint |
| `object.bear_trap.footprint` | `3×3` | researched map generation | ksmapper | community planner references | `community_observed` | rectangular footprint |
| `zone.badlands.geometry` | released V2 rectangle | researched map generation | ksmapper | no independent authoritative geometry | `community_observed` | `zones.badlands` |
| `zone.plains.geometry` | released V2 rectangle | researched map generation | ksmapper | no independent authoritative geometry | `community_observed` | `zones.plains` |
| `zone.fertile.geometry` | released V2 rectangle | researched map generation | ksmapper | official source verifies HQ prohibition, not rectangle coordinates | `community_observed` geometry + `official` HQ restriction | zone + property-level fact |
| `zone.ruins.geometry` | released V2 rectangle | researched map generation | ksmapper | community map references | `community_observed` | zone |
| `zone.central_forbidden.geometry` | released V2 rectangle | researched map generation | ksmapper | community map references | `community_observed` | zone |
| `structure.kings_castle` | coordinate/footprint/exclusion in V2 | researched map generation | ksmapper | community planners | `community_observed` | blocking structure |
| `structure.turrets` | four central Turrets | researched map generation | ksmapper | community planners | `community_observed` | blocking structures |
| `structure.fortress.coordinates` | four Fortresses | researched map generation | ksmapper | Kingshot Wiki ruins reference | `community_observed` in release | blocking structures + facility artifact |
| `structure.sanctuary.coordinates` | twelve Sanctuaries | researched map generation | ksmapper | Kingshot Wiki ruins coordinates independently agree | `community_observed` release ceiling; eligible for later confidence-policy promotion only through explicit review | blocking structures + facility artifact |
| `facility.outpost.coordinates` | 74 Outposts with levels | researched map generation | ksmapper | Kingshot Wiki Outposts reference | `community_observed` | immutable facility artifact |
| `resource_nodes.corpus` | 6,499 fixed resource-node facts | researched ksmapper corpus | ksmapper | downstream planners are not independent | `community_observed` | `resource_layers.resource_nodes`; user-authorized source-corpus reference |
| `terrain.lakes.corpus` | 501 lake features | researched ksmapper corpus | ksmapper | downstream planners are not independent | `community_observed` | `resource_layers.terrain` |
| `terrain.mountains.corpus` | 1,948 mountain features | researched ksmapper corpus | ksmapper | downstream planners are not independent | `community_observed` | `resource_layers.terrain` |
| `planner.object_collision` | planned footprints cannot overlap | application deterministic geometry | Kingshot Alliance | PHP/browser parity fixture | `verified_observation` | blocking application rule |
| `planner.map_bounds` | released footprints remain within bounds | application deterministic geometry over released bounds | Kingshot Alliance | PHP/browser parity fixture | `verified_observation` | blocking application rule |

## Rights record

The repository owner/user explicitly confirmed on 2026-09-06 that they possess the rights needed to use the ksmapper data in Kingshot Alliance. The source registry records this as `user_authorized_reuse_2026-09-06`. Rights authorization permits reuse; it does not promote ksmapper facts above `community_observed` confidence.

## Conflict policy

- Official Century Games evidence wins only for the exact rule/property it states.
- Independent observations may be promoted only through an explicit confidence policy and review; agreement is not silently converted to `official`.
- Sources sharing ksmapper lineage do not count as independent corroboration.
- Conflicting evidence is represented as `disputed` rather than silently selecting one value.
- Unknown applicability/version boundaries remain null/unknown rather than inferred.

## Data-state policy

The V2 release distinguishes inline released facts from an authorized source-corpus reference. Corpus counts and source authorization are released now; future expansion of all individual resource/terrain records must preserve stable keys, source provenance, confidence, immutable release checksums and semantic diffability.
