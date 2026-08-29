# Kingshot Capability Expansion Program

Status: Implementation complete through Phase 24; release verification open — 2026-08-29

## Outcome and non-negotiable boundaries

Phases 13–25 turn the existing GameWorld, Operations, Alliance, Intelligence, Communications, Platform and ReadModels capabilities into a more complete Kingshot officer experience. They do not create parallel Bear Hunt, KvK, Rally Planning, Transfer Campaign, Intelligence Timeline, Territory Execution, Alliance Command or Officer Brief domains.

Owner contexts remain authoritative. `app/ReadModels/*` may authorize and compose bounded projections, but it does not persist recomputable readiness, attention, timeline, campaign or brief state. Communications owns preferences, delivery, retry and receipt state only. Evidence owns artifacts, extraction, review and commit receipts; destination owners reauthorize and own accepted meaning.

Every protected projection establishes the active Governor and concrete Alliance/Kingdom/Event scope before owner data is retrieved. Destination writes reacquire current authority and accept scalar IDs/value objects rather than foreign Eloquent models.

The program never derives a generic Governor strength/quality score, optimal Rally, expected damage, strategic intent, threat conclusion or unqualified game formula. Missing, stale, unknown, conflicting, unsupported and unavailable are first-class states and are never coerced to zero, false, complete or eligible.

## Reconciliation of the previously selected extensions

The implementation inventory on `main` supersedes stale global-ledger labels:

| Extension | Reconciled state | Product truth |
| --- | --- | --- |
| Kingdom Transfer Screenshot Intake | Implementation present; immutable-candidate verification pending | Typed schemas, fixture corpus, review, duplicate/retry boundaries, scalar KingdomTransfers commit and destination tests exist. It remains selected until all containing-SHA gates pass. |
| Governor Progression Screenshot Intake | Implementation present; immutable-candidate verification pending | Typed schemas, canonical dataset pinning, reviewed append-only Roster commits, retry and reference-boundary tests exist. It remains selected until all containing-SHA gates pass. |
| Progression Goal Planner | Implemented; containing-SHA verification remains authoritative | Factual target/path comparison is present. Governor Gear and Governor Charms alone are qualified calculator families. Hero Gear/Mastery, Troops, Research and Buildings/Truegold remain unavailable with their documented incomplete/gap/conflict dispositions. |

No documentation assertion opens a calculator gate. The machine-readable qualification reports, immutable release/checksum checks and golden fixtures remain the authority.

## Kingshot Event Identity & Evidence Gate

Before a named event receives specialized application behavior, `/docs/product` and the server-owned catalogue must establish:

1. a stable canonical Kingshot identity and localized display name;
2. acceptable source provenance, observation/review date and version boundary when known;
3. verified application workflow dimensions, separately from gameplay mechanics;
4. explicit unsupported/evidence-gated mechanics;
5. an enabled profile reviewed by the privileged server-owned boundary;
6. typed action/read-model gates and regression coverage.

Candidate, conflicting and unsupported identities remain profile-disabled. A display name, localization entry, migration, fixture, old hard-coded catalogue, adjacent game or community project cannot enable behavior. The closed workflow vocabulary and Bear Hunt baseline are defined in [Kingshot Event Type Framework](kingshot-event-type-framework.md).

## Shared state, provenance and UX contract

All phase projections expose, where applicable:

- canonical owner and owner-workflow handoff;
- factual `observedAt`/`capturedAt`/`updatedAt` time rather than a synthetic freshness date;
- source/evidence or immutable dataset/revision identity;
- confidence only when supplied by the canonical owner;
- concrete scope and authorization semantics;
- explicit `empty`, `missing`, `stale`, `unknown`, `conflicting`, `unsupported`, `unavailable` and `error` presentation states;
- server-derived typed status/reason codes with localized visible copy;
- responsive desktop/mobile layout, logical keyboard order, visible focus, semantic headings/status text and screen-reader labels;
- privacy-safe audit/telemetry that records IDs, counts, state/reason codes and duration, never raw screenshots, OCR, private Assistant questions or unauthorized identity data.

