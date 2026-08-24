# Capability delivery ledger

Status: Current as of 2026-08-24

This ledger records current delivered outcomes, active selected-extension work and remaining evidence gates. It is a work queue, not a speculative roadmap. Git history remains the archive for retired/completed intermediate phase detail and exact diffs/CI runs.

A row is `Complete` only when its complete product/code outcome, authorization, persistence where applicable, idempotency/concurrency where applicable, audit/observability, recovery, responsive/accessibility/localization UX, tests, visual proof where applicable, documentation reconciliation and repository release gates are complete.

Program-state definitions and acceptance criteria for the active extension effort live in [Capability Extension Program](capability-extension-program.md).

## Merged delivery baseline

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

Later capability delivery is represented by the current canonical contracts below and Git history rather than duplicating every retired intermediate queue in this file.

## Current complete capability programs

| Capability/program | Status | Canonical current-truth contract |
| --- | --- | --- |
| Gift Code trust/recovery | Complete | [Capability catalogue](capability-catalogue.md) and owner/reference docs |
| Notifications, recurring Alliance announcements and delivery recovery | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and owner/reference docs |
| Pagination, shared workflow UX and bounded bulk workflows | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and owner tests |
| Integration platform and bot/API participation parity | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and public reference contracts |
| Alliance Content game parity | Complete | [Alliance Content game parity](alliance-content-game-parity.md) |
| Factual Governor Progression | Complete | [Factual Governor Progression](factual-governor-progression.md) |
| Kingdom Transfer Planning | Complete | [Kingdom Transfer Planning](kingdom-transfer-planning.md) |
| Alliance Territory & Hive Planner | Complete | [Territory & Hive Planner](territory-hive-planner.md) and [capability catalogue](capability-catalogue.md) |
| Screenshot Intake — Bear Hunt | Complete | [Screenshot Intake](screenshot-intake.md) |
| Bear Hunt Debrief | Complete | [Bear Hunt Debrief](bear-hunt-debrief.md) |
| Alliance Assistant — initial bounded intents | Complete | [Alliance Assistant](alliance-assistant.md) |

A defect or material change that invalidates a completed capability's canonical acceptance/Definition-of-Done contract reopens that capability as a regression. A new extension does not make the prior delivered capability incomplete.

## Screenshot Intake — Bear Hunt reconciliation

Target: a complete Bear Hunt Screenshot Intake workflow from private upload through reviewed, exactly-once Operations result commit.

Canonical contract: [Screenshot Intake](screenshot-intake.md).

The previous global ledger contained stale status labels for phases 3, 4, 7, 11 and 12 even though the canonical Screenshot Intake contract and final closeout state say the full 15-phase Bear Hunt program is complete. Phase 0 reconciles the ledger to that canonical truth.

| Phase | Status | Slice |
| --- | --- | --- |
| 1 | Complete | Product contract and architecture ownership |
| 2 | Complete | Secure evidence upload and immutable provenance |
| 3 | Complete | Versioned screenshot classification |
| 4 | Complete | Bear Hunt battle-report extraction |
| 5 | Complete | Field-level confidence and extraction history |
| 6 | Complete | Review, Player resolution and manual correction |
| 7 | Complete | Exact, visual and semantic duplicate detection |
| 8 | Complete | Commit preview and validation |
| 9 | Complete | Scalar cross-context commit |
| 10 | Complete | Bear Hunt report ledger and idempotent aggregation |
| 11 | Complete | Retry/recovery and commit receipts |
| 12 | Complete | Evidence deletion, redaction and retention |
| 13 | Complete | Operational diagnostics and observability |
| 14 | Complete | Accessibility, responsive UX, localization and visual regression |
| 15 | Complete | Full capability audit and closeout |

### Bear Hunt Evidence invariants retained by later extensions

1. Evidence is not domain truth; the destination owner owns accepted meaning.
2. Public write contracts use scalar IDs/value objects and never pass foreign Eloquent models.
3. Every material destination mutation revalidates current active-Player/concrete-scope authority.
4. Original extraction/confidence history remains distinct from human review/correction.
5. OCR text cannot create or mutate Player identity.
6. Exact/perceptual/semantic duplicate handling cannot disclose another Alliance's evidence.
7. Destination retries are idempotent; a recovered receipt cannot duplicate domain meaning.
8. Evidence deletion/redaction never silently removes accepted destination truth.
9. No compatibility shims, legacy routes, dual reads/writes or placeholder ownership survive closeout.

