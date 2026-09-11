# Kingdom Transfer Planning

Status: Current implementation contract

Owner: `GameWorld/KingdomTransfers`

Source/confidence matrix: [Kingdom Transfer official-rules source matrix](kingdom-transfer-official-rules-source-matrix.md)

Kingdom Transfer Planning answers one operational question without manufacturing certainty:

> Can this Governor transfer to the selected Kingdom in this Transfer Window, what still needs to happen, and what capacity is actually available?

The capability separates three kinds of truth:

1. **KingShot observations/rules** — sourced, append-only facts about the current event, target Kingdom and Governor.
2. **Alliance planning intent** — cohorts, readiness, blockers, slot reservations and invitation allocations.
3. **Derived eligibility** — a recomputable assessment over current authoritative facts. It is never persisted as a boolean.

Missing, stale, conflicting or non-authoritative information cannot silently become `eligible_now`.

## Workflow history navigation

Readiness keeps active and resolved manual blockers independently reachable, with complete totals and 25-record forward/first-page navigation. Readiness transitions have their own history. Opening or paging a history does not discard an unsaved readiness or blocker draft. Loading and retry states are explicit, and each request rechecks the current active Governor and Alliance scope. Historical navigation does not change eligibility, readiness or completed-transfer facts. The remaining workspace scalability work is tracked in the [hardening ledger](codebase-hardening-delivery-ledger.md); this contract is not a claim that the overall program is complete.

## Current authoritative rule boundary

The implementation is aligned to the current official KingShot transfer material recorded in the source matrix. First-class rules include:

- source and target Kingdoms must be in the same official Transfer Group for the selected window;
- source/target Hero Generation compatibility;
- source/target Truegold compatibility;
- target-specific character-age threshold, bounded by the official 90–180 day rule range;
- 25-day transfer cooldown;
- maximum four characters in one Kingdom;
- target Power Cap and Leading/Ordinary classification;
- Ordinary/Special Invite requirements;
- Leading Kingdoms cannot issue Special Invites;
- Ordinary Kingdom capacity: 55 total, split into 35 Ordinary Invite and 20 Transfer Opens slots;
- Leading Kingdom capacity: 30 total, split into 20 Ordinary Invite and 10 Transfer Opens slots;
- current Special Invite inventory, with a maximum of three;
- Transfer Pass sufficiency using the current in-game required count; official public material establishes a 1–50 range but does not publish a safe exact formula;
- pre-transfer Storehouse protection warning because resources above the protected amount are lost after transfer;
- a final current `in_game_rules_verified` gate for remaining unpublished or game-version-specific restrictions.

The application does **not** calculate required Transfer Passes from Transfer Score. Transfer Score may remain useful observed context, but the required-pass value used by eligibility is the current sourced in-game value.

Community guides, bots, social posts and other non-authoritative material are discovery/context only and cannot satisfy an authoritative requirement.

## Domain ownership

`GameWorld/KingdomTransfers` owns:

- Transfer Plans, participants, readiness transitions, blockers and completions;
- Alliance planning Transfer Cohorts;
- Transfer Windows and phase boundaries;
- official window-scoped Transfer Groups and membership;
- target Kingdom condition history;
- target Kingdom capacity observations;
- participant transfer observations;
- Alliance capacity reservations and invitation allocations;
- observation provenance/freshness/conflict semantics;
- deterministic eligibility evaluation;
- transfer-specific audit/outbox events and idempotency.

`Intelligence/Evidence` owns screenshots, OCR/classification/extraction, reviews, duplicate handling, commit attempts and retention. Evidence can prove owner facts only through dedicated scalar handoffs; it never mutates KingdomTransfers tables directly.

## Terminology

**Transfer Group** means only the official KingShot grouping of Kingdoms for one Transfer Window.

**Transfer Cohort** means an Alliance planning bucket. No compatibility aliases or old planning `TransferGroup` model remain.

## Transfer Window and phases

A Transfer Plan references exactly one sourced Transfer Window with explicit UTC boundaries for:

- Pre-Transfer;
- Invitational Transfer;
- Transfer Opens;
- event end.

Derived phases are `not_started`, `pre_transfer`, `invitational_transfer`, `transfer_opens`, and `closed`.

Phase is derived from timestamps, not maintained as mutable free-form state.

## Official Transfer Group

Official Transfer Group membership is window-scoped. Corrections create new revisions and preserve prior history. A Kingdom is not given a timeless transfer-group attribute.

## Target Kingdom conditions

Target Kingdom condition observations are append-only and may include:

- Power Cap;
- classification: `ordinary`, `leading`, or unknown/not proved;
- Hero Generation;
- Truegold level;
- character-age threshold days.