Deterministic thresholds, tolerances, freshness windows and comparisons live in backend services/configuration and tests. Vue, controllers, jobs, prompts and bots do not contain business formulas.

## Phase 13 — Kingshot Event Type Framework

`Operations/Events` owns canonical Event identity, occurrence scheduling and the closed typed workflow profile. Baseline scheduling, user-authored phases and user-authored polls are independent of profile specialization. The legacy generic capability/mechanic editor, capability resolver/guard, automatic poll/phase materializers and mutable platform profile path are removed.

Bear Hunt is the only verified enabled profile in this program snapshot. It supports participation, roster, rallies, results, screenshot Evidence, Debrief and readiness/closeout. Candidate identities expose no specialized workflow or event-specific result schema. Details and acceptance are in [Kingshot Event Type Framework](kingshot-event-type-framework.md).

## Phase 14 — Rally Roster Builder

The Event management surface composes Events, Participation, Rosters, Rallies, BattlePlans and authorized Governor-observation freshness. Operations/Rallies continues to own Rally groups, leads, joiners, standbys and recorded participation; Rosters owns Event roster membership; Participation owns registration/response state.

For each occurrence the read model derives factual reason codes for registered-but-unassigned Governors, Governors occupying multiple Rally groups, groups without a lead, declined assignments, joiner capacity gaps, missing standbys and missing/stale/unknown Governor observations. Publishing means handing the officer to the existing Event/Rally workflow; it does not copy a plan into a new store.

Acceptance: only a verified profile with `rallies` (and any additionally consumed dimension) exposes the builder; conflict/gap counts reconcile to owner rows; removed/declined assignments do not occupy slots; every item links to Event management; no optimization/recommendation claim is generated; authorization/isolation, bounded-query, localization, accessibility and responsive tests pass.

## Phase 15 — Alliance Member Capability Profile

The existing authorized Governor Roster history becomes the factual member profile. It composes Alliance role/membership, latest and previous progression observations with freshness/source, registrations/attendance, Bear Hunt accepted results and Rally actuals, BattlePlan assignments, current Transfer assessment, and Evidence review state when the actor has the corresponding authority.

Each section may be independently unavailable. The profile preserves owner provenance and links to the canonical history or workflow. It has no aggregate member score, rank or recommendation.

Acceptance: Alliance/Intelligence authorization occurs before the roster entry and related owner rows are retrieved; sections are bounded and scope-filtered; missing source permissions hide the section rather than leaking counts; factual observations remain append-only; mobile/keyboard/screen-reader/localization states and query budgets are covered.

## Phase 16 — Bear Hunt Performance

`ReadModels/EventAnalysis` extends the existing Bear Hunt Debrief. It composes accepted Alliance/Governor Results, attendance, recorded Rally participation, Evidence review state and a bounded history of the same canonical Bear Hunt Event type.

Permitted deterministic signals are current-versus-previous difference, increased/decreased/unchanged, personal best among accepted results, missed-after-registering, result-awaiting-review and Rally-participation change. Missing results are not zero. The projection makes no formation, expected-damage, lead-selection or causal claim.

Acceptance: canonical verified Bear Hunt plus `participation`, `rallies`, `results` and `debrief` gates are required; comparison inputs and evidence state are visible; accepted-result history is bounded; unmatched Governor review stays an Evidence handoff; controller/read-model/action, query-budget, localization, accessibility and visual tests pass.

## Phase 17 — KvK Command

State: blocked by the Event Identity & Evidence Gate.

