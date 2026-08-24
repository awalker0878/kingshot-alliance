# Capability completeness plan

Status: Current — 2026-08-24

This plan compares the current product with maintained Kingshot community tools. It treats external projects as discovery evidence, not as authoritative game data. Any copied game rule, coordinate set, footprint, cost table, provider behavior or march constant must have a verifiable source and an `observed_at`/version boundary before it becomes product logic.

## Discovery sources

- [Bleezy-D/Alliance-Layout-Planner](https://github.com/Bleezy-D/Alliance-Layout-Planner) — multi-Alliance Kingdom layout planning, HQs, Banners, Governor cities, Bear Traps, territory coverage, map structures/no-build zones, march times, hive presets, grouping/rotation, saved layouts and image export. The Territory implementation pins its community-observed source boundary to commit `c0162ed5f3b41bb997bac970f0c73d1545e622fb`; the upstream README declares MIT licensing, while the repository has no separate `LICENSE` file.
- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — alliance member monitoring, transfer planning, Crazy Joe guidance, Bear Hunt timers, calculators, recruitment, and multi-channel notifications.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — a related multi-game implementation with player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the [official Century Games Gift Code Center](https://ks-giftcode.centurygame.com/) — gift-code workflow discovery and the safe official redemption boundary.

## Current coverage and remaining gaps

The application has governed workflows across Alliance membership/access, recruitment review, content revisions, Events and participation, rosters/battle plans/rallies, King Perks, results, intelligence provenance, Kingdom transfers, platform administration, webhooks, Gift Codes, retryable notifications, Territory & Hive planning, Screenshot Intake, and Bear Hunt Debrief.

Alliance Assistant is complete and is no longer an active capability gap. The delivered capability exposes a bounded authorization-aware question surface over trusted Events, self-roster state, published Alliance Content and Intelligence observations with server-owned citations/provenance, explicit ambiguity/missing states, zero direct mutation and no model-knowledge fallback. Immutable implementation evidence head `0be7e4d8cd2e03c54202d06a1b084b3280c8ca1a` passed CI, Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL, Dependency Review, and King Perks Verification; any future owner-capability defect that invalidates these guarantees reopens the affected Assistant delivery phase.

Factual Governor Progression is complete and is no longer a capability gap. The selected 2026-08-23 source sweep is represented by immutable dataset `2026.08.23.2`: complete reusable public tables are canonicalized, the Century Games 58-row Governor Gear and 22-level Charm tables resolve the current Gear/Charm conflicts while preserving superseded community claims, one unpublished Academy table remains an explicit unknown source gap, and the application exposes the resulting factual corpus without recommendation semantics. Immutable implementation candidate `299e3eddb1d1f16b4be0a08c09e8c7b5091a4c8a` passed CI, Architecture V3 Verification, Intelligence Verification, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks Verification together. Calculator eligibility remains a separate evidence decision and is not implied by factual-reference completeness.

Kingdom Transfer Planning is implemented end-to-end and is no longer an active capability gap. The completed capability preserves participant/readiness/blocker/completion planning while adding sourced Transfer Windows, official window-scoped Transfer Groups, target Power Caps/classification, append-only Governor observations, deterministic eligibility requirements, explicit stale/conflicting/unknown handling, read-vs-manage authority, responsive decision-first UX, bounded queries and deterministic visual regression. Evidence-backed facts prove same-Alliance ownership and latest approved review before they may satisfy eligibility, and manual forms cannot claim an Evidence source without an owner-authorized Evidence selection. Canonical closeout commit `72e4472ded5b1b6c08ae4c98c9848438f74f03ef` passed CI, Architecture V3 Verification, Intelligence Verification, CodeQL, Dependency Review and Visual Regression. The exact Transfer Pass formula remains evidence-gated because authoritative public material does not publish it; observed in-game required-pass counts are supported without inventing formula truth.

Bear Hunt Debrief is implemented as a composed `EventAnalysis` read experience rather than a new bounded context. The completed slice composes the existing Events, Results, Participation, Rallies and Intelligence/Evidence owners for authoritative run identity, damage/rank, attendance, recorded Rally participation and unresolved review state. It provides bounded same-Alliance run history, previous-Hunt comparison, personal trends, Alliance trends, privacy-safe read telemetry, responsive/mobile UX and full localization. Deterministic desktop/mobile visual fingerprints and owner/read-model behavior, authorization, tenant-safety and query-budget tests are complete. Immutable implementation head `fd821e470ef19f51bfff14499c3f417f3cd3eeff` passed the full applicable repository gate set, including production image, ephemeral staging, backup/restore and container scanning; final status-only closeout documentation repeats the applicable gates before merge.

The Alliance Territory & Hive Planner is implemented end-to-end and is no longer a product capability gap. It provides versioned/checksummed map datasets; PHP/TypeScript geometry parity; saved Alliance/Kingdom plans; HQ, Banner, Governor City and Bear Trap placement; coverage and structured validation; typed hive generators; advanced editing; deterministic march/layout analysis; multi-Alliance Kingdom planning; immutable revisions and comparison; previewed JSON import; JSON/PNG/SVG export; accessible synchronized DOM controls; scoped authorization; audit/telemetry; and immutable Event positioning references.

All Territory & Hive Planner delivery-ledger slices are **Complete**. No known Territory feature gap is deferred as a future enhancement. The immutable PR release candidate and resulting `main` commit are verified through the repository release gates; any failure is treated as a blocking regression and reopens closeout rather than creating a roadmap item.

## Prioritized delivery plan

| Priority | Capability/UX | Outcome | Guardrail |
| --- | --- | --- | --- |
| Complete | Alliance Assistant | Authorized, source-cited answers for Events, the active Governor’s roster, Alliance strategy and observations without creating a second source of truth or mutation path | ReadModels only composes owner-authorized Queries; no unsourced model fallback, no cross-tenant retrieval, no direct writes |
| Complete | Kingdom Transfer Planning | Sourced, window-scoped transfer eligibility with explicit next actions, provenance/freshness and independent Alliance readiness | GameWorld/KingdomTransfers owns transfer facts/rules; Evidence references remain Intelligence/Evidence-owned and same-Alliance/latest-approved; no invented Transfer Pass formula or community-derived eligibility truth |
| Complete | Bear Hunt Debrief | One authorized, mobile-accessible after-action surface for authoritative damage/rank, recorded Rally participation, attendance, unresolved Governor review, same-Alliance previous-run comparison and personal/Alliance trends | Reuse Events, Results, Participation, Rallies, Intelligence/Evidence and EventAnalysis; no BearHunt bounded context or duplicate writes/statistics store |
| Complete | Alliance Territory & Hive Planner | Complete spatial planning from sourced map datasets through accessible editing, analysis, revisions, interchange and Operations references | GameWorld owns map facts/rules; Operations owns planning; community data remains provenance-gated |
| Complete | Pagination and list completeness | Opaque cursor pagination, stable sorting, URL filters and bounded query budgets for every potentially large list | Cursor scope is bound to actor, Alliance, filters and ordering |
| Complete | Shared workflow UX | Common page headers, filters, empty/loading/failure states, result receipts and permission-aware navigation | Server remains the authorization authority |
| Complete | Bulk workflows | Previewed, bounded bulk triage and correction with per-item outcomes, audit and failed-item retry | Each owner context keeps its business semantics |
| Complete | Gift Code trust lifecycle | Explicit review/dispute/expiry states, provenance and selective Governor retry | No undocumented provider automation |
| Complete | Announcements | Recurrence, test delivery, cancellation and truthful queued/sent/failed/read history with selective retry | Content owns intent; Communications owns delivery |
| Complete | Integration platform | Typed public events, secret rotation, broader event catalogue and committed OpenAPI/webhook schemas | Public schemas remain distinct from internal messages |
| Complete | Bot/API write parity | Revocable external identity pairing and idempotent Event response/registration writes | A client never supplies an arbitrary actor identity |
| Complete | Knowledge trust | Stale-content review queue, revisioned corrections and contextual Event links | No unreviewed or invented strategy claims |
| Complete | Operational diagnostics | Safe queue/outbox/delivery inspection, correlation search and allowlisted replay | Sensitive payloads are fingerprinted and replay remains idempotent |
| Complete | Factual Governor Progression | Immutable, source-labelled factual progression corpus, Governor Hero observations and dataset-pinned saved loadouts without recommendations | Complete reusable open tables are canonicalized; unknown/conflicting values remain explicit; calculator eligibility remains separately evidence-gated |
| Evidence-gated | Calculators | Troop, Governor Gear, Charm and Hero Gear planning with saved scenarios | No implementation until the dataset gate in the delivery ledger is satisfied |

## Kingdom Transfer Planning implemented scope

An authorized active Alliance member can inspect sourced, server-authoritative transfer eligibility, while R4/R5 managers can maintain the owned planning facts and workflow. The completed capability:

1. binds each Transfer Plan to a sourced Transfer Window with explicit UTC phase boundaries;
2. keeps official Transfer Group membership and target Power Caps/classification scoped to that window;
3. records Power, Transfer Score, available/required Transfer Passes, invitation state and in-game verification as append-only sourced observations with explicit freshness;
4. derives eligibility per requirement without persisting an eligible boolean;
5. returns Needs verification for missing, stale, conflicting or non-authoritative material facts;
6. keeps workflow readiness/manual blockers independent from game eligibility;
7. exposes source/date/freshness and the primary remaining action on responsive participant cards;
8. supports useful eligibility triage filters and bounded query composition;
9. preserves authorized observation/history context so corrections deterministically re-evaluate the result;
10. leaves the unpublished Transfer Pass formula evidence-gated while supporting observed required-pass counts.

The canonical [Kingdom Transfer Planning contract](kingdom-transfer-planning.md), global delivery ledger and implementation agree on the completed capability. Final spec→code, code→spec, UX→backend, authorization, provenance, architecture ownership, data-model, accessibility, localization, observability and test-coverage reconciliation found no known implementable gap; any later regression reopens the affected delivery phase.

## Bear Hunt Debrief implemented scope

An authorized Governor can now:

1. open Debrief from an Alliance Bear Hunt occurrence while preserving the occurrence as the canonical run identity;
2. see Operations/Results-owned total damage, Governor damage/rank and accepted-report contribution facts without recomputing OCR rows;
3. see Participation-owned present/absent/excused/unknown attendance independently from damage;
4. see Rally counts only from explicit recorded participation decisions and distinguish recorded zero from not recorded;
5. route manager-only unresolved Governor observations back to Screenshot Intake without creating a second identity-matching workflow;
6. compare with the immediately preceding completed Bear Hunt for the same historical Alliance target, including Alliance damage, Governor count, attendance count/rate, recorded Rally participation and active-Governor damage/rank/attendance/Rallies;
7. inspect bounded personal and Alliance trends plus navigable run history with explicit missing-data semantics;
8. use the same capability on mobile and keyboard/screen-reader paths with localized dates, numbers, status labels and textual equivalents for visual trend encoding;
9. rely on owner mutations for corrections/review so idempotency, audit, outbox and recovery rules are not duplicated by EventAnalysis;
10. receive a privacy-safe read surface whose telemetry records availability/count dimensions but not Governor names, damage values, OCR text or screenshot data.

The canonical [Bear Hunt Debrief contract](bear-hunt-debrief.md), global delivery ledger and implementation agree on the completed capability. Final spec→code, code→spec, UX→backend, authorization, architecture and data-ownership audits found no known product gap on the immutable implementation candidate; any later regression reopens the affected delivery phase.

## Territory & Hive Planner implemented scope

An authorized Governor/officer can now:

1. create an Alliance or Kingdom-scoped territory plan against a concrete versioned map dataset;
2. place and edit HQs, Banners, Governor cities and Bear Traps using exact KingShot coordinates;
3. distinguish application-linked Players/Alliances from plan-local external references;
4. see territory coverage, gaps, disconnected chains, collisions, structure exclusions and placement-rule failures;
5. generate and customize typed Bear-hive layouts rather than copy opaque hard-coded coordinate blobs;
6. analyze average/median/maximum distance and estimated march time with visible assumptions and no invented authoritative speed claim;
7. coordinate multiple Alliances on one Kingdom plan with independent labels, colors, visibility, locking and object counts;
8. compare immutable revisions using deterministic layout metrics without mutating either revision;
9. save draft work, publish immutable revisions, clone/restore versions and reject stale-editor writes;
10. preview/validate schema-versioned JSON imports before atomic commit and export JSON, PNG and SVG;
11. operate material editing through synchronized DOM controls and keyboard actions rather than requiring canvas-only interaction;
12. reference a published layout revision from applicable Event/Operations positioning without moving spatial persistence into `BattlePlans`.

## Data and rule taxonomy

The implementation preserves three separate concepts:

- **Map fact** — what exists in the represented KingShot world: coordinates, zones, fixed structures and map bounds. Owned by GameWorld/KingdomMaps.
- **Game placement rule** — a sourced/versioned rule that determines whether a placement is legal. Owned by GameWorld/KingdomMaps and enforced authoritatively on the server.
- **Planning preference** — an Alliance/Kingdom planning choice such as a preferred Bear radius or march-speed assumption. Owned by Operations/TerritoryPlanning and reported as warnings/suggestions rather than fake game-rule violations.

Validation returns structured violations, warnings and suggestions. Disconnected planned territory is a planning warning; it is not falsely represented as an official KingShot prohibition.

## Geometry parity

Browser geometry is an immediate preview; Laravel remains authoritative. PHP and TypeScript consume the shared `tests/v3/Fixtures/territory-geometry.json` contract for bounds, footprints, collisions, exclusions, caps, Bear-radius planning warnings, disconnected territory, coverage and analysis outcomes.

The current map schema requires `Coordinate` and `Rectangle` canonical primitives. Rotation is the validated integer set `0 | 90 | 180 | 270`; distance is a deterministic analysis result. Unused Circle/Polygon/Footprint abstractions are not claimed as implemented architecture.

## Persistence and history

Interactive movement remains client working state until an explicit save boundary. Current editable state is normalized. Published revisions retain immutable schema-versioned snapshots with the exact map dataset ID/checksum. Event/Operations references target the immutable published revision, never mutable plan-head state.

Kingdom-plan participant layers accept application-linked Alliances or explicit external references. Linked Alliances are revalidated against the plan Kingdom under the write lock, participant identity/removal is refused while the layer owns planned objects, and database constraints prevent a participant from being simultaneously linked and external.

## Calculator gate

Community calculator pages demonstrate demand, but their visible results do not provide an authoritative, reviewable dataset contract. Calculator implementation starts only after the source, version, reconciliation, checksum, tests, and visible-provenance requirements in the delivery ledger are met. Calculator evidence work is independent of Territory & Hive Planner completion.

## Engineering standards for every slice

1. Owner context keeps write semantics; cross-context pages live in `app/ReadModels` where composition is necessary.
2. Public write actions accept scalar IDs/value objects and never return Eloquent models.
3. Every material mutation is authorized against the active Player and concrete plan scope at commit time.
4. Geometry, placement, plan-analysis and import logic live in typed domain/services, never Vue components or controllers.
5. External/community data is immutable, source-labelled, observation/version bounded and checksummed before use as product truth.
6. Every page must be responsive, keyboard usable and localized through an existing/new domain; material journeys must be included in visual regression coverage.
7. `/docs` changes in the same pull request whenever ownership, integration flow, game-data policy or a user journey changes.
8. Full PHP, frontend, architecture, security, visual, container, staging, backup/restore and image-scan checks must pass before merge.
9. No compatibility shims, duplicate schemas, dual reads/writes or temporary legacy names are retained because the application is not deployed.
