# Capability delivery ledger

Status: Current as of 2026-09-04

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

## Active complete candidate awaiting release gates

| Capability/program | State | Canonical candidate evidence |
| --- | --- | --- |
| Communications Recipient Delivery & Notification Experience | Complete candidate — PR #144 remains draft until the exact reconciled head passes every required release gate | [Product contract](communications-recipient-delivery-expansion.md), [acceptance matrix](communications-recipient-delivery-acceptance.md), [delivery ledger](communications-recipient-delivery-ledger.md), [ADR-0015](../architecture/adr/0015-separate-logical-notifications-from-delivery-routes.md), [delivery architecture](../architecture/contexts/communications/delivery.md), `CommunicationsRecipientDeliveryAcceptanceV3Test` and existing Communications/source V3 suites |

The candidate preserves the already-complete Notifications baseline while extending it to one logical message, account/Governor routing policy, multiple concrete destinations, quiet hours/mute/urgency, Web Push, Accounts-owned verified email, bounded digests, endpoint lifecycle/health and privacy-safe diagnostics. This row moves into the Current complete table only after the exact containing PR head is green across CI, Architecture V3 Verification, Intelligence Verification, King Perks Verification, Visual Regression, CodeQL and Dependency Review.

## Current complete capability programs

| Capability/program | Status | Canonical current-truth contract |
| --- | --- | --- |
| Accounts Sign-In Methods & Credential Evolution | Complete | [Accounts Sign-In Methods & Credential Evolution](accounts-sign-in-methods.md), [acceptance](accounts-sign-in-methods-acceptance.md), [delivery ledger](accounts-sign-in-methods-delivery-ledger.md), [ADR-0014](../architecture/adr/0014-account-sign-in-methods.md) and Accounts V3 tests |
| Gift Code trust, discovery and redemption expansion | Complete | [Extension closeout](gift-code-extension-program.md), [ADR-0004](../architecture/adr/0004-gift-code-trust-from-append-only-evidence.md), [owner reference](../reference/gift-codes.md) and `GiftCodeBehaviorV3Test` |
| Notifications, recurring Alliance announcements and delivery recovery | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and owner/reference docs |
| Pagination, shared workflow UX and bounded bulk workflows | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and owner tests |
| Integration platform and bot/API participation parity | Complete | [Capability catalogue](capability-catalogue.md), [user journeys](experience/user-journeys.md) and public reference contracts |
| Alliance Content game parity | Complete | [Alliance Content game parity](alliance-content-game-parity.md) |
| Factual Governor Progression | Complete | [Factual Governor Progression](factual-governor-progression.md) |
| Kingdom Transfer Planning | Complete | [Kingdom Transfer Planning](kingdom-transfer-planning.md) |
| Alliance Territory & Hive Planner | Complete | [Capability catalogue](capability-catalogue.md), [Capability completeness plan](capability-gap-analysis.md), [user journeys](experience/user-journeys.md), and architecture/ADR docs |
| Territory Planner — Plan vs Observed Reality | Complete | [Territory plan versus observed reality](territory-plan-observed-reality.md). Published Territory revisions remain immutable desired state; Evidence owns reviewed source provenance; Observations owns append-only accepted spatial facts; KingdomMaps owns geometry; ReadModels composes authorized comparison without plan mutation. |
| Screenshot Intake — Bear Hunt | Complete | [Screenshot Intake](screenshot-intake.md) |
| Bear Hunt Debrief | Complete | [Bear Hunt Debrief](bear-hunt-debrief.md) |
| Alliance Assistant — initial bounded intents | Complete | [Alliance Assistant](alliance-assistant.md) |
| Alliance Assistant — GameWorld and operational-self extension | Complete | [Alliance Assistant GameWorld extension](alliance-assistant-gameworld-extension.md) |
| Event Command Readiness & Closeout | Complete | [Event Command — Readiness & Closeout](event-readiness-closeout.md) |
| Intelligence Change Detection | Complete | [Intelligence Change Detection](intelligence-change-detection.md) and [delivery ledger](intelligence-change-detection-delivery-ledger.md) |

A defect or material change that invalidates a completed capability's canonical acceptance/Definition-of-Done contract reopens that capability as a regression. A new extension does not make the prior delivered capability incomplete.

## Current complete capability — Event Command Readiness & Closeout

Canonical contract: [Event Command — Readiness & Closeout](event-readiness-closeout.md).

PR #123 implements the occurrence-scoped Event Command projection and owner-query integrations. Functional, architecture, static/security, visual, release/staging, backup/restore and documentation gates are green on immutable candidate `b912e9c8f2a09da8876317bc07fcefeacacfb4cc`. Event Readiness and Event Closeout are **Current complete capability** behavior.

