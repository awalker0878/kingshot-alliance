# Capability delivery ledger

Status: Current as of 2026-08-22

This ledger records shipped outcomes, active capability delivery, remaining evidence gates, and the implementation standard. GitHub remains the source of truth for exact diffs and CI results.

The delivery ledger is a work queue, not a roadmap. Every incomplete Territory & Hive Planner item created by this effort must be implemented before the effort is considered complete. A feature discovered during implementation that is required for capability correctness, usability, security, operability or architectural integrity is added to the appropriate slice rather than deferred as an unspecified future enhancement.

## Merged delivery

| PR | Slice | User outcome |
| --- | --- | --- |
| [#79](https://github.com/awalker0878/kingshot-alliance/pull/79) | Post-Pint stabilization | Restored a green baseline without a PHPStan baseline or compatibility shims. |
| [#80](https://github.com/awalker0878/kingshot-alliance/pull/80) | Gift Codes | Shared, sourced code catalogue with official redemption handoff and per-Governor status. |
| [#81](https://github.com/awalker0878/kingshot-alliance/pull/81) | Notifications | In-app, Discord, and Telegram delivery with encrypted endpoints and bounded retries. |
| [#82](https://github.com/awalker0878/kingshot-alliance/pull/82) | Command overview | One responsive decision surface for alerts, Events, Gift Codes, and recruitment follow-up. |
| [#83](https://github.com/awalker0878/kingshot-alliance/pull/83) | Alliance broadcasts | Scheduled, idempotent announcements to active members' enabled channels. |
| [#84](https://github.com/awalker0878/kingshot-alliance/pull/84) | Knowledge provenance | Searchable versioned guides with source, game-version, locale, and review metadata. |
| [#85](https://github.com/awalker0878/kingshot-alliance/pull/85) | Player progression | Freshness-aware, source-labelled observation history and consecutive change detection. |
| [#86](https://github.com/awalker0878/kingshot-alliance/pull/86) | Recruitment discovery | Opt-in public discovery, shareable filters, visible attribution, and private conversion analytics. |
| [#87](https://github.com/awalker0878/kingshot-alliance/pull/87) | Bot/API reads | Revocable least-privilege command, Gift Code, and knowledge reads with bounded responses. |
| [#88](https://github.com/awalker0878/kingshot-alliance/pull/88) | Mobile/PWA | Install, update, and offline UX while private application responses remain network-only. |
| [#90](https://github.com/awalker0878/kingshot-alliance/pull/90) | Baseline and cleanup | Established the authoritative inventory, documentation-link gate, and cleanup rule. |
| [#91](https://github.com/awalker0878/kingshot-alliance/pull/91) | Architecture enforcement | Removed V2 visual compatibility structure and made the current visual contract enforceable. |
| [#92](https://github.com/awalker0878/kingshot-alliance/pull/92) | UX system | Standardized accessible busy, validation, outcome, and confirmation behavior. |
| [#93](https://github.com/awalker0878/kingshot-alliance/pull/93) | Public webhook contracts | Replaced dead selectors with emitted Alliance-scoped lifecycle contracts. |
| [#94](https://github.com/awalker0878/kingshot-alliance/pull/94) | Webhook delivery recovery | Added signed test delivery, audited replay, bounded retry, and delivery inspection. |
| [#95](https://github.com/awalker0878/kingshot-alliance/pull/95) | Gift Code recovery | Completed official-provider handoff, terminal outcomes, backoff, and safe retry behavior. |
| [#96](https://github.com/awalker0878/kingshot-alliance/pull/96) | Operational budgets | Made reviewed production JavaScript and stylesheet ceilings release gates. |
| [#97](https://github.com/awalker0878/kingshot-alliance/pull/97) | Accessibility and localization | Replaced browser prompts with the shared accessible modal contract and an AST-based enforcement gate. |

Every merged slice passed the repository's applicable PHP, Pint, PHPStan, frontend lint/format/type/build, architecture, CodeQL, dependency-review, visual, production-image, staging, backup/restore and image-scan checks.

## Territory & Hive Planner delivery program

Target: a complete Alliance Territory & Hive Planner, not a drawing-only MVP.

Architectural ownership is intentionally split:

- `GameWorld/KingdomMaps` owns immutable/versioned map facts, map-dataset provenance and sourced game placement rules.
- `Operations/TerritoryPlanning` owns mutable planning intent, saved layouts, planning preferences, deterministic analysis, revisions and Operations-facing references.
- `app/ReadModels/TerritoryPlanning` composes map, Alliance, Player and plan reads for the editor without becoming a writer.
- `BattlePlans` remains Event-objective/assignment state and does not absorb spatial persistence.

Territory implementation and release readiness are tracked together. `Complete` means the slice has its full product/code outcome, owner tests/contracts, documentation and required release evidence. The final PR candidate must pass the complete repository Definition of Done on one immutable head; the resulting `main` commit repeats the applicable CI, CodeQL, Architecture and Visual gates. A failing post-merge gate immediately reopens blocking closeout work rather than becoming deferred follow-up.

### Slice queue

| Order | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 0 | Complete | Product/architecture contract | Product catalogue, gap analysis, delivery ledger, journeys, capability/data-ownership maps, ADR, frontend capability truth and permissions describe the final ownership and complete delivery target with no contradictory “territory unsupported” current docs. |
| 1 | Complete | Map dataset and geometry foundation | `GameWorld/KingdomMaps` has immutable schema-versioned/checksummed datasets, provenance/confidence metadata, canonical coordinate/rectangle geometry values, structured sourced placement rules and shared PHP/TypeScript golden geometry fixtures. No opaque community coordinate/rule set is treated as official truth. |
| 2 | Complete | Plan persistence and authorization | `Operations/TerritoryPlanning` owns plan/alliance/object/group/preference state, active-Player scoped authorization, optimistic revision protection, normalized editable persistence and immutable published revision snapshots. External Alliance/Governor plan references do not create fake application entities. |
| 3 | Complete | Accessible editor baseline | A localized Inertia/Vue Territory Command page supports list/create/open/save/publish/archive/clone, pan/zoom, exact coordinates, HQ/Banner/Governor city/Bear Trap placement, selection/move/delete/duplicate and synchronized keyboard/DOM editing. Laravel remains save authority. |
| 4 | Complete | Validation, territory and advanced editing | Server-authoritative map bounds, footprint collision, fixed-structure/no-build zones, caps and territory connectivity return structured violations/warnings/suggestions; browser preview matches golden fixtures; coverage rendering, box select, grouping, ungrouping, 90-degree rotation, keyboard nudging and undo/redo are complete. |
| 5 | Complete | Hive generators and march analysis | Bear-hive presets are typed generators with preview/validate/commit and customizable output; TC block placement works; distance/march analysis is deterministic, labels assumptions, supports Bear Trap selection and never presents guessed speed as official game truth. |
| 6 | Complete | Layout analysis and comparison | The planner reports covered/uncovered Governors, disconnected territory, banner efficiency, invalid/warning counts, average/median/max distances and comparable deterministic metrics; immutable revisions can be compared without mutating either. |
| 7 | Complete | Multi-Alliance Kingdom planning | One Kingdom plan supports multiple application-linked and external Alliances, independent visibility/labels/presentation colors, object counts, locks/access decisions and shared spatial validation without transferring Alliance or GameWorld ownership. Participant management revalidates authority/revision under lock and protects layers that still own planned objects. |
| 8 | Complete | Revisions, interchange and export | Publish creates immutable revisions pinned to a map dataset/checksum; restore/clone is explicit; JSON import uses parse → normalize → validate → preview → commit with schema versioning; JSON export plus shareable PNG and SVG rendering are implemented and tested. |
| 9 | Complete | Operations integration | Event positioning can reference an immutable published territory-plan revision through scalar IDs/read composition. Editing a plan head cannot rewrite an Event's referenced historical layout; BattlePlans retains objective/assignment ownership. |
| 10 | Complete | Release closeout | No Territory/Hive TODOs, placeholders, compatibility shims, temporary workflows, dual schemas or incomplete ledger items remain. The immutable release candidate must keep accessibility/mobile/visual coverage, PHP tests, Pint, PHPStan, frontend checks/build, architecture checks, CodeQL, dependency review, production image build/scan, clean PostgreSQL install, staging and backup/restore green; the merged `main` commit repeats the applicable push-triggered gates. |

The Territory & Hive Planner delivery queue is closed: every planned slice is Complete and no known Territory product feature is deferred. Any defect exposed by PR or post-merge `main` verification is a regression that reopens blocking closeout work; it is not recorded as a future enhancement.

### Cross-slice invariants

These are not deferrable cleanup items:

1. **Map fact vs rule vs preference:** a map fact describes the world; a sourced game rule determines legality; an Alliance planning preference creates a warning/suggestion. They are persisted and presented separately.
2. **Structured validation:** placement validation returns machine-readable violations, warnings and optional suggestions. A boolean-only `canPlace` API is not sufficient.
3. **Geometry parity:** browser geometry is preview only; Laravel is authoritative. PHP and TypeScript consume shared golden fixtures for coordinates, footprints, bounds, collisions, rotations, coverage and rule outcomes.
4. **Save boundaries:** pointer movement does not create one HTTP mutation per pixel/drag. The browser maintains working state and saves a coherent proposed layout against an expected plan revision.
5. **Historical truth:** current editable state is normalized; immutable published/history snapshots pin the map dataset and checksum. Downstream workflows reference a revision, not mutable head state.
6. **External references:** external Alliances/Governors used for Kingdom planning remain explicit plan-local references when no application identity exists.
7. **Canvas accessibility:** canvas is never the only control surface. Objects remain selectable/editable through semantic DOM controls with exact coordinates and non-color validation messages; the viewport also exposes keyboard-focusable zoom/fit controls.
8. **No hidden formulas/data:** Vue components do not own placement rules, cost tables, march constants or map datasets.
9. **No partial completion:** a slice is not Complete until its UX, backend behavior, authorization, persistence, validation/concurrency, accessibility, localization, observability, tests and current-truth documentation are all complete.

### Required observability/recovery

The capability records audit evidence for plan create/save/publish/archive/restore/import and Kingdom participant-layer changes; conflicts distinguish stale revision from validation/authorization failures; imports are structurally validated and previewed before write; invalid map datasets fail closed; and published revisions remain readable after newer map datasets appear.

## Previous completeness program

The pagination, shared UX/navigation, safe-bulk, Gift Code trust, recurring communications, integration-contract, external-actor parity, knowledge/operations and release-closeout improvement slices have been completed. Their detailed history remains in Git history and their current outcomes live in the capability catalogue, journeys, reference docs and owner-context tests.

## Remaining evidence gate: calculators

Calculators remain outside the Territory & Hive Planner effort. Community calculator pages demonstrate demand, but their visible results do not provide an authoritative, reviewable dataset contract.

Calculator work may start only when all of these are true:

1. Every row has a source URI, source label, `observed_at`, game-version boundary, and unit.
2. Values come from one official inspectable table or are reconciled across two independent inspectable sources plus recorded in-game evidence.
3. Disagreements, regional differences, unlock conditions, and unknown values are explicit; unknown never means zero.
4. Each released dataset is immutable, schema-versioned, checksummed, and retained when superseded.
5. Calculation code consumes the dataset through a typed domain contract; Vue components contain no cost tables or formulas.
6. Golden fixtures cover single-step, range, promotion, bonus, rounding, and unavailable-data boundaries.
7. The UI displays dataset version, source, observation date, assumptions, and a report-correction path.
8. Saved scenarios reference their dataset version so later data corrections cannot silently rewrite historical plans.

Until that evidence gate opens, calculator pages, guessed formulas, placeholder values and copied opaque tables remain intentionally out of scope.
