# Kingdom Map spatial evidence and geometry

## Immutable facts, not a renderer approximation

The V2 successor `kingshot-spatial-complete-v2-r1` materializes the complete **captured ksmapper Kingshot source**: 501 lakes, 1,948 mountains and 6,499 resource records. This is a community-observed snapshot, not a claim that every Kingdom or later game version has identical terrain, nor an elevation to official confidence.

The predecessor `kingshot-evidence-backed-2026-09-06-v2` is unchanged, with SHA-256 `39c679d5897bbb29939e00b7903a0e7f5dd44d1b95f10dabef29d5762b5405ec`. Its resource/terrain corpus references remain explicitly unavailable for rendering; they are not silently hydrated from a newer release. Published plans continue to pin their exact release and checksum. Both releases use the sole supported map schema V2.

## Source identities and rights

The existing project record authorizes reuse of ksmapper factual data as of 2026-09-06. This delivery does not acquire rights to unrelated code or datasets and does not infer artwork rights or official mechanics from source access.

| Input                                                                | Exact captured identity                                                    | Meaning                                                                                                                                                                                                          |
| -------------------------------------------------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `https://ksmapper.pages.dev/core.js`                                 | SHA-256 `ffc0270f3ff00dc11b2b04ccc171e996b4086c2183f12a96b35b2ad23dc5e566` | Inspected source literals only: Kingshot 2-bit terrain bitmap, component bounds/centroids/counts and resource footprint definitions. Upstream application code is never executed or copied into the application. |
| `https://ksmapper.jim-harvey-yu.workers.dev/resources?game=kingshot` | SHA-256 `f74dec9784d0afca00f4cc7a4d32ac397a07f38b7ec531843b8acf6629cbe04c` | The source-declared public read endpoint, captured at 2026-09-13T02:58:26Z, identifies exactly 6,499 Kingshot nodes. No write/report endpoint is used.                                                           |

Both references retain the same `primary_ksmapper` lineage. They are not independent corroboration. The release carries the rights basis, source hash, capture time, provenance and confidence ceiling.

## Reproducible import and artifact pins

Run `node scripts/import-kingdom-map-spatial-source.mjs <reviewed-core.js> <reviewed-resources.json> <empty-output-directory>` against the exact captured inputs. The importer checks their hashes before parsing, reads only anchored literal constants, uses bounded zlib decompression, verifies the Kingshot-specific contract and creates outputs exclusively. Changed source bytes require a new reviewed import/release; there is no raw-deflate, other-game, old-schema or repair fallback.

| Artifact                                       | SHA-256                                                            | Captured records                                                                                          |
| ---------------------------------------------- | ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------- |
| `artifacts/kingshot-terrain-2026-09-13.json`   | `877cdfb1901251783914d6ae4a1610e59f2adf3510107eaace3958e57dcfcfc1` | 501 lakes / 23,246 lake cells; 1,948 mountains / 67,215 mountain cells.                                   |
| `artifacts/kingshot-resources-2026-09-13.json` | `279977c38ffbbf7ff0fa6530098d512c0a49869a45f3b043718eb421bf9156cd` | 2,169 bread, 2,171 woodmill, 1,470 quarry and 689 ironmine records; each has the sourced 2 × 2 footprint. |

The bitmap decodes to exactly 360,000 bytes (1,200 × 1,200 cells at two bits each), with decoded SHA-256 `bd7c0c5385d28112bcaec1943c3eaf416732c2484d4b938322ae853163b611a84`. Four-neighbor components match all source feature counts, half-open bounds, cell counts and rounded centroids. Each feature stores exact horizontal unit-height spans. Its bounding box is a selection/culling envelope, never substitute blocking geometry. Decorative artwork and movement topology are separate concerns.

The artifact runtime loader verifies owned paths, bounded sizes, SHA-256, schema, capture metadata, source lineage and confidence before hydration. Its spatial validator checks exact record contracts, span order/merging/disjointness, cell totals, tight envelopes, centroids and resource identities. Missing, corrupt and merely unmaterialized inputs remain different states.

## Disclosed source discrepancies

All records are retained. These source conflicts are recomputed during hydration and must exactly match the artifact diagnostics:

| Diagnostic                 | Resource references      | Terrain reference |
| -------------------------- | ------------------------ | ----------------- |
| Resource / terrain overlap | `r_256_130`              | `lake_0057`       |
| Resource / terrain overlap | `r_768_1140`             | `mountain_1853`   |
| Resource footprint overlap | `r_639_342`, `r_640_342` | None              |

No record is moved, removed or assigned fabricated precision to conceal these conflicts. Resource records carry `unqualified_resource_node` ownership semantics: geometric coverage is useful, but must not be labeled verified Alliance production or ownership. The official Alliance-resource threshold must not be generalized to these unqualified records or Governor cities.

## Canonical runtime projections

`KingdomMapDatasetQuery` uses the existing artifact loader and exposes `terrain_features`, `resource_nodes`, `spatial_diagnostics` and `layer_availability`. Each layer includes materialized/unavailable state, available/expected count, covered extent, artifact hash, confidence, capture time, unavailable reason and exact release ID/checksum. There is no new parallel factual query owner.

`KingdomMapSpatialPlacementIndex` and the browser `engine/spatial.ts` build bounded sorted per-row interval unions once per immutable map object. Queries test the rotated logical footprint against exact terrain cells and resource footprints. Touching edges do not collide. Empty corners in a terrain envelope stay empty. PHP uses a weak-key cache and TypeScript a `WeakMap`; map instances cannot contaminate one another, and unused releases need not remain resident forever.

`PlacementValidator` remains save authority. Browser validation mirrors it against the same golden fixture. New blocking issue codes are `terrain_collision` and `resource_collision`. Visibility controls do not disable factual placement constraints; only released `placement_blocking` declarations control those semantics. Claimed materialization without hydrated geometry is an error, not an empty layer.

## Executed evidence and remaining gates

The containing commit and final receipts are recorded in the PR, avoiding a self-referential source SHA here. Local Node 24.20 / PHP 8.5.10 evidence for this slice includes:

- Nine frontend source/spatial behavior tests, including exact shapes, malformed inputs, bounds, rotation, visibility, cache separation and 300 seeded shared queries.
- Forty-four focused PHP tests and 592 assertions across KingdomMaps and TerritoryPlanning unit behavior; shared pre-existing 12-case geometry/analysis parity also passes.
- PHPStan for the changed owner and new tests, focused ESLint, Vue type checking and Pint pass; both map releases pass schema and source verification.
- A complete `migrate:fresh --force` succeeded on the isolated local PostgreSQL 18.6 test database, including the new workspace-view and collaboration tables. No persistent deployment database was reset.
- Hosted source preparation reproduced both artifact byte hashes exactly in run `34735312865`; its object-only receipt confirms no branch ref write and no upstream-code execution.

These checks do not certify artwork coverage, the shared scene renderer, real-browser user journeys, performance targets or final release readiness. The wider 105-test owner/read-model run found nine collaboration-fixture setup errors before geometry validation; they remain tracked with the collaboration owner rather than weakening the owning-Alliance rule. Keep the delivery PR draft until all applicable gates pass on its final immutable candidate.
