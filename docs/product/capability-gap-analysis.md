# Capability completeness plan

Status: Current — 2026-08-24

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
- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — alliance member monitoring, transfer planning, Crazy Joe guidance, Bear Hunt timers, calculators, recruitment, and multi-channel notifications.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — related player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the official Century Games Gift Code Center — gift-code workflow discovery and official redemption boundary.

External projects may not silently supply a progression formula, transfer rule, coordinate/footprint, march constant, eligibility decision or other game fact. Source/version/observation/confidence rules of the owning GameWorld capability apply.

## Current complete capabilities

The product already has governed workflows across account security, Player context, Alliance membership/leadership, recruitment, Alliance Content, Kingdom governance, Events, Participation, rosters, polls, BattlePlans, Rallies, King Perks, Results, Intelligence, Communications, platform administration, integrations, Gift Codes, Territory & Hive planning, Kingdom Transfer Planning, Factual Governor Progression, Screenshot Intake for Bear Hunt, Bear Hunt Debrief and Alliance Assistant.

### Alliance Assistant — current complete

The delivered Assistant is a bounded authorization-aware read surface for trusted Events, the active Governor's self-roster state, published Alliance Content and authorized Intelligence observations. It has server-owned citations/provenance, explicit ambiguity/missing states, zero direct mutation and no model-knowledge fallback.

`game_fact` is a reserved provenance classification, not a delivered generic intent. The new `game_fact`/operational-self work is therefore a **Selected extension**, not a correction to the completed first release.

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

The selected observed-state reconciliation work is an extension that compares desired plan state with dated observations; it does not make the planner incomplete.

### Screenshot Intake — Bear Hunt current complete

The canonical [Screenshot Intake](screenshot-intake.md) contract closes all 15 Bear Hunt phases: secure upload, classification, extraction, field confidence/history, review, exact/visual/semantic duplicate handling, commit preview, scalar cross-context commit, Operations report ledger/recomputation, crash-safe retry/receipt recovery, deletion/redaction/retention, observability, accessibility/localization/visual regression and final audit.

Any global ledger row still showing phases 3, 4, 7, 11 or 12 as `In progress` was stale documentation and is reconciled to `Complete` in Phase 0. The new Transfer and Governor Progression evidence work is represented as separate **Selected extensions** rather than reopening the Bear Hunt delivery program.

## Selected extensions

| Priority/order | Selected extension | User outcome | Canonical owners | Primary guardrail |
| --- | --- | --- | --- | --- |
| 1 | Alliance Assistant `game_fact` | Ask bounded source-backed progression questions with dataset/source/confidence citations. | GameWorld/Progression + ReadModels/AllianceAssistant | No model-memory fallback or copied knowledge store. |
| 2 | Assistant operational-self + handoffs | Ask self Participation, BattlePlan, Transfer and applicable published Territory questions; write-like requests can navigate to canonical owner workflows. | Operations owners, GameWorld/KingdomTransfers, Operations/TerritoryPlanning + ReadModels/AllianceAssistant | Self-only/private scope; zero Assistant mutation. |
| 3 | Event Readiness | One pre-Event view of schedule, registration, roster, BattlePlan, strategy, Territory, Communications and Rally blockers. | Existing Operations/Alliance/Communications owners + ReadModels/EventManagement | Derived composition only; no persisted readiness boolean/state machine. |
| 4 | Event Closeout | One post-Event view of attendance/Rallies/Results/Evidence/review/Debrief work still required. | Operations + Intelligence/Evidence + EventAnalysis/ReadModels | Missing is not complete; owner workflows retain every correction/write. |
| 5 | Kingdom Transfer Screenshot Intake | Review supported in-game Transfer screenshots and commit approved observations exactly once. | Intelligence/Evidence + GameWorld/KingdomTransfers | Evidence owns provenance; KingdomTransfers owns observations/eligibility. |
| 6 | Governor Progression Screenshot Intake | Review profile/progression screenshots, normalize against a pinned Progression release and append Governor observations. | Intelligence/Evidence + Intelligence/Roster + GameWorld/Progression | OCR cannot create identity or alter catalogue truth. |
| 7 | Progression Goal Planner | Compare authorized observed current state with a user-selected factual target/prerequisite path. | ReadModels composing Intelligence/Roster + GameWorld/Progression | No recommendation semantics or unqualified resource totals. |
| 10 | Territory plan vs observed state | Compare immutable desired-state plan revision with dated observed spatial evidence. | Operations/TerritoryPlanning + Intelligence/Evidence/observations + GameWorld/KingdomMaps + ReadModels | Observation does not rewrite plan/map truth; plan does not rewrite observation. |
| 11 | Intelligence change signals | Derive source-cited deterministic change signals from existing observation histories. | Applicable Intelligence owners + ReadModels; Communications only for delivery | No inferred strategic intent; signals remain observation-derived. |

All selected extensions use the detailed acceptance criteria in [Capability Extension Program](capability-extension-program.md). A selected extension remains unimplemented until its global delivery-ledger row is complete.

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
- `Communications` — provider delivery/preferences/retries;
- `ReadModels` — authorized cross-context composition only;
- `ReadModels/AllianceAssistant` — bounded interpretation/evidence composition only.

No selected extension establishes a new top-level bounded context merely to compose these owners.

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
| 0 | Complete in this documentation branch | Reconcile `/docs/product`, ownership/provenance and global delivery ledger |
| 1 | Selected extension | Alliance Assistant `game_fact` |
| 2 | Selected extension | Assistant operational-self intents and safe owner-workflow handoffs |
| 3 | Selected extension | Event Readiness |
| 4 | Selected extension | Event Closeout |
| 5 | Selected extension | Kingdom Transfer Screenshot Intake |
| 6 | Selected extension | Governor Progression Screenshot Intake |
| 7 | Selected extension | Progression Goal Planner |
| 8 | Evidence-gated extension | Calculator evidence qualification per family |
| 9 | Evidence-gated extension | Calculators for qualified families only |
| 10 | Selected extension | Territory observed-state reconciliation |
| 11 | Selected extension | Intelligence change signals |
| 12 | Selected extension | Full reconciliation and release closeout |

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

Phase 0 establishes documentation truth only. It must not change application code, migrations, routes, frontend components or runtime configuration.

The completed documentation outcome is:

- Screenshot Intake global ledger status reconciled to the canonical completed contract;
- a canonical extension-program document with acceptance criteria and ownership/provenance rules;
- catalogue rows that clearly separate delivered behavior from selected/evidence-gated work;
- this gap analysis using the three-state taxonomy;
- global delivery-ledger rows for every extension phase;
- user journeys for the selected extension outcomes, explicitly labelled as contract journeys until implemented.
