# Capability catalogue

Status: Current

This is the user/product view of implemented capability groups and explicitly selected extensions. The **Program state** column prevents approved future work from being described as already implemented.

Program states are defined by the [Capability Extension Program](capability-extension-program.md):

- **Current complete capability** — implemented current product behavior;
- **Selected extension** — approved implementation work with a defined owner and acceptance contract;
- **Evidence-gated extension** — implementation that asserts/calculates game truth is prohibited until its specific evidence gate passes.

| Product capability | Program state | Outcome | Architectural owner |
| --- | --- | --- | --- |
| Account security | Current complete capability | Register, authenticate, verify email, manage profile/password/MFA/recovery. | Accounts |
| Player context | Current complete capability | Own/claim Players and operate as one active game persona at a time. | GameWorld/Players; workflows coordinate cross-context effects |
| Gift Codes | Current complete capability | Preserve source history and explicit trust, prepare official redemption for current/all/failed Governors, track per-Governor outcomes, and warn before expiry. | GameWorld/GiftCodes |
| Factual Governor Progression | Current complete capability | Browse immutable, source-labelled KingShot progression releases across Heroes, gear, formations, buildings, research, Pets, Masters and discovered system caps while keeping source conflicts/unknowns visible; normalize observed Governor Heroes and pin saved loadouts to a factual release without introducing recommendations or calculators. | GameWorld/Progression owns catalogue truth; Intelligence/Roster owns observations; Operations/Rallies owns saved loadout intent |
| Alliance management | Current complete capability | Manage Alliance core/settings and tenant lifecycle. | Alliance |
| Membership and leadership | Current complete capability | Membership, invitations and R1–R5 leadership and specialist roles. | Alliance |
| Recruitment | Current complete capability | Intake, filter, preview/bulk-triage, review and convert recruitment candidates through controlled membership handoff. | Alliance |
| Alliance content | Current complete capability | Publish reviewed, revisioned and context-linked knowledge; maintain first-class canonical Alliance Rules; let active members Like/Dislike published Alliance Notices without affecting authority or ranking; and deliver testable timezone-safe recurring announcements with delivery history and selective recovery. | Alliance/Content intent and reaction state + Communications delivery + ReadModels composition |
| Kingdom governance | Current complete capability | Manage Kingdom role/governance facts for Players. | GameWorld/Governance; workflows coordinate cross-context effects |
| Kingdom Transfer Planning | Current complete capability | Plan a sourced Transfer Window, preserve Alliance participant/readiness workflow, record window-specific official Transfer Groups/Power Caps and dated Governor observations, and answer whether a Governor can transfer to a target Kingdom with explicit blockers/source/freshness. | GameWorld/KingdomTransfers |
| Territory & hive planning | Current complete capability | Build, validate, analyze, version, compare and share Alliance/Kingdom layouts using versioned KingShot map facts; plan HQs, Banners, Governor cities, Bear Traps, territory coverage, hive presets, march times and multi-Alliance positioning. | GameWorld/KingdomMaps owns map facts/rules; Operations/TerritoryPlanning owns plans/analysis; ReadModels composes editor reads |
| Events | Current complete capability | Define/schedule recurring Events and occurrences. | Operations/Events |
| Participation | Current complete capability | Registration, responses and attendance. | Operations/Participation |
| Event planning | Current complete capability | Rosters, polls, battle objectives and assignments. | Operations |
| Rallies | Current complete capability | Plan and coordinate rallies against Event occurrences. | Operations/Rallies |
| King Perks | Current complete capability | Plan/schedule King Perk appointments and King Skills with occupancy/cooldown rules. | Operations/KingPerks |
| Results | Current complete capability | Capture operational Event results and metrics. | Operations/Results |
| Bear Hunt Debrief | Current complete capability | Review one Alliance Bear Hunt run with authoritative total/Governor damage and rank, recorded Rally participation, attendance, unmatched-Governor review handoff, previous-Hunt comparison, bounded run history and personal/Alliance trends. | ReadModels/EventAnalysis composes Operations/Events, Results, Participation, Rallies and Intelligence/Evidence owner reads; no BearHunt bounded context |
| Screenshot Intake — Bear Hunt | Current complete capability | Upload Bear Hunt report evidence, classify/extract it with immutable provenance, review/correct field-level candidates, detect duplicates and commit reviewed results into Operations exactly once. | Intelligence/Evidence owns evidence, review and commit handshake; Operations/Results owns accepted Bear Hunt reports/results |
| Intelligence | Current complete capability | Ingest observations and maintain roster/contribution/event/diplomacy intelligence. | Intelligence |
| Shared intelligence | Current complete capability | Control sharing/grants and compose Kingdom intelligence views. | Intelligence + ReadModels |
| Communications | Current complete capability | Deliver reminders/notifications with preferences/retry/idempotency. | Communications |
| Platform administration | Current complete capability | Cross-tenant admin, lifecycle/retention controls, Event-type administration, privacy-safe diagnostics and audited outbox recovery. | Platform + ReadModels composition |
| Integrations | Current complete capability | Scoped API credentials, revocable external-actor pairing, idempotent Event participation adapters, and signed/retryable webhooks. | Platform/Integrations |
| Dashboards/history | Current complete capability | Compose cross-context user-facing views without changing source ownership. | ReadModels |
| Alliance Assistant — initial intents | Current complete capability | Ask bounded questions about authorized Events, the active Governor’s own roster state, published Alliance guides and Alliance observations with mandatory source/provenance labels and no direct writes or unsourced KingShot fallback. | `ReadModels/AllianceAssistant` composes owner Queries from Operations, Alliance and Intelligence; owner contexts retain all truth and writes |
| Alliance Assistant — Game Data and operational self | Current complete capability | Ask bounded source-backed `game_fact` questions plus the active Governor's own Participation, BattlePlan, authorized Transfer status and Event-attached published Territory-revision questions; recognized roster writes return navigation-only handoff with zero Assistant mutation. | `ReadModels/AllianceAssistant` composes GameWorld/Progression, GameWorld/KingdomTransfers and Operations owner Queries |
| Event Readiness & Closeout | Selected extension | Give coordinators one derived decision surface for pre-Event blockers and post-Event closeout work without persisting a parallel readiness state machine. | `ReadModels/EventManagement`/applicable ReadModels compose Operations, Alliance/Content, Communications and Intelligence/Evidence owner reads |
| Screenshot Intake — Kingdom Transfer | Selected extension | Review supported in-game Transfer screenshots and commit approved typed observations to KingdomTransfers exactly once with immutable Evidence provenance. | Intelligence/Evidence owns evidence/review/receipt; GameWorld/KingdomTransfers owns accepted transfer observations and eligibility |
| Screenshot Intake — Governor Progression | Selected extension | Review supported profile/progression screenshots, normalize them against a pinned Progression release and append approved Governor observations. | Intelligence/Evidence owns evidence/review/receipt; Intelligence/Roster owns observed Governor progression; GameWorld/Progression owns canonical identities/facts |
| Progression Goal Planner | Selected extension | Compare current observed Governor progression with a user-selected factual target/prerequisite path without inventing recommendation or unqualified cost formulas. | ReadModels composes GameWorld/Progression + Intelligence/Roster; any saved planning intent stays with an existing appropriate Operations owner |
| Calculator evidence qualification | Evidence-gated extension | Qualify numeric progression families independently using source/version/unit/reconciliation/checksum and golden-fixture requirements before calculation is allowed. | GameWorld/Progression owns factual/calculation datasets and qualification reports |
| Evidence-backed calculators | Evidence-gated extension | For qualified families only, calculate factual current-to-target resource/time/requirement deltas against an immutable dataset/calculation release with visible provenance. | GameWorld/Progression typed calculation services; ReadModels/UI present results; saved scenarios remain planning intent |
| Territory plan vs observed state | Selected extension | Compare an immutable published Territory revision with dated observed spatial evidence without rewriting either desired state or observed state. | Operations/TerritoryPlanning owns plans; Intelligence/Evidence/observations own observed evidence; GameWorld/KingdomMaps owns map facts/rules; ReadModels composes comparison |
| Intelligence change signals | Selected extension | Derive bounded, source-cited change signals from authorized observation histories for Command Overview, Kingdom Intelligence, Communications and Assistant without inventing strategic conclusions. | Applicable Intelligence owners retain observations; ReadModels derives signals; Communications owns delivery when used |

