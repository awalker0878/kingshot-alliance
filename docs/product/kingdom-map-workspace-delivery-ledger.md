# Kingdom Map workspace delivery ledger

## Resume header

- Status: **In progress; not merge-ready.** PR #165 remains draft.
- Branch: `astra/kingdom-map-workspace`; audited checkpoint `aa88d4d11d7dc786b0d262ff16e038d8098c6ee8` (108 commits, 188 changed files).
- Baseline/current main: `044a6be16e54b3bc2ee5ae9ca9adf6a9c9c5923c`.
- Source plan: the full user-supplied attachment is now reconciled and preserved as a [source reference](../reference/kingdom-map-workspace-source-plan.md). The prior inaccessible-share gate is resolved by this supplied content. No legacy schema adapters will be introduced.
- Ownership: GameWorld owns facts; Operations owns intent/publication/recovery; Intelligence owns evidence; ReadModels compose reads; NotificationDelivery composes delivery. PR #164 remains separately owned at `7b1eb1ad7685b47f4c69440c1b4041454b906997`; only necessary, attributed dependencies may be incorporated.
- Local toolchain: Node 24.19/npm 11.9 and PHP 8.5.10; both lockfile hashes match regenerated dependency artifact `10330572839` from run `34732389289`. The temporary checkout was restored from durable audit commit `2fd7d75a6c9376c173980151bdfe9017caeac297`; previously recovered browser binaries must be reacquired after workspace expiry. PostgreSQL 18.6 binaries were recovered, but this execution profile maps only UID 0 and cannot run the server as its required unprivileged user. Use isolated PostgreSQL CI for database checks; no SQLite substitution, executable patch, or persistent database reset.
- Executed baseline: 49 focused Node tests, 12-case geometry/analysis parity, export source contract, 17-locale key contract, Vue types and ESLint pass (two inherited lint warnings). These do not prove browser journeys, translated content, imagery or export fidelity.
- Failing baseline: test suite registry omits the new TerritoryPlanning Unit directory; architecture reports recovery HTTP mutation shape and undocumented notification transaction composition, plus inherited Governance/Operations boundaries. Visual Regression run `34797169139` fails fixture preparation with an active-membership Kingdom-change exception. Six current workflows report `action_required`; none is recorded as passing.
- Current work: recovery writes now enter through owner actions; recovery reads do not delete, expiry pruning is bounded and scheduled, JSON depth/list counts and future revisions are rejected, and date casts match receipts. Meaningful expiry and future-revision tests await the next PostgreSQL CI run.
- Ownership dependencies: the Operations role policy/provisioner and Governance health caller are copied exactly from PR #164 commit `7b1eb1ad7685b47f4c69440c1b4041454b906997`. Current-Kingdom HTTP checks are added; PR #164 remains separate. ADR 0081 documents the existing rollback-tested notification page transaction, avoiding that PR's reserved ADR numbers 0071–0080.
- Verification repairs: all 350 PHP test sources are registered once; 78 architecture tests pass (74,218 assertions), full PHPStan passes with zero errors. Existing PR formatting defects are corrected. Visual fixtures now use unique stable Player IDs so separate seed processes cannot accidentally reuse another fixture's Governor. Browser results still require the next CI run.
- CI on the durable audit checkpoint: Kingdom Maps Assurance `34799118355`, CodeQL `34799118343` and Dependency Review `34799118327` passed. Architecture, CI, Intelligence and Visual checks failed; the repaired candidate must run all applicable gates again.
- Source artwork: renewed review artifact `10336921355`, SHA-256 `1b07b22845f237c1b1d1384ac541973cf72f294dd97c81d6f55fcbdf8b4c805c`; source acquisition is not production artwork coverage or human approval.
- Next actions: complete asset/scene/export contracts and accessible Explorer, finish editing/analysis/integration, then reconcile every requirement against measured exact-candidate evidence.
- Temporary source/apply workflows and encoded transport chunks remain cleanup items; they are not product functionality and must be removed before readiness.

Checkpoint SHAs name preceding durable work. Git history and the PR verification receipt identify the exact current candidate without circular self-reference.