The repository does not currently establish a verified canonical KvK identity and supported workflow contract. No `KvK` context, catalogue profile, controller branch, phase timing, score, territory rule, readiness rule or UI is created. Once acceptable evidence is documented, an enabled profile may compose Events, Participation, Rosters, BattlePlans, Rallies, TerritoryPlanning, Alliance Content, Intelligence, Communications and Results. Readiness/closeout will remain derived ReadModels state.

The exit criterion is evidence, not code volume: canonical identity/provenance, reviewed workflow dimensions, unsupported-mechanics list, typed profile, owner-query contracts and all acceptance/release gates.

## Phase 18 — Verified Event Operations Onboarding

Additional events use one repeatable path: identify → source → canonicalize → classify workflow dimensions → list unsupported mechanics → enable profile → add typed readiness/closeout rules → add event-specific UX only when owner-neutral composition is insufficient.

The catalogue, resolver and workflow guard are the extension points. Controllers and Vue components consume typed dimensions; they do not grow name/category heuristics. Candidate identities can still be scheduled through baseline Events but never inherit specialized behavior.

Acceptance: the onboarding checklist is documented and enforced by catalogue/resolver/architecture tests; profile-disabled is distinct from dimension-absent; multi-dimension consumers require all inputs; unverified named events receive no result schema or workflow materialization.

## Phase 19 — Transfer Campaign Workspace

The Alliance Recruitment candidate experience composes Recruitment stage/history, reviewed Governor Evidence, KingdomTransfers observations/eligibility, target Kingdom/window, Communications delivery state and eventual Membership/Roster arrival state. It introduces no campaign database and does not duplicate candidate, eligibility or membership truth.

The journey is prospect → candidate → Evidence → Transfer observations → eligibility assessment → target/window → coordination → expected arrival → transferred → active membership. `needs_verification`, missing window, stale/conflicting observations, delivery pending/failed and arrival not observed remain explicit.

Acceptance: every section is independently authorized before retrieval; candidate/transfer/membership identifiers are resolved within one Alliance; eligibility is returned only by KingdomTransfers; delivery success is not inferred from queue state; each action deep-links to its owner; no Transfer rule or pass formula is invented.

## Phase 20 — Kingdom Intelligence Timeline

The existing Kingdom Watch, Alliance dossier, Governor history, Intelligence signal and Evidence history projections are composed chronologically. Timeline entries contain stable owner type/ID, scope, observed time, provenance/evidence, confidence when owned, and a canonical link. Derived change signals link to the underlying before/after facts and never become a second intelligence record.

Entries may represent Alliance/Governor observations, diplomacy, progression, Transfer, Recruitment, Event participation or accepted Evidence only when the actor is authorized for that owner. `power increased` remains a factual difference and never becomes an inferred strategic intention.

Acceptance: authorization precedes every owner query; cursor/order ties are deterministic; timelines are bounded; source gaps and conflicting observations remain visible; no timeline table is introduced; owner and provenance are screen-reader accessible and localized.

## Phase 21 — Territory & Hive Execution

The current Territory Planner and reconciliation flow is the canonical implementation. Immutable published revisions represent desired state; reviewed dated observations represent reality. ReadModels compares them using versioned KingdomMaps geometry and typed tolerances, then hands discrepancy resolution or replacement-plan creation back to owner workflows.

Supported factual states include planned Governor/structure not observed, unexpected observed entity, position outside tolerance, stale observation and incomplete Evidence. Resolving a discrepancy does not mutate the published revision or rewrite an observation.

Acceptance: publish/observation/reconciliation histories remain immutable and separately sourced; all comparisons are scoped and deterministic; missing/stale/incomplete do not equal aligned; replacement plans create new revisions; authorization, geometry, tolerance, accessibility, visual and query-budget tests pass.

## Phase 22 — Alliance Command

The existing Command Overview evolves into the R4/R5 Kingshot attention surface. It composes the next verified Event and Rally gaps, BattlePlan gaps, stale/missing Governor observations, Transfer verification, Intelligence changes, Territory discrepancies, Evidence review and Communications failures. It stores no generic task or attention rows.