This catalogue changes when a real product outcome or approved implementation contract changes, not for internal class/file movement.

## Factual Governor Progression product contract

Factual Governor Progression establishes a data-first foundation before any recommendation or calculator work. The current delivered contract requires:

- immutable `GameWorld/Progression` releases with deterministic checksums and a source registry;
- source authority tiers, observation/version boundaries, explicit conflict states and per-family dispositions;
- current Hero identity coverage across all discovered generations/rarities/classes plus sourced progression-system summaries;
- sourced Hero shard/Widget/Mastery progression plus Hero/Governor Gear and Charm facts, with current conflicts resolved only through documented source precedence and all superseded/unresolved claims preserved rather than guessed away;
- named troop formations stored only as community conventions with mode/scope and no best/recommended score;
- canonicalized building, troop, Academy/War Academy, Alliance Tech, Pet, Master and additional progression families at every selected inspectable row, with genuinely unpublished or disputed values explicitly dispositioned;
- a factual read-only Progression Library showing dataset version, checksum, sources, conflicts and completeness rather than hiding uncertainty;
- append-only `Intelligence/Roster` Hero observations that pin a progression dataset identity/checksum when normalized;
- `Operations/Rallies` saved formation/loadout intent that stores canonical Hero IDs and pins the factual release used to interpret them;
- the pre-existing calculator evidence gate remaining closed for any numeric family that has not independently satisfied its stricter source/reconciliation criteria.

