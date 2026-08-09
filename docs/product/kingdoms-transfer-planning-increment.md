# Kingdoms transfer planning product increment

[← Product and program documentation](README.md)

**Status:** Approved scope — implementation Planned  
**Scope ID:** `KINGDOMS-002`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; this is **not Phase 7**  
**Baseline dependency:** Accepted `KINGDOMS-001` roster/player identity, tenancy and production-hardening controls  
**Implementation sequence:** [KINGDOMS-002 implementation plan](kingdoms-transfer-planning-implementation-plan.md)

## 1. Purpose

`KINGDOMS-002` adds alliance-owned transfer planning on top of the accepted Kingdom/player/roster foundation delivered by `KINGDOMS-001`.

The increment gives alliance leadership one controlled workspace to answer:

- who intends to stay, leave, or join during a transfer cycle;
- which destination Kingdom is planned for outgoing participants;
- which incoming players are being coordinated toward the alliance's Kingdom;
- which players are intended to move together as a transfer group;
- who is coordinating each group;
- whether a participant is ready, blocked, confirmed, or no longer participating; and
- which confirmed transfers have been explicitly handed off to the accepted roster workflow.

The workflow is planning and coordination only. It does not predict transfer eligibility, scrape game data, automate moves, advertise players publicly, rank destinations, or recommend who should leave.

## 2. Product outcome

Alliance members can see the transfer plan relevant to their alliance without relying on spreadsheets or chat-only coordination. Authorized Kingdoms managers can maintain participant intent, destination, group assignment, readiness and completion state while preserving an attributable history of privileged changes.

The increment reuses the accepted `KINGDOMS-001` identity and tenancy model rather than inventing a separate transfer identity system.

## 3. Core business rules

### Alliance tenancy remains authoritative

Transfer plans, participants, groups, coordinator assignments, readiness state, blockers, manager notes and completion records are alliance-owned tenant data.

Sharing a `Kingdom`, `KingdomPlayer`, destination Kingdom, player name or transfer group label never grants one alliance access to another alliance's plan.

All submitted plan, participant, group, roster, membership, player and Kingdom identifiers are re-resolved under the active Alliance and the specific transfer-plan boundary before mutation.

### Application identity, membership and game identity remain separate

`User`, `AllianceMembership`, `KingdomPlayer` and transfer participation remain distinct concepts.

Outgoing and staying participants normally reference an existing same-alliance roster entry. Incoming participants may be planned before they become alliance members or roster entries, so an incoming participant may carry an optional neutral `KingdomPlayer` reference plus plan-scoped observed identity fields.

Display name alone is never sufficient to merge or relink neutral game identity. Stable game-player identifiers follow the accepted `KINGDOMS-001` matching rules.

### Direction is explicit and non-punitive

Each active participant has one planning direction for the transfer cycle:

- `staying` — expected to remain with the alliance/current Kingdom;
- `outgoing` — expected to leave for another Kingdom; or
- `incoming` — expected to arrive into the alliance's Kingdom.

Direction is a planning statement, not an assessment of player value. The product must not create automatic scores, rank participants, recommend removals, or infer direction from power trends.

### Destination rules

An outgoing participant may have a destination Kingdom. An incoming participant's destination is the transfer plan's captured home Kingdom, representing the alliance destination for that cycle. A staying participant does not have a transfer destination.

A transfer plan captures the alliance's home Kingdom when the plan is created/opened. If the Alliance Kingdom association changes while an active transfer plan exists, transfer mutations fail closed until an authorized manager explicitly reconciles the plan. The system does not silently retarget incoming participants.

Destination Kingdom is planning metadata only. Selecting a destination does not move a `KingdomPlayer`, alter the Alliance Kingdom association, or mutate another alliance.

### Transfer groups are coordination cohorts

A transfer group is an alliance-owned planning cohort. It can carry a name, optional destination Kingdom, coordinator, status and manager-only notes.

An outgoing participant assigned to a destination-bound group must be compatible with that group's destination. Incoming groups target the plan's captured home Kingdom. Staying participants are not treated as moving members of a transfer group.

