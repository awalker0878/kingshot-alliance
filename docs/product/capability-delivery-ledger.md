# Capability delivery ledger

Status: Current as of 2026-08-23

This ledger records shipped outcomes, active capability delivery, remaining evidence gates, and the implementation standard. GitHub remains the source of truth for exact diffs and CI results.

The delivery ledger is a work queue, not a roadmap. Every incomplete capability item created by an active delivery effort must be implemented before that effort is considered complete. A feature discovered during implementation that is required for capability correctness, usability, security, operability or architectural integrity is added to the appropriate slice rather than deferred as an unspecified future enhancement.

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

## Screenshot Intake delivery program

Target: a complete Bear Hunt Screenshot Intake capability from private upload through reviewed, exactly-once Operations result commit—not a generic OCR demo.

Canonical contract: [Screenshot Intake](screenshot-intake.md).

Architectural ownership is intentionally split:

- `Intelligence/Evidence` owns uploaded binaries, immutable source provenance, classification/extraction attempts, field confidence, reviews/corrections, duplicate decisions, Evidence-side commit attempts/receipts and retention.
- `Operations/Results` owns accepted Bear Hunt battle-report ledgers, entries and recomputed Event result aggregates.
- `app/ReadModels/ScreenshotIntake` composes the review workspace without becoming a writer.
- The Evidence application Action coordinates the commit handshake through scalar/value-object data and the destination owner Action; Screenshot Intake does not add a top-level `app/Workflows` family.

`Complete` means the phase is implemented across behavior, authorization, idempotency, persistence, UX, accessibility/localization, observability/recovery, tests and current-truth documentation. Screenshot Intake reached that state after one immutable implementation candidate passed the applicable repository quality/release gates and the Phase 15 spec→code, code→spec and UX→backend scan found no remaining gap.

### Phase queue

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Product contract and architecture ownership | `/docs/product` defines the full capability, ownership is recorded in architecture/data-ownership/ADR docs, and Evidence vs Operations write ownership is unambiguous. |
| 2 | Complete | Secure evidence upload and immutable provenance | Private storage, actual MIME/image validation, size limits, shared security scan, checksums/dimensions/source metadata, current authorization and same-scope exact duplicate reuse are implemented and behavior-tested. |
| 3 | In progress | Screenshot classification | Versioned immutable classification attempts, OCR provenance, failure state, queues/retry and Bear Hunt classification are implemented and verified. |
| 4 | In progress | Bear Hunt battle-report extraction | The first schema extracts only supported report timestamp/ranking-row facts through a versioned extractor contract with verified failure/retry behavior. |
| 5 | Complete | Field-level confidence and extraction history | Raw OCR, normalized candidates, data type, confidence, bounding boxes/warnings and immutable extraction-attempt history are retained and verified. |
| 6 | Complete | Review, Player resolution and manual correction | Every first-release screenshot requires review; included rows resolve to existing eligible Player IDs; corrections never overwrite machine history and OCR text cannot create/mutate Players. |
| 7 | In progress | Exact, visual and semantic duplicate detection | SHA duplicates are scoped/reused, perceptually similar binary-distinct images remain separate warnings, semantic duplicates block commit until explicitly resolved, and cross-Alliance evidence is never disclosed. |
| 8 | Complete | Commit preview and validation | The workspace previews current + report = post-commit score changes and blocks unresolved/ineligible/duplicate-invalid reviewed meaning. |
| 9 | Complete | Scalar cross-context commit | Evidence builds reviewed scalar/value-object meaning and invokes the `Operations/Results` owner Action without foreign Eloquent models or cross-context persistence writes. |
| 10 | Complete | Bear Hunt report ledger and idempotent aggregation | Operations records source-linked immutable report/entry facts, preserves pre-import baselines and deterministically recomputes Governor totals without additive double counting. |
| 11 | In progress | Retry/recovery and commit receipts | Stable destination idempotency survives interrupted acknowledgement; retries recover the same Operations report and Evidence retains immutable attempt/receipt history. |
| 12 | In progress | Evidence deletion, redaction and retention | Deletion/retention physically removes binaries/sensitive OCR as required, keeps minimum committed provenance, prevents bounded-scan starvation and never cascades into accepted Operations results. |
| 13 | Complete | Operational diagnostics and observability | Privacy-safe diagnostics, queue/retry visibility, audit/outbox records, failure codes and retention/recovery procedures are implemented and documented. |
| 14 | Complete | Accessibility, responsive UX, localization and visual regression | Bear Hunt entry, upload/processing/review/duplicate/preview/commit/history states are localized, keyboard/mobile-safe and protected by deterministic desktop/mobile visual baselines. |
| 15 | Complete | Full capability audit and closeout | The repository-wide contract scan found no TODO/scaffolding/stale ownership/undocumented behavior; the ledger and Screenshot Intake contract describe implemented current state; one immutable implementation candidate passed all applicable gates. |