The canonical source/evidence policy and delivered acceptance criteria live in [Factual Governor Progression](factual-governor-progression.md). New Goal Planner/calculator work is governed by the [Capability Extension Program](capability-extension-program.md), and does not retroactively make the factual reference capability incomplete.

## Alliance Content game-parity product contract

The delivered game-parity Content slice keeps ownership inside `Alliance/Content` while making two familiar KingShot concepts explicit:

- one canonical, first-class **Alliance Rules** document at the reserved Alliance-local identity `alliance-rules`, managed with existing Content authority, revisions, audit and outbox behavior rather than a parallel Rules store;
- one lightweight **Like** or **Dislike** reaction per active Governor and published Alliance Notice, with switching/removal and idempotent no-op behavior;
- reaction authorization based on active Alliance membership only, explicitly independent from `ContentManage`, publication, editing, archiving or broadcast authority;
- member reads limited to Like count, Dislike count and the current Governor's reaction;
- an anti-ranking contract: reactions never influence Notice ordering, visibility, prominence, moderation, recommendations, reputation, notification delivery or popularity ranking.

The canonical requirements and completed delivery record live in [Alliance Content game parity](alliance-content-game-parity.md).

## Kingdom Transfer Planning product contract

Kingdom Transfer Planning is a delivered `GameWorld/KingdomTransfers` capability, not a generic workflow. It preserves participant/readiness/blocker/completion behavior while adding:

- sourced Transfer Windows with explicit phase boundaries;
- official Transfer Groups whose Kingdom membership is window-specific;
- sourced target-Kingdom Power Caps and Leading/Ordinary classification;
- append-only Governor Power, Transfer Score, Transfer Pass, invitation and in-game eligibility observations with observation/validity boundaries;
- deterministic per-requirement eligibility outcomes rather than a stored boolean;
- a strict rule that stale, missing, conflicting or non-authoritative evidence yields `needs_verification`, never `eligible_now`;
- visible provenance/freshness and next actions in the manager-facing participant UX;
- an evidence gate for the unpublished Transfer Score → Transfer Pass formula and other unpublished in-game rules;
- a terminology correction that reserves **Transfer Group** for the official game concept and uses **Transfer Cohorts** for Alliance planning groups.