Transfer and Governor Progression Screenshot Intake are separate selected extensions below. They reuse these Evidence invariants but have their own typed schemas, destination owner Actions and acceptance criteria.

## Capability Extension Program delivery queue

Canonical contract: [Capability Extension Program](capability-extension-program.md).

Phase 0 is documentation-only. No application code, migration, route, frontend component or runtime configuration is part of Phase 0.

| Phase | Program state | Status | Slice | Acceptance criteria / exit condition |
| --- | --- | --- | --- | --- |
| 0 | Contract work | Complete | Reconcile `/docs/product` | Screenshot Intake stale rows reconciled; extension contract created/indexed; catalogue/gap analysis/ledger/journeys aligned; ownership/provenance documented before application changes; no runtime changes. |
| 1 | Selected extension | Not started | Alliance Assistant `game_fact` | `AE-01`–`AE-03`, `AE-07`, `AE-09`–`AE-12`, plus `PX-*`: source-backed Progression query, immutable dataset/source/confidence citations, unknown/conflict semantics, authorization-before-retrieval, bounded UX/tests/release evidence. |
| 2 | Selected extension | Not started | Assistant operational-self intents and safe handoffs | `AE-04`–`AE-12`, plus `PX-*`: Participation/BattlePlan/Transfer/Territory self reads are authorized/bounded; write-like requests perform zero mutation and only navigate to canonical owner workflows. |
| 3 | Selected extension | Not started | Event Readiness | `ER-01`–`ER-12`, plus `PX-*`: capability-aware pre-Event readiness composed from owner projections with explicit blockers/source/action links and no persisted readiness state machine. |
| 4 | Selected extension | Not started | Event Closeout | `ER-01`–`ER-12`, plus `PX-*`: post-Event attendance/Rally/Results/Evidence/review/Debrief completion composed with explicit missing semantics and owner correction links. |
| 5 | Selected extension | Not started | Kingdom Transfer Screenshot Intake | `TE-01`–`TE-12`, plus `PX-*`: typed fixture-proven extraction/review, tenant-safe duplicate/retry, scalar owner commit, freshness/source semantics and eligibility reevaluation without invented rules. |
| 6 | Selected extension | Not started | Governor Progression Screenshot Intake | `GE-01`–`GE-12`, plus `PX-*`: typed extraction/review, canonical identity normalization pinned to immutable Progression release, append-only Roster owner commit, tenant-safe retry/retention. |
| 7 | Selected extension | Not started | Progression Goal Planner | `GP-01`–`GP-10`, plus `PX-*`: authorized observed current state + pinned factual target/prerequisites, explicit unknown/conflict/freshness, no recommendation semantics or unqualified totals. |
| 8 | Evidence-gated extension | Gate not executed | Calculator evidence qualification per family | `CE-01`–`CE-06` and the ten qualification conditions: independent family reports, explicit source/version/unit/conflict coverage, immutable datasets, typed calculation contract/golden fixtures; no runtime calculator unlock. |
| 9 | Evidence-gated extension | Blocked pending qualification | Evidence-backed calculators | `CI-01`–`CI-08`, plus `PX-*`; implement only for a family whose Phase 8 qualification report is `qualified`. Unqualified families remain unavailable. |
| 10 | Selected extension | Not started | Territory plan vs observed state | `TR-01`–`TR-08`, plus `PX-*`: immutable desired plan versus dated observed evidence, typed coordinate tolerance, explicit stale/missing semantics, no plan/map rewrite from observation. |
| 11 | Selected extension | Not started | Intelligence change signals | `IS-01`–`IS-08`, plus `PX-*`: deterministic source-cited signals over authorized histories, typed thresholds/windows, no inferred strategic intent, privacy-safe telemetry. |
| 12 | Selected extension | Not started | Full reconciliation and release closeout | `PX-01`–`PX-15`: spec→code, code→spec, UX→backend, authorization, provenance/data ownership, missing-data, performance, accessibility/localization, observability/recovery and complete repository release gates are green on one immutable candidate. |