## Renderer and Explorer checkpoint (2026-09-14)

- Durable owner checkpoint `c18f672ea1bd26b4a1627ffbd8218de23f1cf94d`: full PostgreSQL PHP CI passed **2,088 tests / 99,046 assertions**, including fresh installation, formatting and static analysis. Architecture `34817624326`, Intelligence `34817624317`, Kingdom Maps `34817624362`, CodeQL `34817624472` and Dependency Review `34817624368` passed. Frontend stopped at raw Explorer text; Visual stopped at a second fixture's reused Alliance URL. Those remaining defects are repaired in this checkpoint and require new CI.
- Canvas and SVG now consume one ordered presentation list: exact terrain spans, rotated footprints and coverage, selected/observed states, optional grid, label collision suppression and viewport culling. A weakly held spatial index supports geometry lookup. Hidden layers do not intercept reference selection; locked selections cannot preview a partial move. Artwork hooks accept bounded upright anchors, but no unreviewed bitmap counts as completed art coverage.
- Explorer search reaches every matching object in bounded 100-row pages. Grid/label settings round-trip; saved views support rename/remove without silently deleting the oldest view. Mobile panels and fullscreen controls are wired. Coordinate input rejects non-integers. Private view requests are aborted and fenced when the active scope changes. All new Explorer labels have native-language entries in 17 catalogues; human language and RTL review remain open.
- Browser JSON export now emits canonical schema **2** and an explicit `head_revision`, excluding UI status/capability fields. A shared fixture is asserted by the actual TypeScript exporter and PHP owner decoder. Visual exports use the same layers as Canvas, include exact map/revision metadata and identify unsaved working drafts. Raster data can be embedded once per unique image; SVG and PNG allocations remain bounded.
- The production Editor exceeded the unchanged 72 KiB page budget. Loading the optional export implementation on demand reduced its page chunk to **73,060 bytes** (budget 73,728); initial JavaScript approximately 210 KiB and stylesheet approximately 107 KiB pass their existing limits. This is a build-size measurement, not representative-device interaction proof.
- New behavioral verification covers terrain holes, Y-up painting, full semantic traversal, moving offscreen previews, hidden layers, label priority, rotation and browser/PHP interchange. Product-level browser journeys, artwork identity/anchor review, resource-density LOD, annotations/templates, complete editing, sharing revalidation, and full measured acceptance remain in progress.

## Deepseek execution checkpoint (current candidate)

- Branch `feature/kingdom-map-deepseek`; HEAD `fc83b8f2d09ce1e99d46631366a05337dd7cef9e`; `git rev-parse main` returns the same SHA, so this branch is content-identical to `main`. Nothing is pushed, merged or force-pushed from this checkpoint.
- Authoritative specification: the user-supplied plan is preserved as [Kingdom Map workspace implementation plan](kingdom-map-workspace-implementation-plan.md). It is the new-deployment plan; the earlier [source reference](../reference/kingdom-map-workspace-source-plan.md) records the superseded PR #165 audit.
- Historical-state reconciliation: the resume header above describes branch `astra/kingdom-map-workspace` at main `044a6be16e54b3bc2ee5ae9ca9adf6a9c9c5923c`. Current code has moved on. The following ledger claims were re-checked against the working tree and are now stale or repaired — see the reconciliation table below.

### Reconciliation of ledger claims against current code