The canonical contract and completed delivery record live in [Kingdom Transfer Planning](kingdom-transfer-planning.md). The selected Transfer Screenshot Intake extension feeds reviewed observations through this existing owner rather than creating a second transfer model.

## Bear Hunt Debrief product contract

Bear Hunt Debrief is a delivered read-side composition over existing capabilities, not a new BearHunt domain. Its complete user outcome includes:

- one `EventOccurrence` as the canonical Hunt/run identity;
- Operations/Results-owned total damage plus Governor damage/rank and accepted-report counts;
- Participation-owned attendance that remains independent from whether damage exists;
- Rallies-owned actual participation counts only when an explicit recorded participation decision exists, preserving the difference between zero and not recorded;
- manager-only unresolved Governor observations from Intelligence/Evidence with a deep link back to Screenshot Intake;
- the immediately preceding completed Bear Hunt for the same historical Alliance target, with null/zero-safe Alliance and active-Governor comparisons;
- bounded personal and Alliance trends plus navigable run history without materializing a second statistics store;
- active-Player and concrete-Alliance authorization before current, historical or Evidence facts are exposed;
- a read-only Debrief boundary whose corrective mutations continue through the owning Results, Participation, Rallies or Evidence actions;
- privacy-safe read telemetry, mobile-first Governor presentation, accessible textual trend equivalents, localization and deterministic desktop/mobile visual regression.

The canonical implementation and acceptance contract lives in [Bear Hunt Debrief](bear-hunt-debrief.md). `EventAnalysis` owns composition only and does not become the writer for any displayed fact.

## Screenshot Intake product contract

The delivered Bear Hunt Screenshot Intake is an evidence-to-domain workflow rather than a generic OCR utility. It includes:

- private scanned/checksummed screenshot storage with immutable original provenance;
- versioned classification and extraction attempts;
- field-level raw text, normalized values, confidence and extraction provenance;
- manual correction/exclusion without rewriting machine history;
- Player resolution through supported owner reads without creating identity from extracted text;
- exact, visual and semantic duplicate detection that does not disclose cross-tenant evidence;
- review-first commit preview;
- a crash-safe/idempotent Evidence commit handshake that passes scalar reviewed values into `Operations/Results` through the destination owner Action;
- an Operations-owned Bear Hunt report ledger that recomputes result aggregates instead of blindly incrementing them;
- destination receipts and retry recovery when acknowledgement is interrupted;
- evidence deletion/retention that never silently deletes accepted Operations results;
- accessible, responsive, localized lifecycle UX and privacy-safe diagnostics.

The complete Bear Hunt contract lives in [Screenshot Intake](screenshot-intake.md). New Transfer and Governor Progression screenshot types are **Selected extensions** governed by [Capability Extension Program](capability-extension-program.md). They reuse the Evidence lifecycle but have separate typed schemas, destination owner Actions, idempotency identities and acceptance criteria.

## Territory & Hive Planner product contract

The delivered capability is one complete product, not disconnected map widgets. It includes:

- versioned KingShot map datasets with provenance, observation date, schema version and checksum;
- one canonical coordinate/geometry vocabulary used by server validation and browser preview;
- saved Alliance and Kingdom-scoped plans with optimistic revision protection;
- planned HQs, Banners, Governor cities, Bear Traps and plan-local external Alliance/Governor references;
- server-authoritative collision, exclusion-zone, map-boundary, footprint, cap and territory-connectivity validation;
- explicit separation between invalid game-rule violations, planning warnings and optimization suggestions;
- pan/zoom, select, move, delete, duplicate, box-select, grouping, ungrouping, rotation, keyboard movement and undo/redo;
- territory coverage rendering and analysis;
- Bear-hive presets/generators and editable generated layouts;
- march-distance/time analysis with visible, versioned assumptions where no authoritative game value exists;
- multi-Alliance Kingdom planning without requiring external Alliances or Governors to become application records;
- immutable published revisions, comparison, clone/restore, schema-versioned JSON import/export, PNG/SVG image export and shareable artifacts;
- keyboard/mobile/accessibility parity through synchronized DOM controls rather than a canvas-only workflow;
- integration with applicable Event workflows through immutable plan-revision references rather than moving spatial state into BattlePlans.