Group membership must not alter authorization. A coordinator assignment identifies operational responsibility; it does not grant `kingdoms.manage` or bypass active-alliance authorization.

### Readiness is manual and explainable

Readiness is explicitly maintained from known information. Initial states are:

- `not_started`;
- `preparing`;
- `ready`;
- `blocked`;
- `confirmed`; and
- `withdrawn`.

A blocked participant may carry one or more manager-maintained blockers with clear text/status. The platform does not infer eligibility or readiness from power, spending, inventory, external game state, or undocumented mechanics.

Member-facing views may show approved direction/group/readiness information. Private coordinator notes, blocker detail designated as management-only, and mutation history remain restricted to `kingdoms.manage`.

### Completion is explicit

A planned transfer never automatically changes the accepted roster.

When an authorized manager explicitly confirms real-world completion:

- an incoming participant may be linked to or created as an alliance roster entry through the accepted roster-domain action contract;
- an outgoing participant may be marked left through the accepted roster action contract; and
- a staying participant produces no roster lifecycle mutation.

Completion must be idempotent and must not duplicate roster entries, snapshots or history when retried.

Planning completion does not rewrite historical player snapshots and does not automatically change the neutral `KingdomPlayer` Kingdom relationship based only on an intended destination.

## 4. In-scope capabilities

### 4.1 Transfer cycles

Provide alliance-scoped transfer plans with:

- ULID identity;
- captured home Kingdom;
- title/label;
- optional planning window start/end dates;
- lifecycle state (`draft`, `open`, `locked`, `closed`, `cancelled`);
- created/updated timestamps;
- actor/audit provenance for privileged lifecycle changes; and
- no implicit recurring scheduler requirement.

Only one plan needs to be active/open for an alliance at a time unless implementation evidence proves multiple concurrent plans are required. The initial implementation should prefer the simpler single-active-plan invariant.

### 4.2 Transfer participants

Provide alliance-scoped participant records supporting:

- direction (`staying`, `outgoing`, `incoming`);
- existing roster-entry reference where applicable;
- optional neutral Kingdom-player reference for incoming planning;
- plan-scoped observed player name and stable game-player identifier when supplied under accepted identity rules;
- source Kingdom when known;
- destination Kingdom under the rules above;
- optional same-alliance membership link where applicable;
- readiness state;
- optional transfer-group assignment;
- private manager notes; and
- explicit withdrawn/completed timestamps where applicable.

Do not require an application account for an incoming player.

### 4.3 Transfer groups and coordinators

Provide alliance-owned groups with:

- group name;
- direction/context;
- destination Kingdom where applicable;
- same-alliance coordinator membership;
- group lifecycle state;
- member count derived from scoped participants;
- private manager notes; and
- auditable assignment changes.

Coordinator membership must belong to the same Alliance and must be revalidated when changed.

### 4.4 Readiness and blockers

Provide an explicit readiness workflow and manager-maintained blockers.

Blockers must be attributable and mutable without destroying the historical fact that a participant previously had a different readiness state. The implementation may use append-oriented status history or equivalent auditable state-transition records; final design is locked in `K2-P0`.

No automatic readiness calculation, transfer eligibility engine or player score is approved.

### 4.5 Member and management views

Provide an authenticated alliance transfer-planning workspace.

Ordinary member visibility uses `alliance.view` and must exclude private manager notes, restricted blocker detail, internal coordinator notes and privileged audit metadata.

Management surfaces require `kingdoms.manage` and support plan lifecycle, participant direction/destination, group assignment, coordinator assignment, readiness/blocker maintenance and explicit completion.

### 4.6 Explicit roster handoff

Use existing `KINGDOMS-001` roster actions rather than duplicating roster mutation logic.

Completion re-resolves all references under the active Alliance, uses existing roster identity/membership invariants, records attributable audit evidence and emits internal transactional-outbox events when state materially changes.

## 5. Authorization model

`KINGDOMS-002` reuses the accepted authorization model:

- ordinary authenticated transfer-plan visibility: `alliance.view`;
- transfer-plan, participant, group, readiness and completion mutations: `kingdoms.manage`;
- Alliance-to-Kingdom association remains `alliance.manage` and is not moved into the transfer workflow.

Built-in role defaults remain unchanged from `KINGDOMS-001`: Owner, Leader and Officer receive `kingdoms.manage`; other built-in roles do not. Custom-role permission union semantics remain authoritative.

Privileged mutations require recent password confirmation where the accepted Kingdoms mutation pattern requires it, especially plan lifecycle, destination/group changes and completion/handoff operations.

Coordinator assignment is workflow metadata only and never confers authorization.

Platform administrators do not implicitly become transfer managers. Cross-tenant support requires an explicit Platform-domain workflow.

## 6. Data ownership

| Concept | Ownership | Tenant scope |
| --- | --- | --- |
| Kingdom | Kingdoms | Global reference |
| Kingdom player identity | Kingdoms | Global neutral identity/reference |
| Alliance roster entry / snapshots | Kingdoms | Alliance-scoped, existing `KINGDOMS-001` |
| Transfer plan | Kingdoms | Alliance-scoped |
| Transfer participant | Kingdoms | Alliance-scoped |
| Transfer group | Kingdoms | Alliance-scoped |
| Transfer readiness/blocker history | Kingdoms | Alliance-scoped |
| Coordinator membership reference | Memberships reference | Same-alliance only |
| Audit event | Audit | Correlated to actor/alliance as applicable |
| Durable internal event | Platform outbox | Alliance-scoped where tenant data is involved |

Global Kingdom/KingdomPlayer records must not contain transfer-plan notes, readiness, blockers or alliance-private coordination state.

## 7. Cross-domain contracts

### Kingdoms roster

Transfer planning consumes the accepted roster/player query and action contracts. It must not reach around them to mutate roster persistence directly.

### Alliances

The active Alliance and its captured home Kingdom establish the tenant and destination context. Alliance Kingdom changes are reconciled explicitly rather than silently rewriting open transfer plans.

### Memberships and Identity

A coordinator or linked participant membership must belong to the active Alliance. Incoming players do not require an application account or membership before arrival.

### Recruitment

No Recruitment-domain integration is required for `KINGDOMS-002`. A future increment may explicitly connect accepted recruitment candidates to transfer participants if product scope approves the cross-domain lifecycle.

### Integrations

No public Transfer/Kingdoms API or webhook contract is introduced. New `kingdoms.transfer_*` outbox events remain internal and must be excluded from generic external webhook fan-out unless a later integration increment explicitly approves exposure.

## 8. Delivery slices

### Slice A — Transfer cycle foundation

- transfer-plan persistence and lifecycle;
- captured home-Kingdom invariant;
- tenant-scoped read/manage routes;
- `alliance.view` / `kingdoms.manage` authorization;
- audit/outbox boundaries; and
- architecture/domain documentation.

### Slice B — Participant direction and destination

- incoming/outgoing/staying participant records;
- roster/player/membership linking rules;
- source/destination Kingdom validation;
- no-name-only identity merging;
- member/manager participant views; and
- tenant-isolation/object-ID tests.

### Slice C1 — Transfer groups and coordinators

- transfer groups;
- destination-compatible group assignment;
- same-alliance coordinator assignment;
- manager notes and group lifecycle; and
- coordinator-is-not-authorization regression coverage.

### Slice C2 — Readiness and blocker workflow

- readiness state machine;
- blocker/history model;
- manager workflow and member-safe presentation;
- deterministic status transitions; and
- accessibility/operational diagnostics.

### Slice D — Explicit completion and roster handoff

- confirmed incoming handoff to accepted roster actions;
- confirmed outgoing handoff to mark-left behavior;
- staying completion with no roster lifecycle mutation;
- idempotent retries;
- transaction/audit/outbox integration; and
- end-to-end transfer-cycle presentation.

A final hardening phase validates the complete dependency stack end to end before `KINGDOMS-002` can become Accepted.

## 9. Explicitly out of scope

`KINGDOMS-002` does not implement:

- transfer marketplace, public player advertising or public recruitment listings;
- destination/alliance rankings or automated destination recommendations;
- automated decisions about who should stay or leave;
- automatic readiness/eligibility calculations from power or other game data;
- transfer pass/ticket/resource optimization unless separately approved with authoritative game rules;
- scraping, OCR, bots or undocumented/unapproved Kingshot APIs;
- automated transfer execution;
- cross-alliance visibility into another alliance's transfer plan;
- diplomacy/NAP/ally/rival management (`KINGDOMS-003` candidate scope);
- automated game-data ingestion (`KINGDOMS-004` candidate scope);
- opt-in cross-alliance aggregates/rankings (`KINGDOMS-005` candidate scope);
- public Kingdoms/transfer APIs or webhook contracts; or
- AI-generated player scoring, punitive recommendations or forced roster decisions.

Deferred capabilities must not be partially introduced as dormant schema, routes or UI placeholders.

## 10. Security, privacy and abuse requirements

Acceptance requires review of at least:

- cross-alliance plan/participant/group disclosure;
- object-ID tampering and scoped binding;
- private notes and blocker-detail leakage;
- coordinator assignment used as a privilege-escalation attempt;
- incoming identity ambiguity and accidental player merge;
- destination manipulation and stale home-Kingdom context;
- duplicate completion / roster corruption on retries;
- misleading readiness state;
- punitive or coercive use of transfer status; and
- accidental external webhook/API exposure.

Game-facing information is not automatically public merely because it may be observable in-game.

## 11. Operational and observability requirements

The increment must provide enough structured diagnostics to investigate:

- plan lifecycle failures;
- participant/group validation failures;
- authorization/tenant failures;
- home-Kingdom drift;
- readiness/blocker transition failures;
- completion/roster-handoff failures; and
- outbox publication failures.

Private notes and sensitive blocker text must not be written into general application logs.

No recurring scheduler, crawler or external ingestion worker is required for the initial manual planning workflow.

## 12. Testing requirements

Acceptance includes:

- unit tests for lifecycle and transition rules;
- feature tests for plan, participant, group, readiness and completion workflows;
- authorization tests for `alliance.view` and `kingdoms.manage`;
- tenant-isolation tests across every submitted object identifier;
- same-alliance coordinator/membership invariant tests;
- incoming identity ambiguity tests;
- home-Kingdom drift/fail-closed tests;
- group destination-compatibility tests;
- completion idempotency and roster-handoff tests;
- audit/outbox assertions;
- accessibility validation for member and management planning surfaces;
- migration rollback/reapply validation; and
- realistic-volume query-shape validation for transfer-cycle views.

## 13. Acceptance criteria

`KINGDOMS-002` is complete only when all of the following are true:

1. An alliance can create and manage an alliance-scoped transfer cycle with a captured home Kingdom.
2. Authorized managers can track incoming, outgoing and staying participants without requiring every incoming player to have an application account.
3. Outgoing destinations and incoming home-Kingdom rules are validated without mutating global/player/Alliance identity merely because a move is planned.
4. Transfer groups and same-alliance coordinators can be managed without coordinator assignment granting authorization.
5. Readiness and blockers are manual, explainable and auditable; missing information is not converted into a score or inferred eligibility.
6. Ordinary members can see approved transfer-plan information while manager notes, restricted blockers and privileged history remain protected.
7. A confirmed incoming/outgoing transfer can be explicitly handed off to the accepted roster lifecycle without duplicate mutations on retry.
8. Every read and mutation preserves active-Alliance tenancy even when multiple alliances share Kingdom/player references or destinations.
9. Privileged changes are authorized, password-confirmed where required, audited and durably represented through internal outbox events.
10. No public Kingdoms/transfer API or webhook exposure is introduced.
11. Security, accessibility, migration, query-shape, operations and end-to-end acceptance gates pass on the complete stack.
12. Current capability documentation is updated from Planned to Implemented only after the acceptance gate passes.

Real production cutover remains a separate approval decision and is not implied by repository/product acceptance.