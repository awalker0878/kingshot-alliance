# Kingdom Map workspace data and artwork catalogue

This catalogue records factual coverage and presentation requirements. Completion evidence belongs in the [delivery ledger](kingdom-map-workspace-delivery-ledger.md). Artwork dimensions, versions and authorization never establish collision geometry or game mechanics.

## Materialized baseline

The immutable release `kingshot-evidence-backed-2026-09-06-v2` has checksum `39c679d5897bbb29939e00b7903a0e7f5dd44d1b95f10dabef29d5762b5405ec`. Its facility artifact `kingshot-facilities-2026-09-06.json` has checksum `96c57142e688145f51e657aad41f2abc9afd934a9ff15f48193b9e3fc0ad89d7`.

Released successor `kingshot-spatial-complete-v2-r1` (`released_at` `2026-09-13T03:13:39Z`, file checksum `de5377e89640f50b2a82c420a47b1f1f9b090f1ced8d2d8a542801387462c7a6`) carries the terrain and resource geometry as checksummed artifacts rather than inline arrays:

| Artifact | Records | Checksum | Provenance |
| --- | --- | --- | --- |
| `kingshot-facilities-2026-09-06.json` | 90 facility records | `96c57142e688145f51e657aad41f2abc9afd934a9ff15f48193b9e3fc0ad89d7` | `ksmapper`, `kingshot_wiki_outposts`, `kingshot_wiki_ruins` |
| `kingshot-terrain-2026-09-13.json` | 501 lakes and 1,948 mountains (`completeness: complete_captured_source`) | `877cdfb1901251783914d6ae4a1610e59f2adf3510107eaace3958e57dcfcfc1` | `ksmapper_terrain_2026_09_13` |
| `kingshot-resources-2026-09-13.json` | 6,499 resource nodes (`completeness: complete_captured_source`) | `279977c38ffbbf7ff0fa6530098d512c0a49869a45f3b043718eb421bf9156cd` | `ksmapper_resources_2026_09_13`, `ksmapper_terrain_2026_09_13` |

| Family | Materialized factual records | Presentation requirement |
| --- | --- | --- |
| Castle and Turrets | 1 Castle and 4 Turrets in fixed structures | Distinct Castle and Turret icon/sprite/detail. |
| Fortresses and Sanctuaries | 4 Fortresses and 12 Sanctuaries; facility catalogue overlaps fixed structures | Distinct families; deduplicate source identity in scene. |
| Outposts | 74 facility coordinates; footprint absent where not sourced | Family/level art for Builder's Guild (1,3), Armory (2,4), Scholar's Tower (1,3), Arsenal (2,4), Forager Grove (1), Harvest Altar (1), Drill Camp (2), Frontier Lodge (3). Markers must not invent footprints. |
| Planned HQ, Banner, city and Bear | User-authored layout objects, not immutable observations | Badland/Plains HQ variants, Banner, Governor city and Bear Trap icon/sprite/detail. |
| Resources | 6,499 materialized nodes in `kingshot-resources-2026-09-13.json`; three recorded overlap diagnostics remain reported, never silently repaired | Appropriate resource family art; node ownership stays `unqualified_resource_node` until sourced. |
| Lakes and mountains | 501 lakes and 1,948 mountains materialized in `kingshot-terrain-2026-09-13.json` | Appropriate terrain family art; counts and geometry are sourced, so terrain can be drawn and validated against. |
| Regions/exclusions/territory/coverage | Sourced bounds and derived geometry | Deliberate overlays/legends; no fabricated art or mechanics. |
| Observations/annotations/comparison | Authorized private evidence and plan-owned information | Distinct status overlays and semantic descriptions; never public-cache private content. |

The inspected baseline contains application-owned room illustrations and app icons, but no complete authentic Kingdom Map artwork master pack. Missing art remains an open acceptance gate. Runtime fallbacks communicate missing material and cannot be counted as final imagery.

## Registry and preparation contract