Community projects are discovery evidence, not authoritative KingShot truth. A community-derived coordinate, footprint, placement rule or march constant cannot silently become product logic; it must carry the provenance/confidence contract defined by GameWorld/KingdomMaps. The selected observed-state reconciliation extension does not alter this ownership.

## Alliance Assistant product contract

The delivered Alliance Assistant is a constrained read/composition capability. It answers only from current data the active Governor is already authorized to view, cites every substantive source, preserves `operational_fact`, `game_fact`, `alliance_strategy` and `observation` provenance, and never becomes an alternate writer. It uses deterministic bounded intent resolution with no external model provider or persisted conversation.

The delivered GameWorld/operational-self extension adds source-backed Progression facts; the active Governor's own Participation and BattlePlan state; authorized self transfer assessment; immutable Event-attached published Territory revisions; and navigation-only handoff for recognized roster write requests. It preserves authorization-before-retrieval, narrow owner projections, explicit unknown/conflicting GameWorld states, server-created citations and zero Assistant mutation. Generic KingShot questions remain unsupported rather than falling back to model memory.

The base requirements live in [Alliance Assistant](alliance-assistant.md), the completed extension contract and ledger live in [Alliance Assistant — GameWorld Extension](alliance-assistant-gameworld-extension.md), and global completion state lives in the [capability delivery ledger](capability-delivery-ledger.md).

## Capability Extension Program product contract

The extension program deliberately connects existing owners rather than creating new top-level domains. Its canonical ownership/provenance contract requires:

- GameWorld owners remain the source of immutable/sourced game facts and deterministic transfer/map rules;
- Operations owners remain the source of Event, Participation, roster, BattlePlan, Rally, Results and Territory planning state;
- Alliance/Content remains the source of Alliance-authored strategy;
- Intelligence/Evidence owns source artifacts, extraction/review/duplicate/commit receipts but never destination truth;
- Intelligence/Roster owns observed Governor progression history;
- ReadModels own cross-context composition only;
- Assistant owns interpretation/composition only and performs no direct writes;
- missing/stale/conflicting/unknown values never silently satisfy readiness, eligibility, calculation or reconciliation;
- calculator implementation is qualified independently by numeric family and cannot start merely because the factual Progression library is complete.

The complete selected-extension outcomes, acceptance criteria, evidence gates, delivery order and Phase 0 exit criteria live in [Capability Extension Program](capability-extension-program.md).

## Assurance contract

Every capability and selected extension carries the same five-part release obligation; a row is not considered delivered without it.

| Obligation | Authoritative evidence |
| --- | --- |
| Owner | The architectural owner in this catalogue and the canonical [capability map](../architecture/capability-map.md). Owner contexts retain writes; cross-context pages use read models or workflows. |
| Permission model | Active-Player and concrete-resource authorization through owner policies and services, indexed by the [permission reference](../reference/permissions.md). Public/read-only exceptions are explicit contracts, never implicit fallbacks. |
| Tests | Owner behavior, authorization, idempotency, architecture, frontend and applicable visual coverage described by the [testing contract](../codebase/testing.md). |
| Observability | Audit records for material mutations, correlation-aware request/job logging, outbox and delivery state, and the operational signals defined by [observability](../operations/observability.md). |
| Recovery | User correction or cancellation where the domain permits it, bounded retry/replay for external effects, operator diagnostics, and the applicable [recovery runbooks](../operations/recovery/README.md). |

Capability-specific product/reference pages may strengthen these obligations but may not omit them.