| Ledger claim | Current code |
| --- | --- |
| "SVG export ignored nonzero map origins" | Repaired in `buildSvg`; now locked by an exact translation-invariance assertion in `scripts/check-territory-export.mjs` and `tests/Contexts/Operations/TerritoryPlanning/Frontend/ExportBounds.test.ts` that translates the world *and* a nonzero-origin sub-region and requires byte-identical output. |
| "pointer cancellation could commit a move" | Repaired and covered by the editing regression slice. |
| "Publish could omit unsaved work" | Repaired; publication carries a normalized snapshot and checksum receipt, and unsaved working drafts are identified in exports. |
| "Resource/terrain corpus counts do not supply actual coordinates" | Superseded. Released `kingshot-spatial-complete-v2-r1` carries `kingshot-terrain-2026-09-13.json` (501 lakes, 1,948 mountains) and `kingshot-resources-2026-09-13.json` (6,499 nodes) as checksummed artifacts. See the [asset catalogue](kingdom-map-workspace-assets.md). |
| "The production Editor exceeded the unchanged 72 KiB page budget" | Holds at the time of writing; the optional import-preview panel is now an async component and the Editor page chunk measures 71 KiB / 72 KiB. |
| "test suite registry omits the new TerritoryPlanning Unit directory" | Not reproducible here: the Node runner globs `tests/**/Unit/*.test.ts` and `tests/**/Frontend/*.test.ts`. |
| "Local toolchain: Node 24.19/npm 11.9 and PHP 8.5.10" | Node 24.19.0 and npm 11.17.0 are present. **PHP and composer are absent from this execution profile**, so no PHP-side gate can be executed or claimed (see below). |

### Executed evidence

`npm run check` completed end to end with exit code 0, covering: ESLint (0 errors, the two inherited `resources/js/pages/Public/Recruitment/Apply.vue` attribute-order warnings), Prettier, `vue-tsc --noEmit`, the Node source-contract suite (**175 tests, 175 pass, 0 fail**), accessibility, documentation-link, page/event/action localization-coverage, product-language, territory localization (16 catalogues), territory geometry parity, territory export, the artwork registry structure gate, `vite build`, localization-chunk and performance-budget checks.

Performance receipts from that run: initial JavaScript 212 KiB / 225 KiB, application entry source 6 KiB / 20 KiB, largest page chunk 71 KiB / 72 KiB (`resources/js/pages/Kingdom/Territory/Editor.vue`), largest stylesheet 107 KiB / 128 KiB. Localization chunks 323; page chunks 92.

Defect repaired: `test:source-contracts` reported `tests 0 / pass 0` because the npm script quoted its globs with single quotes, which PowerShell does not strip before handing arguments to Node. The script now uses double quotes, so the suite actually runs.

### Artwork pipeline (KM03 / KMAP-004)

- `resources/data/kingdom-map-art/manifest.v1.json` (registry version `2026.09.14.1`) is the single versioned registry: 27 entries, of which 24 raster families are `awaiting_source` and 3 are application-owned vector overlays.
- `resources/js/features/territory-planner/engine/artwork.ts` owns the typed schema, a strict validator that returns diagnostics instead of throwing, and a bounded lazy loader. `outpost.*` keys degrade to `outpost.unknown`; nothing invents art or provenance.
- `resources/js/features/territory-planner/engine/artwork-runtime.ts` is the Vite-aware bridge used by Canvas and by exports.
- `scripts/kingdom-map-art-import.mjs` prepares explicitly selected local source files and emits registry-valid, content-addressed records. `scripts/check-kingdom-map-art.mjs` is the structure gate (`npm run check`) and the strict completeness gate (`npm run check:kingdom-map-art:strict`).
- Verification: `tests/ReadModels/TerritoryPlanning/Frontend/TerritoryArtwork.test.ts` (8 tests) and `TerritoryArtworkImport.test.ts` (5 tests). The latter round-trips prepared bytes through the PNG decoder and asserts the emitted record is accepted by `validateArtworkManifest` unchanged.
- **Blocked input.** `npm run check:kingdom-map-art:strict` exits 1 with `BLOCKED_INPUT`, naming every absent icon/sprite/detail representation. The rights-cleared Kingshot artwork master pack has not been supplied, so the 24 raster entries legitimately remain `awaiting_source`. No imagery, hash, provenance or authorization was fabricated to close this gate.

### Visual exports (KM14 / KMAP-013)

`buildSvg` accepts a bounded `options.bounds` region. `exportScopeBounds` resolves `map`, `viewport`, `selection` and `alliance` scopes, pads by 2 tiles and clamps to the released map so a rendition can never expose geometry outside the authorized release; `estimateExportSize` rejects non-positive or over-large allocations before any raster memory is taken. Exports record locale, font family, artwork version, plan revision and map checksum provenance. The Editor exposes the scope selector, and every new string has entries in all 17 territory catalogues.

