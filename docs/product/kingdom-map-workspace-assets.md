# Kingdom Map workspace data and artwork catalogue

This catalogue records factual coverage and presentation requirements. Completion evidence belongs in the [delivery ledger](kingdom-map-workspace-delivery-ledger.md). Artwork dimensions, versions and authorization never establish collision geometry or game mechanics.

## Materialized baseline

The immutable release `kingshot-evidence-backed-2026-09-06-v2` has checksum `39c679d5897bbb29939e00b7903a0e7f5dd44d1b95f10dabef29d5762b5405ec`. Its facility artifact `kingshot-facilities-2026-09-06.json` has checksum `96c57142e688145f51e657aad41f2abc9afd934a9ff15f48193b9e3fc0ad89d7`.

| Family | Materialized factual records | Presentation requirement |
| --- | --- | --- |
| Castle and Turrets | 1 Castle and 4 Turrets in fixed structures | Distinct Castle and Turret icon/sprite/detail. |
| Fortresses and Sanctuaries | 4 Fortresses and 12 Sanctuaries; facility catalogue overlaps fixed structures | Distinct families; deduplicate source identity in scene. |
| Outposts | 74 facility coordinates; footprint absent where not sourced | Family/level art for Builder's Guild (1,3), Armory (2,4), Scholar's Tower (1,3), Arsenal (2,4), Forager Grove (1), Harvest Altar (1), Drill Camp (2), Frontier Lodge (3). Markers must not invent footprints. |
| Planned HQ, Banner, city and Bear | User-authored layout objects, not immutable observations | Badland/Plains HQ variants, Banner, Governor city and Bear Trap icon/sprite/detail. |
| Resources | Corpus reference claims 6,499; actual coordinates absent at inspected baseline | Appropriate resource family art and materialized records required for complete coverage. |
| Lakes and mountains | Corpus references claim 501 lakes and 1,948 mountains; actual geometry absent at inspected baseline | Geometry and appropriate visual family required; counts alone cannot draw terrain or validate against it. |
| Regions/exclusions/territory/coverage | Sourced bounds and derived geometry | Deliberate overlays/legends; no fabricated art or mechanics. |
| Observations/annotations/comparison | Authorized private evidence and plan-owned information | Distinct status overlays and semantic descriptions; never public-cache private content. |

The inspected baseline contains application-owned room illustrations and app icons, but no complete authentic Kingdom Map artwork master pack. Missing art remains an open acceptance gate. Runtime fallbacks communicate missing material and cannot be counted as final imagery.

## Registry and preparation contract

One versioned registry owns stable keys, variants, source metadata, icon/sprite/detail representations, byte hashes, image dimensions, anchors, orientation and review state. Every required entry remains present even if source files are unavailable. Source-required entries have no invented hashes or unrelated replacement art. A separate strict completeness check must fail on those entries.

Preparation accepts only explicitly selected local source files, validates dimensions/byte limits and source metadata, strips unsafe input metadata, trims transparent borders, generates bounded resolution variants and compresses delivery representations. SVG input requires strict validation or rejection; scripts, event handlers, external references and active content must never reach the browser. Runtime loading is lazy and bounded; export embedding verifies asset identity and cleans up decoded resources.

The user's Kingshot authorization is recorded as supplied; unrelated third-party datasets require their own rights basis. Source research may identify URLs and factual metadata without establishing that an image is cleared or delivered. Required unresolved inputs are a rights-cleared artwork master pack and an obtainable, authorized spatial corpus with actual coordinates/geometry. Neither can be replaced with invented records.