The Screenshot Intake delivery queue is closed: every phase is Complete and no known Screenshot Intake product feature is deferred. Any defect or material change that invalidates a phase exit condition is a regression that reopens the affected phase and must restore the same release evidence before closeout.

### Cross-phase invariants

These are not deferrable cleanup items:

1. Evidence is not domain truth; accepted Bear Hunt result facts remain Operations-owned.
2. Public write contracts use scalar IDs/value objects and never pass foreign Eloquent models.
3. Every material mutation revalidates current active-Player/scope authority at the write boundary.
4. Original extraction/confidence/history remains immutable after manual correction.
5. OCR names never create or mutate Player identity.
6. Exact/perceptual/semantic duplicate handling cannot disclose another Alliance's evidence.
7. Operations aggregation is report-ledger recomputation; retry cannot add damage twice.
8. Evidence deletion/redaction never silently removes an accepted Operations result.
9. No compatibility shims, legacy routes, dual reads/writes or placeholder architecture survive closeout.
10. A phase is not Complete until its tests, UX, docs, observability/recovery and applicable gates are complete.

## Bear Hunt Debrief delivery program

Target: one complete Alliance Bear Hunt after-action experience from authoritative run facts through review handoff, comparison and trends—not a parallel BearHunt domain or a second result store.

Canonical contract: [Bear Hunt Debrief](bear-hunt-debrief.md).

Architectural ownership is intentionally composed rather than transferred:

- `Operations/Events` owns Event type, target and `EventOccurrence` run identity.
- `Operations/Results` owns accepted Bear Hunt reports and authoritative projected Governor damage/rank.
- `Operations/Participation` owns attendance facts.
- `Operations/Rallies` owns actual recorded Rally participation.
- `Intelligence/Evidence` owns unresolved extracted Governor observations and review lifecycle.
- `app/ReadModels/EventAnalysis` composes current/history/comparison/trend reads only and owns no Debrief persistence or write semantics.

`Complete` means the phase has backend behavior, authorization, applicable owner idempotency/audit/recovery, observability, responsive/accessibility/localization UX, tests, visual proof and current-truth documentation. All phases reached Complete after immutable implementation head `fd821e470ef19f51bfff14499c3f417f3cd3eeff` satisfied the documented exit conditions and passed every applicable repository release gate.

### Phase queue

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Product contract and ownership | Canonical Debrief contract, capability catalogue, gap analysis, delivery ledger and architecture/reference/operations docs agree on complete scope and owner boundaries. |
| 2 | Complete | Authoritative current-run facts | Results, Participation and Rallies owner queries expose total/Governor damage/rank, attendance and recorded Rally facts with explicit zero-vs-missing semantics and owner behavior tests. |
| 3 | Complete | Historical composition and comparison | EventAnalysis returns bounded same-Alliance Bear Hunt history, correct immediately preceding completed run, personal/Alliance trends and null/zero-safe Alliance + active-Governor comparison. |
| 4 | Complete | Unmatched-Governor review and authorization | Manager-only Intelligence/Evidence unresolved rows deep-link to Screenshot Intake, lifecycle advancement removes resolved rows, and cross-Alliance/current-authority tests prevent leakage. |
| 5 | Complete | HTTP, idempotency integration, audit and observability | Authenticated/verified active-Governor HTTP route is authorized; Debrief stays read-only; corrective writes keep owner idempotency/audit/outbox contracts; read telemetry is privacy-safe and tested. |
| 6 | Complete | Responsive, accessible and localized UX | Desktop and mobile surfaces cover summary, Your Hunt, leaderboard, Needs Review, previous Hunt, trends, history and all explicit missing-data states with keyboard/semantic/text-equivalent behavior in every supported locale. |
| 7 | Complete | Behavioral and performance verification | Current facts, all attendance states, Rally evidence semantics, Evidence lifecycle/tenant safety, comparisons, history bounds, active Governor without result and a 100-Governor no-N+1 budget are covered. |
| 8 | Complete | Deterministic visual regression | Complete/unmatched Debrief renders on deterministic desktop/mobile fixtures without horizontal overflow and both screenshots have accepted stable SHA-256 fingerprints. |
| 9 | Complete | Final audits and repository release gates | Spec→code, code→spec, UX→backend, authorization, architecture and data-ownership scans found no known gap/TODO/placeholder; PHP/Pint/PHPStan/frontend/architecture/Intelligence/CodeQL/dependency/visual/container/staging/backup/recovery gates passed on immutable implementation head `fd821e470ef19f51bfff14499c3f417f3cd3eeff`. |