The release candidate preserves these non-negotiable boundaries:

1. `ReadModels/EventManagement` authorizes and composes; it does not own Event, Participation, Poll, Roster, BattlePlan, Rally, Result, Territory, Content, Communications, Evidence or EventAnalysis facts.
2. No `event_ready`, `event_complete`, readiness lifecycle or blocker count is persisted.
3. Readiness and closeout are occurrence-scoped, including recurring Events and explicit occurrence switching.
4. Cancellation remains Event-owned truth and is presented as not applicable rather than synthetic completion.
5. Missing/unavailable blocking owner state is `unknown` and never silently satisfies readiness or closeout.
6. Every actionable item exposes its canonical owner and a navigation-only handoff; the destination owner reauthorizes and performs the mutation.
7. Alliance-authored guidance remains labelled Alliance strategy; Communications queued state is not delivery success; Evidence remains provenance/review state rather than destination truth.

## Current complete capability — Intelligence Change Detection

Canonical contract: [Intelligence Change Detection](intelligence-change-detection.md). Detailed evidence: [Intelligence Change Detection — Delivery Ledger](intelligence-change-detection-delivery-ledger.md).

Phase 11 implements deterministic source-cited change signals over existing owner histories without a new bounded context, signal table or second Intelligence truth store. Implementation candidate `e5c492f9391431ab68e1b2ca215038f448e5539d` is green across CI, Intelligence Verification, Architecture V3 Verification, Visual Regression, CodeQL and Dependency Review, including production image/staging, backup/restore and scan gates.

The delivered capability preserves these non-negotiable boundaries:

1. Owner contexts retain facts and writes; `ReadModels/IntelligenceSignals` derives recomputable values only.
2. Authorization and concrete Alliance/Governor scope are established before owner data enters signal composition.
3. Missing, stale, unsupported and conflicting states remain explicit; missing is not zero or disappearance.
4. Ordinary Alliance history does not emit disappearance/reappearance because current source history does not prove exhaustive absence.
5. Governor Hero absence requires a complete-roster capture; Transfer expiry comes from canonical Transfer observation validity; Bear Hunt missing metrics are never zero-filled; Recruitment history is not fabricated from `updated_at`.
6. Assistant uses typed signals and source citations without strategic inference; Communications owns delivery/idempotency only.
7. Command Overview renders its Intelligence feed only for a concrete active Alliance scope, so unscoped state is not presented as an empty factual result.

## Kingshot capability expansion — phases 13–25

Canonical contract and row-level ledger: [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md).

Phases 13–16 and 18–24 are current complete capabilities on the final containing candidate. Phase 21 reconciles the existing Territory execution implementation. The HTTP/Inertia rank matrix, tenant/Kingdom isolation, bounded-history, query-budget, privacy-safe telemetry, desktop/mobile accessibility and deterministic visual fingerprints are verified together with the repository-wide PHP, Pint, PHPStan, frontend, architecture, security, production-image, clean-database/staging and recovery gates.

Phase 17 remains blocked at `P17-01`: no verified canonical KvK identity/evidence and supported workflow profile exists. No KvK specialization was implemented.

| Phase range | Current delivery state | Remaining release evidence |
| --- | --- | --- |
| 13–16 | Complete | Acceptance matrix and containing-candidate gates green |
| 17 | Blocked by identity/evidence gate | Canonical identity, provenance and reviewed workflow dimensions before code |
| 18–20 | Complete | Acceptance matrix and containing-candidate gates green |
| 21 | Complete | Reconciliation and containing-candidate gates green |
| 22–24 | Complete | Acceptance matrix and containing-candidate gates green |
| 25 | Complete | All applicable gates green on the final containing candidate |

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

Transfer and Governor Progression Screenshot Intake are separate current complete capabilities below. They reuse these Evidence invariants but have their own typed schemas, destination owner Actions and acceptance criteria.

## Capability Extension Program delivery queue

Canonical contract: [Capability Extension Program](capability-extension-program.md).

Phase 0 is documentation-only. No application code, migration, route, frontend component or runtime configuration is part of Phase 0.