`Not started` means the approved product contract exists but the implementation outcome must not be described in present tense. `Blocked pending qualification` is intentional evidence gating, not an implementation defect.

## Calculator evidence qualification ledger

Qualification is independent by family. Current factual-reference completeness does not imply calculator qualification.

| Family | Qualification state | Runtime calculator state | Required next evidence work |
| --- | --- | --- | --- |
| Governor Gear | Gate not executed | Disabled | Produce `CE-*` qualification report against immutable source rows and calculation fixtures. |
| Governor Charms | Gate not executed | Disabled | Produce `CE-*` qualification report against immutable source rows and calculation fixtures. |
| Hero Gear / Mastery | Gate not executed | Disabled | Produce `CE-*` qualification report against immutable source rows and calculation fixtures. |
| Troop training / promotion | Gate not executed | Disabled | Produce `CE-*` qualification report against immutable source rows and calculation fixtures. |
| Academy / War Academy research | Gate not executed | Disabled | Produce `CE-*` qualification report; explicit source gaps/conflicts remain blockers where calculations depend on them. |
| Buildings / Truegold progression | Gate not executed | Disabled | Produce `CE-*` qualification report against immutable source rows and calculation fixtures. |

No family can transition to `qualified` by documentation assertion alone. The evidence package, immutable data release, reconciliation result and golden calculation fixtures must exist and pass review/tests. Qualifying one row has no effect on another family.

## Program-wide ownership/provenance invariants

These apply to every open delivery row and cannot be deferred:

1. **One canonical owner.** Every new datum has exactly one source-of-truth owner; ReadModels and Assistant compose only.
2. **Evidence is provenance, not destination truth.** Evidence owns artifact/extraction/review/receipt; destination owners reauthorize, validate and persist accepted meaning.
3. **Authorization before retrieval.** Unauthorized data never enters a candidate/evidence/readiness/signal/comparison set and is not filtered only after retrieval.
4. **Write ownership.** Cross-context writes use scalar IDs/value objects through owner Actions; no foreign Eloquent model mutation.
5. **Immutable source boundaries.** GameWorld datasets/releases and published Territory revisions retain version/checksum/source identity; later source changes never rewrite historical meaning.
6. **Explicit uncertainty.** Missing, stale, unknown, unsupported and conflicting states cannot silently satisfy readiness, eligibility, calculation or reconciliation.
7. **Classification discipline.** Game data, operational fact, Alliance strategy, observation, evidence and planning intent remain distinct end to end.
8. **No parallel domains for composition.** Do not create AssistantKnowledge, EventReadiness/EventCloseout, TransferOCR/ProgressionOCR, Calculator or TerritoryReality top-level contexts merely to compose existing owners.
9. **No hidden formulas/policy in presentation.** Normalization, calculation, tolerance, thresholds and business rules live in typed domain/services/configuration with tests, never controllers/jobs/bots/Vue.
10. **Recovery and privacy.** Retry/replay is bounded/idempotent and diagnostics never expose raw screenshots/OCR/private Assistant questions/sensitive provider payloads or unauthorized identity data.
11. **No compatibility debt.** Because the application is not deployed, do not retain shims, deprecated aliases, dual schemas, dual reads/writes or legacy routes.

## Definition of Done for an extension row

A selected extension does not become a Current complete capability until all applicable items below are satisfied on one immutable candidate:

- owner domain/application behavior and persistence/migrations where needed;
- active-Player/concrete-resource authorization and tenant isolation;
- idempotency/concurrency and crash/retry behavior where applicable;
- provenance, immutable-version/checksum/freshness/conflict semantics;
- audit/outbox/observability and documented recovery;
- complete HTTP/application boundaries and stable typed contracts;
- responsive/mobile UX, keyboard/screen-reader accessibility and supported-locale localization;
- behavior, authorization, architecture, contract, performance/query-budget and frontend tests;
- deterministic visual regression for material journeys;
- current-truth `/docs/product` reconciliation and architecture/reference/operations/codebase/ADR updates when their contracts changed;
- PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, CodeQL, dependency review, production image/container scan, staging/clean-database, backup/restore and other repository release gates green as applicable.

If implementation discovers a required correctness, security, operability or usability item, add it to the appropriate program acceptance criteria/delivery row and implement it before closing that row. Do not defer it as an unspecified follow-up.