The Bear Hunt Debrief delivery queue is closed: every phase is Complete and no known Bear Hunt Debrief product feature is deferred. Final closeout documentation is status-only and repeats the applicable repository gates before merge. Any defect or material change that invalidates a phase exit condition is a regression that reopens the affected phase and must restore the same release evidence before closeout.

### Cross-phase invariants

1. A Hunt/run is an existing `EventOccurrence`; there is no `bear_hunt_runs` store.
2. Results-owned projected scores/ranks are authoritative; EventAnalysis never re-sums OCR rows into competing domain truth.
3. Attendance is independent from damage; damage does not imply present and no damage does not imply absent.
4. Rally counts require explicit recorded participation evidence. Assigned/confirmed planning state is not actual participation, while recorded zero remains distinct from not recorded.
5. Unmatched Governor identity review remains an Intelligence/Evidence workflow; Debrief never creates/mutates Players from extracted names.
6. Historical comparison is same Bear Hunt type and same historical Alliance target only; current membership does not rewrite historical scope.
7. Debrief is read-only. Any review/correction/removal/attendance/Rally mutation uses the owning capability Action and inherits that owner's current authorization, idempotency, audit/outbox and recovery rules.
8. Read diagnostics never log Governor names, damage values, OCR text, screenshots or raw Evidence.
9. EventAnalysis owns composition only; no BearHunt bounded context, cross-context persistence write or duplicate statistics table may appear.
10. Missing is never silently rendered as zero, including historical Rally/attendance data and comparison percentages from a zero baseline.

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

## Kingdom Transfer Planning delivery program

Target: sourced KingShot transfer planning that preserves Alliance readiness while answering whether a Governor can transfer to a specific target Kingdom in the selected Transfer Window and exactly what remains.

Canonical contract: [Kingdom Transfer Planning](kingdom-transfer-planning.md).

A previous implementation candidate passed the standard release gates, but final code→spec reconciliation reopened the Evidence-provenance slice: Evidence-backed transfer facts must prove same-Alliance ownership and latest approved review through the Intelligence/Evidence owner contract, and manual forms must not claim an Evidence source without that authorized reference. The program remains active until the corrected candidate passes the complete gate set again.

### Phase queue

| Phase | Status | Slice |
| --- | --- | --- |
| 1 | Complete | Product contract |
| 2 | Complete | Cohort terminology correction |
| 3 | Complete | Transfer Window + official groups |
| 4 | Complete | Governor observation history |
| 5 | Complete | Eligibility domain |
| 6 | Complete | HTTP/read composition |
| 7 | Complete | Management UX |
| 8 | Complete | Decision-first participant UX |
| 9 | Complete | Accessibility/localization/mobile |
| 10 | Complete | Audit/observability/recovery |
| 11 | Complete | Behavioral/architecture/performance tests |
| 12 | Complete | Visual regression + closeout |

The queue is reopened for the Evidence-provenance correction and final immutable-head verification. The exact Transfer Pass formula remains evidence-gated because no authoritative version-bounded formula is available; observed required-pass facts are supported and missing formula evidence never produces false eligibility.

### Cross-phase invariants

1. Readiness is not eligibility.
2. Eligibility is derived, never stored as a boolean.
3. Mutable eligibility facts retain source, observation time and validity.
4. Missing, stale, conflicting or non-authoritative facts cannot produce `eligible_now`.
5. Official Transfer Groups are window-scoped; Alliance planning uses Transfer Cohorts.
6. Community data is discovery evidence only.
7. View authority is distinct from R4/R5 management authority; every write reauthorizes current scope.
8. Owner Actions keep writes; controllers/read models/Vue do not own game rules.
9. No compatibility shim, dual read/write path or legacy planning-group name survives.
10. Evidence-gated unpublished game truth is never invented.