| Phase | Program state | Status | Slice | Acceptance criteria / exit condition |
| --- | --- | --- | --- | --- |
| 0 | Contract work | Complete | Reconcile `/docs/product` | Screenshot Intake stale rows reconciled; extension contract created/indexed; catalogue/gap analysis/ledger/journeys aligned; ownership/provenance documented before application changes; no runtime changes. |
| 1 | Current complete capability | Complete | Alliance Assistant `game_fact` | `AE-01`–`AE-03`, `AE-07`, `AE-09`–`AE-12`, plus `PX-*`: source-backed Progression query, immutable dataset/source/confidence citations, unknown/conflict semantics, authorization-before-retrieval, bounded UX/tests/release evidence. |
| 2 | Current complete capability | Complete | Assistant operational-self intents and safe handoffs | `AE-04`–`AE-12`, plus `PX-*`: Participation/BattlePlan/Transfer/Territory self reads are authorized/bounded; write-like requests perform zero mutation and only navigate to canonical owner workflows. |
| 3 | Current complete capability | Complete | Event Readiness | `ER-01`–`ER-12`, `EC-01`–`EC-28`, plus `PX-*`: occurrence-scoped capability-aware pre-Event readiness composed from bounded owner projections with explicit blockers/source/action links, query-budget coverage and no persisted readiness state machine. |
| 4 | Current complete capability | Complete | Event Closeout | `ER-01`–`ER-12`, `EC-01`–`EC-28`, plus `PX-*`: occurrence-scoped post-Event attendance/Rally/Results/Evidence/review/Debrief completion composed with explicit missing semantics, owner correction links and no Event Command write path. |
| 5 | Current complete capability | Complete | Kingdom Transfer Screenshot Intake | `TE-01`–`TE-12`, typed fixtures, owner commits, recovery, UX and containing-candidate gates are complete. |
| 6 | Current complete capability | Complete | Governor Progression Screenshot Intake | `GE-01`–`GE-12`, pinned normalization, append-only Roster commits, recovery, UX and containing-candidate gates are complete. |
| 7 | Current complete capability | Complete | Progression Goal Planner | `GP-*` implementation and reconciled UX/tests exist; the calculator delivery ledger remains the exact verification authority. |
| 8 | Evidence-gated extension | Complete family dispositions | Calculator evidence qualification per family | Governor Gear and Governor Charms are qualified; Hero Gear/Mastery and Troops are evidence-incomplete, Research has a source gap, and Buildings/Truegold has an evidence conflict. |
| 9 | Evidence-gated extension | Qualified families implemented; others correctly unavailable | Evidence-backed calculators | Governor Gear and Governor Charms calculators consume their pinned qualified release. No other family is exposed. |
| 10 | Current complete capability | Complete | Territory plan vs observed state | `TR-01`–`TR-08`, plus `PX-*`: immutable desired plan versus dated observed evidence, typed coordinate tolerance, explicit stale/missing semantics, no plan/map rewrite from observation. |
| 11 | Current complete capability | Complete | Intelligence change signals | `IS-01`–`IS-08`, `ICD-01`–`ICD-30`, plus `PX-*`: deterministic source-cited signals over authorized histories, typed thresholds/windows, explicit unsupported complete-source absence, no inferred strategic intent, privacy-safe telemetry, scoped consumer UX and green release evidence. |
| 12 | Current complete capability | Complete | Full reconciliation and release closeout | `PX-01`–`PX-15`: documentation, selected Evidence extensions and containing-candidate verification are reconciled. |

`Blocked pending qualification` and explicit incomplete/gap/conflict calculator-family dispositions are intentional evidence gating, not implementation defects. A future material regression reopens the affected completed row.

Phase 12 is complete. Evidence-gated calculator families intentionally retain their explicit dispositions. Phases 13–25 are complete except Phase 17, whose KvK identity/evidence gate remains correctly blocked and is separately governed by [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md).

## Calculator evidence qualification ledger

Qualification is independent by family. Current factual-reference completeness does not imply calculator qualification.

| Family | Qualification state | Runtime calculator state | Required next evidence work |
| --- | --- | --- | --- |
| Governor Gear | Qualified | Enabled for pinned qualified release | Preserve report/release/checksum/golden-fixture boundary. |
| Governor Charms | Qualified | Enabled for pinned qualified release | Preserve explicit level-zero boundary and golden fixtures. |
| Hero Gear / Mastery | Evidence incomplete | Disabled | Complete independent evidence and transition semantics before implementation. |
| Troop training / promotion | Evidence incomplete | Disabled | Establish training/promotion/modifier boundaries and independent evidence. |
| Academy / War Academy research | Source gap | Disabled | Resolve the documented table and calculation-unit/source gaps. |
| Buildings / Truegold progression | Evidence conflict | Disabled | Resolve prerequisite conflict and independent calculator evidence. |

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
8. **No parallel domains for composition.** Do not create AssistantKnowledge, EventReadiness/EventCloseout, TransferOCR/ProgressionOCR, Calculator, TerritoryReality, IntelligenceChange/ChangeDetection/Signals top-level contexts merely to compose existing owners.
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