### Environment-limited gates (not executed, not claimed)

PHP 8.5, composer, PostgreSQL and a browser are absent from this execution profile. Therefore `composer check:ci`, `composer lint:check`, `composer types:check`, `composer test:architecture`, `php artisan test`, fresh-schema installation and `npm run test:visual` were **not** executed and are neither passing nor failing here. They remain required before merge readiness and must run in CI. No SQLite substitution, executable patch or persistent database reset was performed.

## Sources and boundaries

The delivery implements the user's **Kingshot Kingdom Map — Complete Implementation Planning Prompt** and **Kingshot Kingdom Map — Execute to Completion** instructions. The formerly inaccessible shared source is reconciled by the full attachment supplied in this conversation; see the preserved source reference above.

Architecture ownership remains defined by [ADR 0009](../architecture/adr/0009-versioned-map-truth-and-territory-planning.md), [KingdomMaps](../architecture/contexts/game-world/kingdom-maps.md), and [TerritoryPlanning](../architecture/contexts/operations/territory-planning.md). GameWorld owns immutable facts; Operations owns plans and publication; Intelligence owns observations/evidence; ReadModels compose authorized reads. Artwork is presentation, not collision geometry or evidence of mechanics.

This is a new deployment: one canonical contract, no obsolete schema readers, compatibility aliases, dual reads/writes, upgrade shims, or parallel authorities. Historical immutable releases/revisions, safe loading fallbacks, current integrations, and supported-browser behavior remain legitimate requirements. Destructive schema verification is restricted to disposable test databases.

The user supplied authorization for Kingshot artwork. Record actual source files and provenance; do not extend that authorization to unrelated third-party code/data. Placeholder or fallback artwork does not satisfy final required asset coverage.

## Complete acceptance queue

These `KMAP` identifiers are newly assigned delivery identifiers, distinct from the source plan KM-00 through KM-18 identifiers. The [acceptance matrix](kingdom-map-workspace-acceptance.md) maps these to visible source packages, owners, implementation areas and required evidence. [Implementation](kingdom-map-workspace-implementation.md) and [asset catalogue](kingdom-map-workspace-assets.md) are canonical design/coverage references.