Every condition records source/reference and `observed_at`. Corrections preserve history. The Power Cap cannot be changed after Phase II begins except through an explicit sourced correction path.

## Target capacity observations

Target capacity observations are separate from Alliance reservations. They record current observed KingShot state for one window + target Kingdom:

- Ordinary Invites used;
- Transfer Opens used;
- Special Invites available;
- source/reference;
- `observed_at`;
- optional Evidence reference;
- correction marker.

Official capacity totals come from the versioned rulebook and classification; current use/inventory comes from sourced observations.

The UI must clearly distinguish **Observed KingShot capacity** from **Alliance planned reservations**.

## Governor observations

Transfer observations are append-only. Supported first-class kinds are:

- `governor_power`;
- `hero_generation`;
- `truegold_level`;
- `character_age_over_target_days`;
- `transfer_cooldown_remaining_days`;
- `target_existing_character_count`;
- `transfer_score`;
- `transfer_passes_available`;
- `transfer_passes_required`;
- `invitation_status`;
- `resource_protection_verified`;
- `in_game_rules_verified`.

Each observation records Transfer Window, participant, target where applicable, typed value, source/reference, `observed_at`, explicit `valid_until` for mutable current facts, optional Evidence ID, actor and deterministic fingerprint.

Manual forms cannot claim `source_type=evidence`; Evidence-backed truth enters only through the reviewed Evidence commit path.

## Provenance and freshness

Authoritative source types are `official_publication`, `in_game`, and approved same-Alliance `evidence`.

`manager_note` and `community` are visible planning/context sources only.

There is no hidden universal TTL. Mutable facts used for current eligibility require an explicit validity boundary. Expired facts are `stale`; simultaneous authoritative disagreement is `conflicting`; missing or non-authoritative truth is `unknown`.

## Eligibility requirements

For an active incoming/outgoing participant, evaluation is ordered around the current transfer decision:

1. official window phase;
2. official Transfer Group compatibility;
3. Hero Generation compatibility;
4. Truegold compatibility;
5. character-age threshold;
6. transfer cooldown;
7. target character-count limit;
8. target Power Cap;
9. invitation requirement/type;
10. total target capacity;
11. invitation/Special Invite capacity when applicable;
12. Transfer Opens capacity when applicable;
13. Transfer Pass sufficiency;
14. Storehouse resource-protection pre-flight;
15. final in-game rules verification.

Requirement states are `met`, `unmet`, `unknown`, `stale`, `conflicting`, and `not_applicable`.

Overall outcomes are:

- `eligible_now`;
- `eligible_with_action`;
- `blocked`;
- `needs_verification`;
- `not_open_yet`;
- `window_closed`;
- `not_applicable`.

Hard rule mismatches such as Transfer Group, Hero Generation, Truegold, character age, target character limit and impossible Leading-Kingdom Special Invite paths produce `blocked`.

Actionable shortages such as cooldown, passes, capacity, invitation acquisition or unprotected resources produce `eligible_with_action` when all required facts are trustworthy.

`resource_protection_verified=false` is a pre-transfer consequence warning, not a false claim that KingShot forbids the transfer. Missing/stale resource verification still fails closed to `needs_verification` because the user must be warned before completion.

## Alliance capacity reservations

Capacity reservations are Alliance planning intent, not KingShot reservations. One participant may hold one current planning reservation in either:

- `ordinary_invite`;
- `transfer_open`.

States are `planned`, `reserved`, `confirmed`, `released`, and `failed`.

Creating/changing a consuming reservation requires current authoritative target capacity and is serialized against other reservations so Alliance planning cannot oversubscribe known capacity.

Withdrawal releases consuming reservations. Completion finalizes consuming reservations to `confirmed`. A confirmed commitment continues to reduce projected remaining capacity until a newer authoritative capacity observation is at or after that commitment update, at which point the observed game state supersedes the planning subtraction and double counting stops.

## Invitation allocations

Invitation allocations are Alliance planning workflow, separate from observed `invitation_status` game truth.

Kinds are `ordinary` and `special`. States are `requested`, `reserved`, `issued`, `accepted`, `declined`, and `cancelled`.

Special Invite consuming states require:

- authoritative Ordinary target classification;
- current observed Special Invite inventory;
- remaining inventory after other Alliance allocations.

Leading Kingdoms cannot create a consuming Special Invite allocation.

Withdrawal cancels consuming allocations. Completion finalizes consuming allocations to `accepted` as planning workflow. The evaluator still relies on the independently sourced `invitation_status`; allocation state never proves game eligibility.

Issued/accepted Special Invite commitments are subtracted only until a newer authoritative capacity/inventory observation supersedes them, avoiding double counting.

## Screenshot Intake: Transfer Evidence

