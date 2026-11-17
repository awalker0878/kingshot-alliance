# Decorative room artwork

The October 26, 2026 artwork batch implements generation briefs SCN-14 through SCN-17. These are original AI-generated companion-application environments, not supplied game artwork or evidence of game structures, rewards, recipes or mechanics.

| Scene | Pages | Desktop bytes | Mobile bytes |
| --- | --- | ---: | ---: |
| Territory command | Territory Index, Explorer, Editor and Tools | 79,966 | 89,072 |
| Gift-code vault | Gift Codes Index and Workspace | 51,976 | 74,820 |
| Progression workshop | Progression Index, Library, Governor, Planner and NoDataset | 62,370 | 107,882 |
| Assistant archive | Assistant Index | 54,372 | 68,652 |

## Source and delivery

The [manifest](../../resources/data/room-art/manifest.v1.json) retains the exact prompts, generator description, master paths and hashes, crop rectangles, dimensions, delivery paths, hashes and byte limits. The four original 1448 × 1086 PNG masters are retained with it. The built-in image generator did not expose a model version; none is inferred.

Desktop exports are 1280 × 480 WebP. Mobile exports are 768 × 1024 WebP, with a crop centered on the scene subject. The desktop size is below the workbook's proposed 1600 × 600 target because generation returned 1448 × 1086 masters; no export upscales the source. All exports fit the proposed 250 KiB desktop and 150 KiB mobile budgets.

To reproduce the committed exports with ImageMagick 6+ and WebP support:

```sh
node scripts/prepare-room-art.mjs
```

The normal application build consumes the existing WebP files and needs no image-processing tools. Masters are outside `public`; pages fetch only their selected delivery image. The script verifies source hashes and crop bounds before preparation.

## Integration

`RoomArtwork.vue` provides a decorative `picture` with a mobile source below 640 CSS pixels. Dimensions are explicit, decoding is asynchronous, and images have empty alternative text and no pointer interactions. RoomBanner keeps its existing SVG argument as the compatibility path for callers without a scene.

Existing headings, actions, status data and authorization messages remain HTML. The room banner uses its established dark overlay. Compact workflow headers use a stronger overlay to protect labels and data, with its darkest side following text direction. The artwork itself is never mirrored.

## Validation

`npm run check` passes, including 198 source-contract tests, localization checks, types and build. Initial JavaScript remains 215/225 KiB; the largest page chunk remains 71/72 KiB; CSS is 108/128 KiB. Desktop and mobile masters/exports were visually inspected during preparation.

`RoomArtwork.spec.ts` verifies actual image delivery, the selected responsive source, dimensions, decorative semantics and horizontal overflow at desktop, mobile and tablet widths, including English, French, Brazilian Portuguese and Arabic. CI publishes rendered header samples as `room-artwork-review` for review with the real layout, fonts and overlays. Existing visual fingerprints remain mandatory and change only after the corresponding output is inspected.

This batch does not change the Kingdom Map sprite registry. Its 24 source-required families and human review gate remain recorded separately in the [map artwork catalogue](../product/kingdom-map-workspace-assets.md).