| ID | Required outcome | Current state | Acceptance / remaining evidence |
| --- | --- | --- | --- |
| KMAP-001 | Source, baseline, ownership, existing-behavior audit and durable continuation | In progress | Exact checkout; reconciled source plan; traced UI/server/data/test behavior; no unsupported completion labels. |
| KMAP-002 | Canonical V2 contracts and fresh installation | Unverified | All owned callers agree; obsolete inputs rejected; clean disposable deployment without repair/upgrade shims. |
| KMAP-003 | Complete evidence-supported object/layer catalogue and actual data delivery | Unverified | Fixed structures/facilities/terrain/resources/regions/exclusions rendered from materialized authorized records; partial/unavailable coverage explicit; no invented coordinates. |
| KMAP-004 | Authorized artwork pipeline and unified versioned asset registry | Unverified | Every required family/variant has palette icon, sprite, detail picture; manifest provenance/checksums/anchors/variants; safe preparation/loading/caching and export parity. Missing source art remains a gate. |
| KMAP-005 | One canonical renderer and geometry fidelity | Unverified | Culling/hit testing/redraw/HiDPI/zoom detail/bounded memory; correct origins/axes/rotation/edges; PHP/TypeScript parity; art separate from footprint/coverage/hit target. |
| KMAP-006 | Kingdom Explorer across desktop/tablet/mobile | Unverified | Search, inspector, layers/legends, minimap, coordinates/jump, bookmarks/saved views, fit controls/full-screen, keyboard/pointer/touch, explicit modes and all failure/read-only states. |
| KMAP-007 | Complete editing and authorized persistence | Unverified | Place/move/duplicate/delete, coordinates/snap/preview, multiselect/group/legal transformations/locks/alignment, undo/redo; expected revisions, server validation, retries/conflicts/recovery; no pointer-event write flood. |
| KMAP-008 | Hive templates and Governor assignments | Unverified | Bear-centered generation, layouts/templates, slots/reservations, real roster and plan-local identities, alternative comparison and visible assumptions. |
| KMAP-009 | Explainable analysis and accepted suggestions | Unverified | Coverage/connectivity/resource access/Banner efficiency/density/distances; reproducible constrained proposals; explicit preview/accept; sourced mechanics and honest limitations. |
| KMAP-010 | Plan versus Observed Reality | Unverified | Authorized pinned comparisons, freshness/coverage/uncertain matches, moved/unexpected/missing-vs-not-observed distinctions; no implicit overwrite of planning intent. |
| KMAP-011 | Multi-alliance collaboration and review | Unverified | Independent identities/scope, object comments, assignments, revision notes, review/publication, optimistic concurrency; no unsupported live-collaboration claim. |
| KMAP-012 | Real owner integrations and bounded operations | Unverified | Roster/event/audit/Intelligence/notification entry points invoked; preferences/current authority/idempotency/retries/bounded fan-out; commands/queues/scheduling/storage/diagnostics wired. |
| KMAP-013 | Canonical import, artwork-bearing exports and private sharing | Unverified | Validated preview/dry run/duplicate handling/commit; JSON round trip and old-format rejection; PNG/SVG viewport/selection/alliance/map fidelity; limits/cleanup/failures; private scoped revocable sharing. |
| KMAP-014 | Security, accessibility, localization and measured performance | Unverified | Scope switching/revocation/stale tabs/cross-tenant/cache/export/input tests; semantic object list/focus/keyboard/touch/announcements/non-color cues; long labels/RTL; measured budgets on real and labeled synthetic fixtures. |
| KMAP-015 | Exact-candidate release assurance and cleanup | Open | All applicable normal gates, clean-install evidence, desktop/tablet/mobile visual inspection, performance receipts, obsolete-path/placeholder/dependency/docs audit, temporary tooling removal, full requirement reconciliation. |

## Evidence policy

Implementation, narrow validation, full validation, merge readiness, merge, and deployment are separate claims. Each completed item must name actual files, tests, data/assets, execution results, and a containing immutable SHA. Authored or queued checks are not passing checks. Update affected evidence after changes and retain exact run/artifact references in the PR receipt.

Final gates include applicable PHP/style/static analysis/architecture, frontend/lint/types/build, geometry and dataset/artifact contracts, authorization/integration, browser/visual, dependency/security, fresh-schema, container/staging/recovery, and source/artwork/data completeness. Review screenshots; do not blindly bless baseline changes. Repair root causes rather than disabling or weakening gates.

Missing external sources, artwork, or permissions require exact unblock information and continued independent work. A hard execution limit permits a durable **NOT COMPLETE** checkpoint, never a fabricated completion claim.

## Inspected defects and delivery constraints

- SVG export ignored nonzero map origins; pointer cancellation could commit a move; browser rendering ignored saved rotation. Renderer/geometry owners are replacing these paths under one scene contract.
- Publish could omit unsaved work. Save/publication now being connected through normalized snapshot and checksum receipts; schema 2 is the sole plan contract.
- Browser drafts used plan/revision-only localStorage keys; authority-bound recovery must replace that private-data retention.
- Facility catalogue contains 90 records while old Canvas rendered only fixed structures. Resource/terrain corpus counts do not supply actual coordinates. See the asset catalogue for exact inspected coverage.
- Archive did not prevent subsequent saves/publication; stale mutations must fail closed.
- This execution profile cannot start PostgreSQL under a non-root identity. Database behavior/concurrency evidence must come from the isolated PostgreSQL CI service. No SQLite substitution or persistent database reset is authorized.
- Inherited Architecture run 34732445211 at setup SHA 9f4b2f8c999d8603b9a19f704d7c4bc5f14eda7f failed current-Kingdom controller and foreign permission-vocabulary boundaries. Resolve these required gates while preserving PR #164 ownership.