The five explicit Evidence families remain:

- Governor status;
- Transfer Score & Passes;
- invitation;
- target Kingdom rules;
- official Transfer Group.

The target Kingdom rules schema is v2 and may review/commit only fixture-proven fields among:

- target Kingdom number;
- Power Cap;
- classification;
- Hero Generation;
- Truegold level;
- character-age threshold days.

Evidence preview invokes the same `TransferEligibilityEvaluator` as current reads and substitutes only reviewed candidate facts in memory. No Evidence schema can manufacture `in_game_rules_verified=true`.

## Management UX

`/alliance/transfers/manage` lets authorized managers:

- record Transfer Windows;
- record/revise official Transfer Groups;
- record target Power/classification/Hero/Truegold/age-threshold facts;
- record current target slot usage and Special Invite inventory;
- inspect sourced condition/capacity history;
- create/manage the Transfer Plan, cohorts and participants.

`/alliance/transfers/readiness` is decision-first. It shows:

- outcome and primary next action;
- every requirement and provenance;
- observed versus Alliance-projected capacity;
- capacity reservation and invitation allocation controls;
- reviewed Evidence workflow;
- append-only observation entry/history;
- independent Alliance readiness and blockers.

Required triage includes outcome plus missing target, invitation need, pass shortfall, Power Cap, generation, Truegold, age, cooldown, character limit, resource warning and capacity shortages where represented by the current UI.

## Completion and withdrawal

Withdrawal and completion reconcile planning commitments in the same domain transaction as the workflow state change.

- withdrawal releases consuming capacity reservations and cancels consuming invitation allocations;
- completion finalizes consuming reservations/allocations while recording the roster/player outcome;
- accepted owner observations remain immutable history;
- completion does not mutate prior eligibility observations into synthetic game truth.

## Authorization and isolation

- view requires the transfer view boundary;
- mutations require `kingdom_transfer.manage` and recent password confirmation at HTTP boundaries;
- every Action reacquires current actor/Alliance authority and concrete plan/window/participant scope;
- foreign Alliance IDs cannot be used to infer record existence;
- Evidence references must be same-Alliance and approved where required.

Frontend permission flags are affordances only.

## Concurrency and idempotency

- observation identity uses deterministic fingerprints;
- official-group revisions are serialized per window;
- target condition/capacity corrections preserve history;
- reservation/invitation allocation writes lock participant/current commitment and relevant competing commitments;
- destination Evidence receipts are stable/idempotent;
- retries cannot create duplicate accepted owner truth.

## Operational and test acceptance

The capability is complete only when the final implementation candidate is green for:

- fresh PostgreSQL installation/migrations;
- Pint;
- PHPStan;
- KingdomTransfers V3 behavior/contract/completeness tests;
- Evidence review/commit tests;
- frontend lint/format/type/build;
- Architecture V3 Verification;
- Intelligence Verification;
- Visual Regression desktop/mobile transfer states;
- CodeQL and Dependency Review;
- authorization/isolation and query-budget coverage;
- backup/restore/replay expectations in operations guidance;
- documentation/source-matrix reconciliation.

No compatibility shims, legacy aliases or dual-read/write paths are part of this fresh deployment.

The member capability profile evaluates only that Governor's active participant in the current authorized Alliance Plan. Missing or withdrawn participation remains unavailable as an assessment rather than selecting another Governor. A large plan does not enlarge the participant records or relationship graph read for that single profile.

A Governor’s self-transfer answer uses the same current eligibility assessment as management, including conflicting evidence, unknown facts, group/condition provenance and current capacity. Requests cannot select another Governor’s assessment or a different destination. Complete observation counts remain accurate even when evaluation needs only bounded factual witnesses; historical records for a previous destination do not influence the current destination.

## Participant navigation and totals

Overview, management, readiness and outcomes display 25 participants at a time, in stable registration-ID order. The page summary gives the number currently displayed and the complete total for that view. Direction and outcome totals remain complete even when the corresponding participants appear on another page. Use Next page to continue and First page to refresh from the beginning; deleting or renaming a participant does not invalidate the continuation boundary.

Readiness's eligibility filter applies to the currently displayed page, as labelled beside the filter. An empty filtered page is not a statement that no participant in the plan matches; continue paging to review the rest. Game eligibility still comes from the canonical server evaluator, independently of Alliance readiness.

Participant/cohort edits are retained during page navigation and failed loads. The displayed page does not silently become empty on failure: a retry control appears. Switching Alliance or Plan discards old-scope drafts. Loading further pages is not a write and does not change readiness, evidence or completion. History navigation remains separate for each visible participant. See the [authoritative query boundary](../architecture/contexts/game-world/kingdom-transfers.md) for current authorization and cursor rules.
