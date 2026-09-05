# Capability completeness plan

Status: Current — 2026-09-04

This document identifies what is already complete, what has been selected for the next implementation program, and what remains evidence-gated. It is not permission to describe selected work as shipped.

The canonical extension requirements, ownership/provenance rules and acceptance criteria live in [Capability Extension Program](capability-extension-program.md).

## State taxonomy

| State | Meaning | Implementation rule |
| --- | --- | --- |
| **Current complete capability** | Implemented current product behavior with its existing acceptance/release evidence. | Keep supported; a new extension does not reopen it unless a regression invalidates its contract. |
| **Selected extension** | Approved cross-capability work with a defined owner, product outcome and acceptance criteria. | Implement continuously through its delivery-ledger row; do not claim the outcome before closeout. |
| **Evidence-gated extension** | Demand is recognized, but implementation that calculates/asserts numeric or unpublished game truth is prohibited until a specific evidence gate passes. | Evidence qualification may proceed; runtime calculation remains disabled for unqualified families/rules. |

Do not use `planned`, `MVP`, `partial`, or `future enhancement` to obscure these states.

## Discovery sources

Community/open-source projects remain discovery evidence only. They can reveal useful product workflows but do not become authoritative game truth by being implemented elsewhere.

- [Bleezy-D/Alliance-Layout-Planner](https://github.com/Bleezy-D/Alliance-Layout-Planner) — multi-Alliance layout planning, HQs, Banners, Governor cities, Bear Traps, territory coverage, map structures/no-build zones, march times, hive presets, grouping/rotation, saved layouts and image export. Existing Territory data/rules remain provenance-gated.
- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — community workflow discovery for Alliance monitoring, transfer planning, timers, calculators, recruitment and multi-channel notifications; it is not evidence for a Kingshot event identity or mechanic.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — related player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the official Century Games Gift Code Center — gift-code workflow discovery and official redemption boundary.

External projects may not silently supply a progression formula, transfer rule, coordinate/footprint, march constant, eligibility decision or other game fact. Source/version/observation/confidence rules of the owning GameWorld capability apply.

## Current complete capabilities

The product already has governed workflows across account security, Player context, Alliance membership/leadership, recruitment, Alliance Content, Kingdom governance, Events, Participation, rosters, polls, BattlePlans, Rallies, King Perks, Results, Intelligence, Communications, platform administration, integrations, Gift Codes, Territory & Hive planning, Kingdom Transfer Planning, Factual Governor Progression, Screenshot Intake for Bear Hunt, Bear Hunt Debrief, Alliance Assistant, Event Readiness & Closeout, Territory plan versus observed state and Intelligence Change Detection.

Gift Codes now use a fresh-schema canonical trust model: append-only observations, platform-approved sources, revisioned derived trust/expiry, evidence-gated reward/applicability, MFA-protected moderation, guided owned-Governor handoff, revision-aware lifecycle delivery, bounded source ingestion health, cursor catalogue/API reads and versioned webhooks. The official Century Games center remains the provider boundary; undocumented redemption automation and unsupported Kingshot reward/eligibility claims remain explicitly out of scope.

### Account security — current complete

Accounts now treats the Kingshot Alliance User as the permanent application identity and Password, Google and Passkeys as attached sign-in methods. The fresh canonical schema has no `authentication_type`; one Accounts-owned policy derives actual method availability and blocks removal of the final usable method.

Google attachment is explicit, recent-authenticated and keyed by stable provider subject rather than email. Passwords may be added, changed or removed when policy allows. Passkeys use the maintained first-party Laravel/WebAuthn implementation with opaque user handles and required user verification. Recent authentication is method-agnostic, TOTP remains MFA rather than account identity, and user-verifying passkeys do not receive a redundant TOTP challenge. Security Center, sessions, Security Activity, account email and lifecycle invalidation remain within their existing Accounts/Communications/Platform ownership boundaries.

Account merging, email-based identity consolidation, official Kingshot game authentication and game credentials remain unsupported.

### Communications recipient delivery — current complete

The Communications capability uses one logical `NotificationMessage` plus zero or more concrete `NotificationDelivery` routes. Recipient policy resolves account defaults and Governor overrides across In App, Discord, Telegram, Web Push and Accounts-owned verified email, with quiet hours, recipient-controlled urgent bypass, temporary mute and immediate/hourly/daily digest timing.

Multiple named stored endpoints are independently testable, pausable and health-tracked. Provider workers recheck current endpoint state, preferences, Governor ownership and verified email before send; immediate and digest processing are both bounded, idempotent and scheduled every minute with overlap protection. Web Push destination/key/VAPID handling, email transport readiness, safe relative action URLs, cursor inbox reads, message-owned read/archive state, bounded bulk operations and privacy-safe platform diagnostics are covered by the Communications acceptance suite.

Immutable implementation candidate `f880cb40014b2ef5236facaf65ac2b68f90fd5ae` passed CI, Architecture V3 Verification, Intelligence Verification, King Perks Verification, Visual Regression, CodeQL and Dependency Review. The [Communications delivery ledger](communications-recipient-delivery-ledger.md) is closed and the capability is current complete.

### Alliance Capability Expansion — current complete

The delivered Alliance expansion closes the officer-facing settings gap and extends existing owners rather than creating a new Alliance domain. `Alliance/Lifecycle` owns application name/slug/language/timezone settings; `Alliance/Access` owns bounded specialist-role definition and delegation; `Alliance/Membership` retains membership, R1–R5 and leadership writes; `Alliance/Recruitment` owns private Alliance-local re-entry controls.

Private roster screenshots remain Evidence artifacts until a human-reviewed revision is approved. Exactly-once commit appends accepted `Intelligence/Roster` observations, and `ReadModels/AllianceGovernance` derives reconciliation and audit history without changing Membership. Absence becomes a missing-member observation only when the reviewer explicitly confirms that the screenshot is a complete roster. Bulk rank/role commit rechecks current authority and single-owner invariants. Alliance Assistant exposes only authorized factual settings/history/reconciliation and returns navigation handoff for writes.

### Alliance Assistant — current complete

The delivered Assistant is a bounded authorization-aware read surface for trusted Events, the active Governor's self-roster/Participation state, the active Governor's BattlePlan assignments, published Alliance Content, authorized Intelligence observations, source-backed Progression facts, authorized self transfer assessment and immutable Event-attached published Territory revisions. It has server-owned citations/provenance, explicit ambiguity/missing/unknown/conflicting states, zero direct mutation and no model-knowledge fallback.

Recognized roster write attempts return navigation-only handoff into the normal owner workflow; unknown writes remain unsupported. The completed [Alliance Assistant GameWorld extension](alliance-assistant-gameworld-extension.md) preserves authorization-before-retrieval and narrow owner projections rather than importing broad management views into Assistant composition.

### Factual Governor Progression — current complete

The current immutable Progression release provides the source-backed factual corpus across discovered Heroes, Gear, Charms, formations, buildings, troop tiers, Academy/War Academy, Alliance Technology, Pets, Masters and additional structured families, preserving source/conflict/unknown semantics.

The factual reference being complete does **not** mean numeric calculators are qualified. Calculator eligibility remains independently evidence-gated by family.

### Kingdom Transfer Planning — current complete

The delivered capability preserves Alliance participant/readiness/blocker/completion workflow while adding sourced Transfer Windows, official window-scoped Transfer Groups, target Power Caps/classification, append-only Governor observations, deterministic eligibility requirements, stale/conflicting/unknown handling, visible provenance/freshness and separate view/manage authority.

The unpublished Transfer Pass formula remains evidence-gated. Observed required-pass counts are supported without inventing the formula.

### Bear Hunt Debrief — current complete

The delivered `EventAnalysis` composition uses existing Events, Results, Participation, Rallies and Intelligence/Evidence owners for authoritative run identity, damage/rank, attendance, recorded Rally participation, unresolved review state, previous-run comparison and bounded trends/history. It does not create a BearHunt bounded context or duplicate statistics store.

### Alliance Territory & Hive Planner — current complete

The delivered planner provides versioned/checksummed map datasets, shared geometry parity, saved Alliance/Kingdom plans, accessible editing, server-authoritative validation, coverage, hive generators, march/layout analysis, multi-Alliance planning, immutable revisions/comparison, JSON/PNG/SVG interchange and immutable Event positioning references.

The delivered observed-state reconciliation extension compares desired plan state with dated observations without making either source mutable.

### Screenshot Intake — Bear Hunt current complete

The canonical [Screenshot Intake](screenshot-intake.md) contract closes all 15 Bear Hunt phases: secure upload, classification, extraction, field confidence/history, review, exact/visual/semantic duplicate handling, commit preview, scalar cross-context commit, Operations report ledger/recomputation, crash-safe retry/receipt recovery, deletion/redaction/retention, observability, accessibility/localization/visual regression and final audit.

Any global ledger row still showing phases 3, 4, 7, 11 or 12 as `In progress` was stale documentation and is reconciled to `Complete` in Phase 0. Transfer and Governor Progression Evidence implementations now exist but remain **Selected extensions** until their containing immutable candidate passes the required gates.

### Event Readiness & Closeout — current complete

The delivered Event Command is an occurrence-scoped `ReadModels/EventManagement` composition over existing owners. It derives `planning | needs_attention | ready | active | closeout_required | complete`, preserves cancellation and explicit unknown/not-applicable semantics, exposes canonical owner provenance/handoffs, performs no domain writes, and is verified through query-budget, authorization/isolation, accessibility/localization and deterministic desktop/mobile visual coverage.

### Intelligence Change Detection — current complete

The delivered [Intelligence Change Detection](intelligence-change-detection.md) capability derives typed, deterministic, source-cited change/staleness/expiry/trend signals from authorized owner histories without creating another intelligence database or persisted signal truth. Command Overview, Kingdom Intelligence, Alliance Assistant and Communications consume the same recomputable signal contract while the underlying Intelligence, GameWorld, Operations and Alliance owners retain their facts.

Signals preserve factual discipline: differences between observations do not become strategic intent, threat or quality judgments. Ordinary Alliance observation history does not emit disappearance/reappearance because the current source model does not prove exhaustive absence; that subtype remains explicitly unsupported until a complete-source presence/absence contract exists. The dashboard feed also requires a concrete active Alliance scope, so unscoped state is not presented as an empty factual result.

Implementation candidate `e5c492f9391431ab68e1b2ca215038f448e5539d` passed CI, Intelligence Verification, Architecture V3 Verification, Visual Regression, CodeQL and Dependency Review, including staging, backup/restore and production-image scanning.

## Reconciled extension outcomes

| Priority/order | Selected extension | User outcome | Canonical owners | Primary guardrail |
| --- | --- | --- | --- | --- |
| 1–10 | Alliance Capability Expansion — implementation complete, final verification in progress | Manage application Alliance settings/roles, governance history, roster evidence/reconciliation, bulk rank/role and Recruitment re-entry through existing owners. | Alliance owners + Intelligence/Evidence/Roster + ReadModels/AllianceGovernance | Active Player authority; human-reviewed evidence never auto-mutates Membership. |
| 5 | Kingdom Transfer Screenshot Intake — current complete | Use the verified supported-screenshot review and exactly-once destination workflow. | Intelligence/Evidence + GameWorld/KingdomTransfers | Evidence owns provenance; KingdomTransfers owns observations/eligibility. |
| 6 | Governor Progression Screenshot Intake — current complete | Use the verified pinned-release normalization and append-only Governor observation workflow. | Intelligence/Evidence + Intelligence/Roster + GameWorld/Progression | OCR cannot create identity or alter catalogue truth. |
| 13–25 | Kingshot Capability Expansion Program — complete except evidence-gated KvK | Use the verified Event profile, Rally, factual member, Bear Hunt, Transfer, Intelligence, Territory, Command, Brief and Assistant compositions; KvK remains disabled pending evidence. | Existing owners + ReadModels composition | Mandatory named-Event identity/evidence gate; no parallel composition domains or unsupported mechanics. |

Phases 5–12 use the detailed acceptance criteria in [Capability Extension Program](capability-extension-program.md). Phases 13–25 use [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md). A selected extension is not promoted to current complete until its global delivery-ledger row and containing-candidate evidence are reconciled.

## Evidence-gated extensions

### Calculator evidence qualification

Calculator demand is accepted, but qualification is independent by family. Evidence work may proceed for:

- Governor Gear;
- Governor Charms;
- Hero Gear/Mastery;
- troop training/promotion;
- Academy/War Academy research;
- buildings/Truegold progression.

A family is not qualified until all of these are true:

1. every row/input has source URI/source ID, source label, `observed_at`, game/version boundary when available and unit;
2. values come from an official inspectable table or satisfy the documented independent-source + reviewed in-game evidence standard;
3. disagreements, regional/version differences, unlock conditions and unknown values are explicit;
4. calculator datasets are immutable, schema-versioned, checksummed and retained when superseded;
5. calculation code consumes typed owner data; Vue/controllers contain no hard-coded formulas or cost tables;
6. golden fixtures cover relevant step/range/promotion/bonus/rounding/unavailable boundaries;
7. UI exposes dataset version/source/observation boundary, assumptions and correction path;
8. saved scenarios pin dataset/checksum/calculation version;
9. qualification state is explicit and machine-readable;
10. qualifying one family never unlocks another.

### Evidence-backed calculators

Calculator implementation is permitted only for a family whose qualification report is `qualified`. Unknown/conflicting inputs produce unavailable/conflicting results, not guessed totals. Calculators answer the user's selected scenario and are not recommendations.

The detailed `CE-*` and `CI-*` acceptance criteria live in [Capability Extension Program](capability-extension-program.md).

## Capability-extension ownership map

The extension program intentionally reuses current architecture:

- `GameWorld/Progression` — immutable factual progression datasets, source/conflict metadata, per-family calculator qualification/calculation data;
- `GameWorld/KingdomTransfers` — accepted transfer observations and derived eligibility;
- `GameWorld/KingdomMaps` — immutable map datasets and sourced placement rules;
- `Operations/Events` — Event/occurrence identity/schedule;
- `Operations/Participation` — registration/response/waitlist/attendance;
- `Operations/Rosters` — roster state;
- `Operations/BattlePlans` — objectives/assignments;
- `Operations/Rallies` — Rally planning/actual recorded participation;
- `Operations/Results` — Event results/Bear Hunt accepted reports;
- `Operations/TerritoryPlanning` — desired spatial planning intent/revisions;
- `Alliance/Content` — Alliance-authored Event strategy/guidance;
- `Intelligence/Evidence` — private source artifacts, extraction/review/duplicates/commit attempts/receipts/retention;
- `Intelligence/Roster` — observed Governor progression history;
- other Intelligence owners — observation histories consumed by change signals;
- `Communications` — logical inbox state, recipient routing/preferences, concrete destinations, digest/provider delivery, retry, endpoint health and delivery diagnostics; source contexts retain notification meaning and Accounts retains verified email identity;
- `ReadModels` — authorized cross-context composition only;
- `ReadModels/AllianceAssistant` — bounded interpretation/evidence composition only.

No delivered extension establishes a new top-level bounded context merely to compose these owners.

## Data/provenance taxonomy

The implementation must keep the following distinct:

- **Game fact/data** — source-backed versioned GameWorld truth;
- **Operational fact** — application state owned by Operations/other domain owner;
- **Alliance strategy** — Alliance-authored guidance/plan;
- **Observation** — dated observed intelligence or Governor state;
- **Evidence** — source artifact and review provenance supporting a destination command/observation;
- **Planning intent** — user/Alliance-selected desired state or goal;
- **Derived signal/readiness/comparison** — recomputable read-side interpretation over owner state, never a second authoritative store.

Evidence cannot silently become game truth. Planning intent cannot silently become observation. Observation cannot silently become strategy conclusion. Missing/stale/conflicting facts cannot silently satisfy readiness, eligibility, calculation or comparison.

## Implementation sequence

| Phase | State | Slice |
| --- | --- | --- |
| 0 | Complete | Reconcile `/docs/product`, ownership/provenance and global delivery ledger |
| 1 | Complete | Alliance Assistant `game_fact` |
| 2 | Complete | Assistant operational-self intents and safe owner-workflow handoffs |
| 3 | Complete | Event Readiness |
| 4 | Complete | Event Closeout |
| 5 | Complete | Kingdom Transfer Screenshot Intake |
| 6 | Complete | Governor Progression Screenshot Intake |
| 7 | Complete | Progression Goal Planner |
| 8 | Complete family dispositions | Governor Gear/Charms qualified; remaining families explicitly incomplete/gap/conflict |
| 9 | Partially enabled by independent family gate | Governor Gear/Charms calculators only; remaining families unavailable |
| 10 | Complete | Territory observed-state reconciliation |
| 11 | Complete | Intelligence change signals |
| 12 | Complete | Full reconciliation and release closeout |
| 13–25 | Complete except Phase 17 identity/evidence gate | [Kingshot Capability Expansion Program](kingshot-capability-expansion-program.md) |

## Engineering standards for every selected extension

1. Owner contexts keep write semantics; cross-context pages live in `app/ReadModels` where composition is necessary.
2. Public owner write Actions accept scalar IDs/value objects and never foreign Eloquent models.
3. Authorization occurs before retrieval; protected writes reacquire current active-Player/concrete-scope authority inside the owner boundary.
4. Evidence machine output never writes destination truth without the documented human review/typed commit boundary.
5. Rules, formulas, normalization, tolerance and thresholds live in typed backend/domain services/configuration with tests, not Vue/controllers/jobs/bots.
6. External/community data is source-labelled, observation/version bounded and checksummed before it can become factual GameWorld input.
7. Missing, stale, unknown, unsupported and conflicting states have explicit UI/API semantics.
8. Every material page is responsive, keyboard/screen-reader usable and localized; visual regression covers selected journeys where repository policy requires it.
9. `/docs` changes in the same implementation slice whenever ownership, API/integration, operational behavior or user journey changes.
10. No compatibility shims, duplicate schemas, dual reads/writes or temporary legacy names are retained because the application is not deployed.
11. PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review, production image/container scanning, staging/clean database and backup/restore gates must pass as applicable before a slice closes.

## Phase 0 reconciliation result

Phase 0 established documentation truth before application code changed.

The completed documentation outcome was:

- Screenshot Intake global ledger status reconciled to the canonical completed contract;
- a canonical extension-program document with acceptance criteria and ownership/provenance rules;
- catalogue rows that clearly separate delivered behavior from selected/evidence-gated work;
- this gap analysis using the three-state taxonomy;
- global delivery-ledger rows for every extension phase;
- user journeys for extension outcomes, with delivered journeys promoted as their implementation rows close.

## Gift Code Redemption Workspace & Personalization

The completed Gift Code foundation is extended with account-personal actionable state and persistent many-code/many-Governor redemption sessions. Session items are orchestration only: current Player ownership, canonical Gift Code trust/expiry, qualified applicability, terminal redemption state and retry timing are re-resolved server-side, while `gift_code_redemptions` remains the authoritative Governor outcome ledger. Pin/snooze/dismiss/reminder state never mutates global catalogue truth.

Communications receives GiftCode-owned logical notification intent and retains endpoint, preference, quiet-hour, digest, provider retry and delivery ownership. Privacy-gated redemption signals remain observational and cannot establish canonical validity/applicability. Alliance coverage is aggregate and permission-gated; contributor projections cannot elevate community evidence to official authority. Signed source webhook ingestion feeds the same approved-source evidence path with replay protection and does not create a second trust path. The Century Games redemption centre remains the provider boundary; undocumented redemption automation remains out of scope.

Canonical contract: [Gift Code Redemption Workspace & Personalization](gift-code-redemption-workspace.md), [acceptance matrix](gift-code-redemption-workspace-acceptance.md), and [delivery ledger](gift-code-redemption-workspace-delivery-ledger.md).