Each item contains owner, factual reason/status, count or affected identity, observation/update time when relevant and a navigation-only owner handoff. Informational changes do not inflate actionable counts. An actor without a capability receives no item or count from it.

Acceptance: concrete Alliance authority is established before retrieval; attention is recomputable and deterministically ordered; unavailable owner reads remain explicit when needed; every actionable item has one owner handoff; dashboard desktop/mobile, keyboard, screen-reader, localization, query-budget and privacy-safe telemetry coverage pass.

## Phase 23 — Officer Briefs & Notifications

Communications consumes owner projections or deterministic readiness/change-signal contracts for Event/Rally incompleteness, unassigned registration, BattlePlan changes, expiring Transfer Evidence, eligibility change, accepted progression Evidence, stale Governor observation, Territory divergence, accepted Bear Hunt report and available Debrief.

Supported presentation groups are Daily Officer Brief, Upcoming Event Brief and Post-Event Closeout Brief. Communications owns subscription, channel, idempotency fingerprint, delivery attempt, retry and receipt. Every message states the factual trigger, owner and canonical handoff. Queued is not delivered.

Production delivery uses bounded, cursor-addressable recipient sweeps and introduces no brief or signal persistence. Scheduled sweeps rotate a short shared-cache cursor through every page and wrap after the final page. Every sweep resolves the current owned Player, active Alliance membership and required owner permissions before protected projections are retrieved, and each publisher repeats the account/scope authorization check immediately before queueing. Daily Officer Briefs become eligible after 09:00 in the recipient account's configured timezone and queue once per recipient/channel/local calendar date. Upcoming Event, Post-Event Closeout and Intelligence change deliveries are re-evaluated every 15 minutes and queue only when a supported non-empty semantic fingerprint has not already been delivered to that recipient/channel. Revoked authority, disabled channels, unavailable groups and empty signal sets create no delivery.

`officer.brief` and `intelligence.change` are first-class Notification Center preferences. The existing Communications worker owns external provider attempts, automatic bounded retry, failure state and diagnostic visibility. Queue sweeps report only bounded counts, cursor/truncation state and duration; they do not log message bodies, signal summaries, private identities or destination credentials.

Acceptance: notification fingerprints are stable; same-run and later-run replays do not duplicate meaning; changed fingerprints create a new delivery; revoked authority and cross-Alliance identity mismatches prevent future protected delivery; disabled channels create no delivery; content has no unsupported conclusions; automatic failure/retry and receipt diagnostics work for both types; bounded cursor behavior and command/scheduler registration are tested; failed/unavailable/dismissed states are explicit; in-app/Discord/Telegram presentation is localized, accessible and privacy-safe.

## Phase 24 — Alliance Assistant Expansion

The Assistant answers Kingshot-specific questions only from exact authorized projections: Alliance Command attention, verified Event readiness, Rally gaps/assignments, Bear Hunt history, Transfer verification, progression freshness, Intelligence timeline/change, and Territory comparison. Every answer carries server-built owner/provenance Evidence.

Write-like requests return a context-preserving navigation handoff to the owner workflow. The Assistant performs no direct mutation, authorization-by-prompt, hidden query, inferred gameplay claim or free-form fallback.

Acceptance: typed intents and prompts are bounded; authorization occurs inside each owner query; missing/unsupported/conflicting states are honest; source links are preserved; write-intent tests prove zero database/outbox mutation; logs exclude question text; localized responsive/accessible UI and deterministic contract tests pass.

## Phase 25 — Reconciliation and release closeout

Closeout performs spec→code, code→spec, UX→backend, owner/auth/provenance, missing-state, performance, accessibility/localization, observability/recovery and release-gate audits. Product catalogue, gap analysis, user journeys, this ledger, architecture/codebase/operations/reference docs and ADRs are updated only to current truth.