One versioned registry owns stable keys, variants, source metadata, icon/sprite/detail representations, byte hashes, image dimensions, anchors, orientation and review state. It ships as `resources/data/kingdom-map-art/manifest.v1.json` (registry version `2026.09.14.1`): 27 entries — 24 raster families awaiting source bytes and 3 application-owned vector overlays. Every required entry remains present even if source files are unavailable. Source-required entries have no invented hashes or unrelated replacement art.

`npm run check:kingdom-map-art:strict` is the separate completeness check. It fails with `BLOCKED_INPUT` while any raster entry remains `awaiting_source`, naming every absent representation. `npm run check:kingdom-map-art` (`--structure-only`) validates keys, schema, limits, content-addressed paths and anchors without asserting delivered bytes, and is part of `npm run check`.

Preparation is `scripts/kingdom-map-art-import.mjs`. It accepts only explicitly selected local source files, validates MIME from magic bytes, enforces byte/dimension limits, trims transparent borders, produces bounded resolution variants, never upscales, and writes content-addressed delivery paths. SVG input must be rasterized by an approved rasterizer first and is rejected here. The emitted record contains bytes and geometry only; it never asserts rights or review state. Runtime loading is lazy and bounded; export embedding verifies asset identity and cleans up decoded resources.

### Master pack contract

`scripts/kingdom-map-art-pack.mjs` ingests a whole rights-cleared master pack in one atomic operation instead of preparing one representation at a time. A pack is a directory holding a `pack.json` declaration plus the source images it names:

```json
{
  "pack_schema_version": 1,
  "pack_id": "kingshot-kingdom-map-artwork-2026-09",
  "supplied_at": "2026-09-14",
  "supplied_by": "<supplying party>",
  "source": "<optional description of the delivery channel>",
  "entries": {
    "headquarters.badland": {
      "icon": "hq-badland-icon.png",
      "sprite": "hq-badland-sprite.png",
      "detail": "hq-badland-detail.png"
    }
  }
}
```

Every `entries` key must be an existing registry key and every representation kind must be one the registry declares for that key; unknown keys, unknown kinds, absolute paths and `..` escapes are rejected before any bytes are read. Each named file is prepared through the same validation used by the single-file importer (magic-byte MIME detection, byte and dimension limits, transparent-border trim, no upscaling) and written to its content-addressed delivery path.

```bash
npm run art:pack -- <pack-dir>            # validate and apply, writing bytes + registry records
npm run art:pack -- <pack-dir> --dry-run  # report the plan, write nothing
npm run art:approve -- <reviewer>         # human review: awaiting -> delivered -> reviewed
```

Ingestion is atomic: the whole pack is planned in memory, the resulting registry is validated, and only then are bytes and manifest written, so a rejected pack leaves the registry and delivery tree untouched. Re-ingesting an identical pack is idempotent. Ingestion can never set `review_state: reviewed`; it records `delivered_unreviewed` and only the separately named `npm run art:approve -- <reviewer>` transition sets `reviewed` and appends `reviewed by <name> at <date>` to provenance. Rights and review are therefore never asserted by tooling.

The strict gate verifies delivery bytes, not just declarations: for every declared representation it checks that the content-addressed file exists, does not escape the delivery root, and matches the recorded `sha256` and `byte_size`. That verification is what makes embedded artwork safe, because an export can only embed bytes whose hash it has confirmed. When representations are absent the gate reports `BLOCKED_INPUT`; when they are present but not yet human-reviewed it reports `REVIEW_PENDING`.

The user's Kingshot authorization is recorded as supplied and was reconfirmed on 2026-09-14 for the Kingshot Kingdom Map artwork master pack; unrelated third-party datasets require their own rights basis. Clearance is a rights statement, not a delivery: no artwork bytes are present in the repository, so every source-required entry remains `awaiting_source` until the pack is supplied and ingested. Source research may identify URLs and factual metadata without establishing that an image is cleared or delivered. The required unresolved input is the master pack itself; it cannot be replaced with invented records.
