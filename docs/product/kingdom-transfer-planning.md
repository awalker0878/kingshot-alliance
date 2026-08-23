# Kingdom Transfer Planning

Status: Current — Complete 2026-08-23

Kingdom Transfer Planning extends the existing Kingdom Transfer participant/readiness workflow into a sourced game-planning capability. This document is the implementation contract for the active delivery program. A delivery-ledger item is not complete until the behavior, authorization, persistence, idempotency where applicable, audit/observability, responsive UX, accessibility, localization, tests and visual proof required by that item are complete.

## Product outcome

For an authorized Alliance manager, the primary question is:

> Can this Governor transfer to Kingdom 123 during this Transfer Window, and what still needs to happen?

The product must answer that question without manufacturing certainty. A Governor is never presented as eligible from stale, missing, conflicting or unsourced facts. When evidence is insufficient, the answer is **Needs verification** and the UI identifies the exact facts that must be refreshed or verified.

Readiness and game eligibility are separate concepts. Existing readiness state remains an Alliance planning workflow. Eligibility is a deterministic assessment of the current Transfer Window, target Kingdom, sourced game facts and current Governor observations. Eligibility never silently changes readiness.

## Authoritative game-rule boundary

Verified official sources as of 2026-08-23:

- [Kingshot — Kingdom Transfer](https://www.centurygames.com/kingshot-kingdom-transfer/) — official event announcement describing the seven-day event, three phases, Transfer Groups, same-group restriction, Power Cap, Leading/Ordinary Kingdom classification, invitations, Transfer Score and Transfer Pass dependency.
- [How many phases are there in Kingdom Transfer?](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8546-how-many-phases-are-there-in-kingdom-transfer/) — official phase semantics and the rule that the Power Cap cannot change after Phase II begins.
- [Special Invites & Ordinary Invites](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8547-special-invites-ordinary-invites/) — official invitation distinction and Special Invite behavior.
- [Leading Kingdoms & Ordinary Kingdoms](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8559-leading-kingdoms-ordinary-kingdoms/) — official target classification and the rule that Leading Kingdoms cannot issue Special Invites.

These sources do not publish the exact Transfer Pass formula and explicitly defer additional eligibility rules to the in-game rules. Therefore:

1. the application may display and use an **observed required Transfer Pass count** from current in-game evidence;
2. it must not invent a Transfer Score → Transfer Pass formula;
3. it must not claim complete game eligibility solely from the public web rules;
4. a fresh `in_game_rules_verified` observation is required before an assessment can become `eligible_now`;
5. unpublished rules remain evidence-gated until an authoritative, version-bounded source exists.

Community projects, wikis, bots, guides and social posts are discovery evidence only. They may never turn an eligibility requirement from unknown into met.

## Capability ownership

`GameWorld/KingdomTransfers` owns:

- Transfer Plans and the existing participant/readiness/completion workflow;
- Alliance planning cohorts;
- Transfer Windows and official phase boundaries;
- official Transfer Groups and their window-specific Kingdom membership;
- target-Kingdom transfer conditions, including sourced Power Caps and Kingdom classification;
- transfer-specific Governor observation history;
- eligibility rules, requirement evaluation and transfer-specific invariants;
- audit/outbox semantics for material Kingdom Transfer mutations.

Other contexts retain their own aggregates. Kingdom Transfer may reference Player, Alliance, Kingdom and Evidence identifiers but does not take ownership of those aggregates. Cross-context writes continue through owner Actions/value objects rather than foreign-model mutation.

## Terminology correction

The current code uses `TransferGroup` for an Alliance-managed coordination bucket. That conflicts with KingShot's official **Transfer Group** concept.

Because the application is not deployed, implementation must rename the existing planning concept cleanly:

- `TransferGroup` → `TransferCohort`
- `transfer_groups` → `transfer_cohorts`
- `transfer_group_id` → `transfer_cohort_id`
- related Actions, Queries, Controllers, routes, event names, localization and frontend vocabulary must use **cohort** for the Alliance planning concept.

No compatibility aliases, duplicate models, dual reads/writes or transitional schema are retained.

**Transfer Group** thereafter means only the official, Transfer-Window-scoped KingShot grouping of Kingdoms.

## Domain model

### Transfer Window

One official Kingdom Transfer event. It carries:

- stable application ID;
- label;
- Phase I start;
- Phase II start;
- Phase III start;
- event end;
- source type/reference;
- `observed_at`;
- optional source evidence identifier.

Phase is derived from explicit timestamps; it is not a mutable free-form status.

Required derived states:

- `not_started`;
- `pre_transfer`;
- `invitational_transfer`;
- `transfer_opens`;
- `closed`.

A Transfer Plan references exactly one Transfer Window.

### Official Transfer Group

An official Transfer Group belongs to one Transfer Window and has:

- official label/identifier;
- sourced window-specific Kingdom membership;
- source/reference and `observed_at`.

Membership is never stored as a timeless Kingdom attribute. The same Kingdom may belong to a different Transfer Group in another window.

### Target Kingdom condition

A Target Kingdom condition belongs to a Transfer Window + Kingdom and records:

- Power Cap when known;
- Kingdom classification: `ordinary`, `leading`, or `unknown`;
- source/reference;
- `observed_at`;
- optional evidence ID.

Power Cap is a window fact. The application enforces the official invariant that it cannot be changed after Phase II begins except by recording a correction whose source explicitly proves the previous observation was wrong. Corrections retain history; they do not erase prior observations.

### Transfer observation

Mutable Governor/target facts are append-only observations, never silently overwritten current-truth columns.

First-release observation kinds:

- `governor_power`;
- `transfer_score`;
- `transfer_passes_available`;
- `transfer_passes_required`;
- `invitation_status`;
- `in_game_rules_verified`.

Each observation records:

- Transfer Window;
- Transfer participant;
- target Kingdom where the fact is target-specific;
- typed kind;
- typed value;
- source type/reference;
- `observed_at`;
- `valid_until` for mutable Governor facts;
- optional Evidence identifier;
- actor who recorded it;
- deterministic fingerprint for idempotent ingestion.

`transfer_passes_required` is observed from the game until an authoritative pass formula is available. `in_game_rules_verified` is a current in-game verification that no additional unpublished transfer restriction is presently blocking the Governor; it does not replace the explicitly modeled requirements.

## Provenance and freshness contract

### Source types

Supported source types are:

- `official_publication` — Century Games public material;
- `in_game` — direct observation of KingShot UI/rules;
- `evidence` — reviewed application Evidence whose provenance remains owned by Intelligence/Evidence;
- `manager_note` — human planning note without authoritative supporting evidence;
- `community` — discovery-only external community material.

Only `official_publication`, `in_game`, and reviewed `evidence` may satisfy an authoritative eligibility requirement. `manager_note` and `community` remain visible context but cannot produce a `met` requirement.

### Freshness

The application does not invent a universal hidden TTL.

- Window/group/Kingdom-condition facts are valid only inside the Transfer Window/version boundary they explicitly describe.
- Mutable Governor observations must carry `valid_until` to be usable as current eligibility evidence.
- A mutable observation with no `valid_until` is historical context only and yields `unknown` for a current assessment.
- When `now > valid_until`, the requirement is `stale`.
- An observation from another Transfer Window is never current for this window.
- Conflicting non-expired authoritative observations produce `conflicting`, not last-write-wins certainty.

Every material fact shown in the eligibility UI includes source and observation time. Stale and conflicting states are visible, not hidden behind a tooltip-only treatment.

## Eligibility assessment

Eligibility is derived on read. There is no persisted `eligible` boolean.

Assessment outcomes:

- `eligible_now` — every required modeled rule is met and a fresh in-game rules verification is present;
- `eligible_with_action` — transfer is possible in the current phase but one or more actionable requirements are unmet;
- `blocked` — a known rule currently prevents transfer and cannot be satisfied merely by refreshing evidence;
- `needs_verification` — at least one required fact is missing, stale, conflicting or non-authoritative;
- `not_open_yet` — the Transfer Window has not reached a phase in which this Governor can transfer;
- `window_closed` — the event has ended.
- `not_applicable` — the Governor is staying in the current Kingdom, so transfer eligibility is not applicable.

Requirement states:

- `met`;
- `unmet`;
- `unknown`;
- `stale`;
- `conflicting`;
- `not_applicable`.

Every requirement result includes a stable requirement key, explanation, actual/required display values where appropriate, source/reference, `observed_at`, `valid_until`, and the recommended next action.

### Modeled authoritative requirements

The first authoritative evaluator models:

1. **Window phase** — Phase I is planning only; Phase II permits invitation-based early transfer; Phase III is open transfer subject to requirements.
2. **Transfer Group compatibility** — source and target Kingdom must be in the same official Transfer Group for the selected window.
3. **Target Power Cap** — sourced target cap must be known.
4. **Invitation**:
   - Phase II requires a current observed invitation;
   - a Governor at or below the cap requires an Ordinary Invite in Phase II;
   - a Governor above the cap requires a Special Invite in Phase II or III;
   - a Leading target Kingdom cannot satisfy an over-cap Special Invite path because official rules state Leading Kingdoms cannot issue Special Invites;
   - in Phase III, a Governor at/below cap does not require an invitation.
5. **Transfer Passes** — available and required counts must be current observations; available must be greater than or equal to required. Automatic required-pass calculation remains evidence-gated.
6. **Additional in-game eligibility** — a current authoritative `in_game_rules_verified=true` observation is required because official public material does not enumerate every in-game restriction.

An explicit current `in_game_rules_verified=false` observation may carry a human-readable blocker reason and produces `blocked`/`eligible_with_action` as appropriate. Unknown unpublished rules never silently pass.

## Manual planning blockers

The existing persistent Transfer Blocker workflow remains independent from derived game eligibility blockers.

- Manual blockers are Alliance planning records with create/resolve history.
- Derived eligibility blockers are computed requirement results and are never copied into the blocker table on every evaluation.
- Resolving a manual blocker does not alter observations.
- Refreshing an observation does not erase manual blocker history.

## Readiness independence

Existing readiness states and transition history remain supported.

Examples:

- `readiness=ready`, `eligibility=needs_verification` is valid;
- `readiness=blocked`, `eligibility=eligible_now` is valid when the Alliance still has planning work;
- eligibility reevaluation never invokes the readiness transition Action automatically.

## User experience

The manager-facing participant surface leads with game eligibility, not the workflow state.

For every outgoing/incoming Governor with a target Kingdom it shows, before secondary details:

- Governor name;
- target Kingdom;
- assessment outcome;
- current official phase;
- official Transfer Group;
- target Power Cap;
- invitation requirement/status;
- Transfer Score where observed;
- available/required Transfer Passes;
- highest-priority remaining action;
- stale/missing/conflicting facts;
- visible source and observation date for each material fact.

The existing readiness control, cohort, notes, manual blockers, readiness history and completion state remain available below the eligibility summary.

Required triage filters:

- all;
- eligible now;
- blocked;
- needs verification;
- needs invite;
- insufficient passes;
- over Power Cap;
- missing target Kingdom.

### UX states

The page has explicit, localized states for:

- no Transfer Window configured;
- window not started;
- Phase I planning;
- Phase II invitation transfer;
- Phase III transfer open;
- window closed;
- no current Transfer Plan;
- no participants;
- no target Kingdom;
- missing official Transfer Group facts;
- missing Power Cap;
- missing/stale/conflicting Governor observations;
- evidence-gated pass formula;
- eligible now;
- blocked/action required;
- read-only locked/closed plan;
- mutation validation/failure and success receipts.

On mobile, Governor, target Kingdom, assessment outcome and primary blocker/action are visible without a wide table or opening a secondary panel. All visual status coding has text equivalents. Source/freshness disclosure is keyboard and screen-reader accessible.

## Management UX

Managers can:

- create/edit Transfer Windows and explicit phase boundaries before use;
- record official Transfer Groups and their Kingdom membership for a selected window;
- record/correct target Kingdom conditions with provenance;
- attach a Transfer Plan to one window;
- preserve existing participant/readiness/cohort/blocker/completion management;
- append Governor observations with source, `observed_at` and `valid_until`;
- inspect observation history rather than overwriting it;
- correct a prior game fact by appending a sourced correction;
- see immediately how new observations change the deterministic assessment.

Material source facts cannot be edited into anonymous values: source type, source/reference and observation time are required at the write boundary.

## Authorization

- Alliance members with transfer view permission may see the current authorized planning surface according to existing policy.
- Transfer management permission is required to create/update Windows, Transfer Groups, Kingdom conditions, observations, readiness, cohorts and manual blockers.
- Every mutation re-resolves active Player + Alliance authority inside the transaction and re-checks the concrete plan/window/participant scope.
- IDs from another Alliance/plan/window cannot be used to infer whether a record exists.
- Evidence references never bypass Intelligence/Evidence authorization or disclose cross-Alliance evidence.

## Idempotency and concurrency

- Observation ingestion uses a deterministic fingerprint over scope, kind, target, typed value, source and observation boundary. Repeating the same observation returns the existing record/no-op rather than duplicating history.
- Window/group/condition corrections are serialized under the relevant window/plan lock.
- A Power Cap write after Phase II begins is rejected unless the Action is explicitly recording a correction and includes authoritative correction provenance.
- Participant/cohort/readiness mutations retain current aggregate locking/idempotent no-op behavior.

## Audit, outbox and observability

Material mutations record audit + outbox metadata without logging sensitive free-form evidence payloads. At minimum this includes:

- Transfer Window create/update;
- official Transfer Group/membership change;
- target Kingdom condition observation/correction;
- Governor transfer observation recorded;
- existing plan/cohort/participant/readiness/blocker/completion events after terminology correction.

Read telemetry is privacy-safe. It may report counts by assessment outcome/requirement state and availability of data, but not Governor names, Transfer Score values, Power values, pass counts, raw evidence text or source screenshots.

Operational diagnostics expose failed validation/retry/idempotency outcomes by correlation/fingerprint without leaking private observation values.

## Localization

Every new visible string lives in the existing transfer localization domain. English is the complete canonical fallback for every supported locale; locale overlays override the keys they translate and otherwise use the shared English fallback, so no supported locale path exposes raw localization keys. Dates, times, Kingdom numbers, Power/Score/pass numbers and phase labels use the existing locale formatting utilities. Source type and freshness state are resolved through localization keys rather than hard-coded English in Vue.

## Test contract

Completion requires behavior coverage for at least:

- phase boundary instants and UTC-safe ordering;
- invalid/overlapping phase boundaries;
- same/different/missing Transfer Group membership;
- window-specific group changes;
- Power below/equal/above cap;
- Phase II Ordinary/Special Invite paths;
- Phase III at/below-cap no-invite path;
- Phase III over-cap Special Invite path;
- Leading target + over-cap impossibility;
- sufficient/insufficient/unknown/stale/conflicting pass observations;
- Transfer Score history/freshness disclosure;
- stale/missing/conflicting Governor Power;
- missing/non-authoritative provenance;
- fresh/false/stale/missing in-game rule verification;
- deterministic assessment changes after a new observation;
- manual blockers independent from derived blockers;
- readiness independent from eligibility;
- observation idempotency;
- cross-Alliance/window/plan authorization;
- query budgets/no-N+1 behavior for a representative large participant list;
- localized formatting and all supported locales;
- keyboard/screen-reader operation;
- mobile layout without horizontal overflow;
- deterministic visual states for eligible, blocked and needs-verification participants.

## Acceptance criteria

The capability is complete only when all of the following are true:

1. A manager can configure/select a sourced Transfer Window, official groups and target conditions without editing code.
2. Existing participants/readiness/blockers/completion continue to work after the cohort terminology correction.
3. For a Governor + target + window, the server returns a structured deterministic eligibility assessment with per-requirement explanations.
4. `eligible_now` is impossible when any required fact is missing, stale, conflicting, non-authoritative, or when fresh in-game verification is absent.
5. Every mutable fact used by the assessment visibly exposes source and observation date; mutable Governor facts also expose their validity boundary.
6. Automatic Transfer Pass calculation is not implemented until an authoritative formula exists; observed required passes are supported now.
7. The UI answers both “Can they transfer?” and “What still needs to happen?” before presenting secondary workflow details.
8. Desktop/mobile, keyboard, screen-reader and all supported locale paths are complete.
9. Material writes are scoped, authorized, audited, concurrency-safe and idempotent where repeat delivery is possible.
10. Product/architecture/reference/operations docs describe current implementation truth with no stale `TransferGroup` planning terminology.
11. Full applicable repository release gates pass on one immutable implementation candidate.

## Delivery ledger

`Complete` means the slice satisfies its complete exit condition across code, UX, authorization, tests, observability and documentation. Documentation-only or backend-only completion is not accepted.

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Product contract | This contract defines complete scope, source/evidence boundary, ownership, UX states, acceptance criteria and delivery ledger before application changes. |
| 2 | Complete | Cohort terminology correction | Existing Alliance planning `TransferGroup` is renamed to `TransferCohort` across schema/code/routes/events/localization/tests with no compatibility layer; official Transfer Group vocabulary becomes unambiguous. |
| 3 | Complete | Transfer Window + official groups | Window/phase persistence, official Transfer Group membership, target conditions and provenance are writable through authorized domain Actions and exposed by bounded queries. |
| 4 | Complete | Governor observation history | Typed append-only sourced observations, explicit validity, conflict handling and idempotent ingestion are implemented with history UI. |
| 5 | Complete | Eligibility domain | Deterministic evaluator implements the sourced rule set, evidence gates, requirement states and structured next actions without a persisted eligibility boolean. |
| 6 | Complete | HTTP/read composition | Authorized planning reads expose window, phase, target, observations, manual blockers and assessments with bounded query counts and no cross-tenant leakage. |
| 7 | Complete | Management UX | Managers can maintain sourced window/group/condition/observation data and see validation, history and receipts without raw IDs or hidden provenance requirements. |
| 8 | Complete | Decision-first participant UX | Readiness page leads with eligibility, requirement/source/freshness details and required triage filters while preserving readiness/cohort/blocker/completion controls. |
| 9 | Complete | Accessibility/localization/mobile | All new states are translated, keyboard/screen-reader usable, mobile-first and free of horizontal overflow; visual status has text equivalents. |
| 10 | Complete | Audit/observability/recovery | Material writes are audited/outboxed, observation retries are idempotent, diagnostics are privacy-safe and recovery/correction behavior is documented. |
| 11 | Complete | Behavioral/architecture/performance tests | Rule boundaries, authorization, idempotency, history, independence invariants, query budgets and architecture constraints are covered. |
| 12 | Complete | Visual regression + closeout | Deterministic eligible/blocked/needs-verification desktop/mobile states are accepted; spec→code, code→spec, UX→backend and docs scans show no implementable gap and full release gates pass. |

The Kingdom Transfer Planning delivery queue is closed: every implementable phase is Complete on implementation candidate `4ee688508f8ef741bb1c43d8909f747743cf9526`. That candidate passed strict PHP/Pint/PHPStan and full tests, frontend checks/build, Architecture V3, Intelligence Verification, CodeQL, Dependency Review, deterministic desktop/mobile Visual Regression, production-image build, ephemeral staging, backup/restore and HIGH/CRITICAL container scanning. The unpublished Transfer Pass formula remains explicitly evidence-gated because no authoritative version-bounded formula is available; it is not an implementation TODO. Final closeout documentation is status-only and the resulting PR head must repeat the repository's normal gates; any failure reopens the affected phase.

### Cross-phase invariants

1. Readiness is not eligibility.
2. Eligibility is derived, never a persisted boolean.
3. Mutable eligibility facts are observations with source + time + explicit validity.
4. Unknown/stale/conflicting/non-authoritative data cannot produce `eligible_now`.
5. Official Transfer Groups are window-scoped game facts; Alliance planning cohorts are a different concept.
6. No unpublished Transfer Pass formula is invented.
7. Additional unpublished in-game requirements are never silently assumed met; fresh in-game verification is required for a yes answer.
8. Owner Actions retain write semantics; controllers/Vue never become the game-rule authority.
9. Every material mutation reauthorizes active Player + concrete scope at commit time.
10. No compatibility shims, dual reads/writes, legacy naming or placeholder implementation survive closeout.