No phase is promoted based on intent or a green subset. The exact immutable candidate must pass PHP tests, Pint, PHPStan, frontend format/lint/types/build, documentation/product-language/localization, architecture/contracts, query budgets, visual regression, CodeQL, dependency review, production image/container scan, clean-database/staging, backup/restore and repository-specific gates. A failure reopens the affected row.

## Delivery ledger

| Phase | Rows | Current state | Exit condition |
| --- | --- | --- | --- |
| 13 | P13-01–P13-19 | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Framework contract, typed consumers, legacy removal, Bear Hunt regression and full gates reconcile. |
| 14 | P14-01 contract; P14-02 owner projection; P14-03 gaps/conflicts; P14-04 management UX; P14-05 auth/isolation; P14-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Every row implemented and verified without recommendation semantics. |
| 15 | P15-01 contract; P15-02 progression; P15-03 participation/Bear; P15-04 BattlePlan/Transfer/Evidence; P15-05 UX/auth; P15-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Factual member profile is complete, bounded and provenance-preserving. |
| 16 | P16-01 contract; P16-02 typed profile gate; P16-03 accepted history; P16-04 comparisons/signals; P16-05 Evidence state; P16-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Existing Debrief satisfies the expanded contract; containing-candidate verification remains open. |
| 17 | P17-01 identity evidence; P17-02 workflow review; P17-03 profile; P17-04 composition; P17-05 UX; P17-06 tests/gates | Blocked at P17-01 | Acceptable Kingshot identity/workflow evidence exists; then all rows close. |
| 18 | P18-01 onboarding contract; P18-02 typed extension seam; P18-03 disabled states; P18-04 architecture tests; P18-05 docs/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | New candidates cannot specialize without the documented gate. |
| 19 | P19-01 contract; P19-02 Recruitment composition; P19-03 Transfer/Evidence; P19-04 delivery/arrival; P19-05 UX/auth; P19-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | One authorized candidate journey spans owners without duplicated truth. |
| 20 | P20-01 contract; P20-02 owner entry schema; P20-03 chronological composition; P20-04 provenance/links; P20-05 UX/auth; P20-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Authorized bounded timelines and underlying-fact links reconcile. |
| 21 | P21-01 contract; P21-02 immutable intent; P21-03 observations; P21-04 comparison/resolution; P21-05 UX/auth; P21-06 tests/gates | Existing implementation reconciled; local frontend/documentation gates green; immutable-candidate verification pending | Existing Territory reconciliation satisfies this program contract. |
| 22 | P22-01 contract; P22-02 attention schema; P22-03 owner composition; P22-04 handoffs; P22-05 UX/auth; P22-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | R4/R5 surface covers the authorized Kingshot attention set without persistence. |
| 23 | P23-01 trigger contract; P23-02 briefs; P23-03 delivery/idempotency; P23-04 handoffs; P23-05 UX/auth; P23-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | All supported factual triggers and digest states are verified. |
| 24 | P24-01 intent contract; P24-02 owner projections; P24-03 provenance; P24-04 safe handoffs; P24-05 UX/auth; P24-06 tests/gates | Implementation complete; local frontend/documentation gates green; PHP and immutable-candidate verification pending | Listed Kingshot questions are bounded, grounded and zero-write. |
| 25 | P25-01 audits; P25-02 docs; P25-03 architecture; P25-04 operations; P25-05 full gates; P25-06 immutable evidence | Verification in progress; local frontend/documentation/build gates green; PHP/Pint/PHPStan/CI/staging evidence pending | No open non-evidence-blocked row remains and exact candidate is green. |

## Continuous reconciliation rule

If implementation discovers a missing owner boundary, authorization rule, identity ambiguity, provenance requirement, stale/conflict state, query risk, unsupported-mechanic risk, UX/accessibility issue or release requirement, update this contract and its ledger before implementing the correction. Uncertainty is resolved by an explicit state or closed evidence gate, never by inventing Kingshot facts.
