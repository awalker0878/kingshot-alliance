# KINGDOMS-002 implementation plan

[← Kingdoms transfer planning product increment](kingdoms-transfer-planning-increment.md)

**Status:** Planned  
**Scope ID:** `KINGDOMS-002`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` scope and implementation  
**Important:** These are implementation phases inside `KINGDOMS-002`; they are not a continuation of the historical program phase numbering.

## 1. Purpose

This plan converts the approved `KINGDOMS-002` transfer-planning scope into independently reviewable implementation phases while preserving one end-to-end acceptance boundary for the increment.

The implementation must continue the accepted Kingdoms/platform rules:

- domain-first runtime ownership under `app/Domain/<Domain>`;
- explicit active-Alliance tenancy for tenant-owned observations and workflows;
- global Kingdom/KingdomPlayer data remains neutral reference identity only;
- `alliance.view` for ordinary authenticated visibility and `kingdoms.manage` for transfer mutations;
- assigned coordinator is workflow responsibility, never authorization;
- policy/permission authorization rather than controller role-name checks;
- thin controllers and business logic in actions/services/queries;
- transactional persistence for related business mutations;
- audit evidence for privileged changes;
- transactional outbox for durable internal side effects;
- append-oriented or equivalently auditable transition history where status changes matter;
- reuse accepted roster actions for roster lifecycle handoff instead of duplicating persistence logic;
- no compatibility shims after a migration is complete;
- code and tests authoritative for exact runtime behavior;
- security, accessibility, operations and living documentation updated in the same slice when affected; and
- no partially implemented future diplomacy, ingestion, marketplace, ranking or public API capability hidden behind dormant schema/UI.

## 2. Phase summary

| Phase | Initial status | Outcome | Primary slice |
| --- | --- | --- | --- |
| `K2-P0` | Planned | Design, identity, tenancy and lifecycle contract locked | Slice A preparation |
| `K2-P1` | Planned | Transfer-cycle foundation | Slice A |
| `K2-P2` | Planned | Participant direction and destination | Slice B |
| `K2-P3` | Planned | Transfer groups and coordinators | Slice C1 |
| `K2-P4` | Planned | Readiness and blocker workflow | Slice C2 |
| `K2-P5` | Planned | Explicit completion and roster handoff | Slice D |
| `K2-P6` | Planned | Whole-increment hardening and acceptance | Whole increment |

Every implementation slice must leave the repository internally consistent, migratable and testable. `K2-P6` validates the complete dependency stack rather than compensating for incomplete earlier slices.

## 3. K2-P0 — Design and contract lock

### Objective

Lock transfer identity, plan lifecycle, tenant boundaries, visibility and roster-handoff semantics before runtime schema is changed.

### Required decisions

- Confirm a `TransferPlan` is alliance-owned and captures the Alliance's current Kingdom as `home_kingdom_id` when created/opened.
- Confirm the initial invariant is at most one `open` transfer plan per Alliance.
- Confirm plan lifecycle states: `draft`, `open`, `locked`, `closed`, `cancelled`.
- Confirm participant directions: `staying`, `outgoing`, `incoming`.
- Confirm readiness states: `not_started`, `preparing`, `ready`, `blocked`, `confirmed`, `withdrawn`.
- Confirm outgoing/staying participants normally resolve through the active Alliance roster.
- Confirm incoming participants may exist before application membership or alliance roster entry and therefore may carry an optional neutral `KingdomPlayer` reference plus plan-scoped observed identity fields.
- Confirm display name alone never merges neutral identity.
- Confirm destination semantics:
  - incoming destination = plan home Kingdom;
  - outgoing destination may be another Kingdom;
  - staying has no transfer destination.
- Confirm changing the Alliance Kingdom while a plan is active causes transfer mutations to fail closed until explicit reconciliation.
- Confirm a transfer group is alliance-owned coordination data and coordinator membership is same-alliance only.
- Confirm coordinator assignment never grants `kingdoms.manage`.
- Lock which readiness/blocker details are member-visible versus manager-only.
- Choose the transition-history representation for readiness/group/direction changes so prior meaningful workflow state remains auditable.
- Confirm completion is explicit and idempotent and delegates roster mutation to accepted `KINGDOMS-001` actions.
- Confirm no plan action automatically reassigns a neutral `KingdomPlayer` to a destination Kingdom.
- Confirm new transfer outbox events remain internal and ineligible for generic external webhook fan-out.

### Verification gate

- No proposed global table contains alliance-private transfer notes, readiness, blockers or group assignments.
- No submitted ID path can cross the active-Alliance/transfer-plan boundary.
- No coordinator concept bypasses RBAC.
- No future marketplace/diplomacy/automated-ingestion field is added as a placeholder.
- Migration series has a tested development rollback path before Slice A runtime is accepted.

## 4. K2-P1 — Transfer-cycle foundation

### Objective

Introduce the alliance-owned transfer-cycle aggregate and authenticated member/manager entry points.

### Persistence

- Add `TransferPlan` under `app/Domain/Kingdoms/Models` using repository ULID conventions.
- Persist `alliance_id`, captured `home_kingdom_id`, label/title, optional start/end dates, lifecycle state and timestamps.
- Add indexes supporting active-Alliance/current-plan queries.
- Enforce the chosen single-open-plan invariant transactionally/database-side where practical.
- Do not add participant/group/readiness future columns to the plan table merely to support later slices.

### Domain behavior

- Add actions for create/open/lock/close/cancel plan.
- Re-resolve the active Alliance and captured home Kingdom before privileged lifecycle changes.
- Require `kingdoms.manage` and recent password confirmation for lifecycle mutations.
- Use transaction/locking semantics that prevent two concurrent requests opening conflicting plans.
- Emit attributable audit records and internal transactional-outbox events for material lifecycle changes.
- If the Alliance currently has no Kingdom, fail plan creation with clear validation rather than creating a context-free plan.

### UI

- Add Alliance → Transfers member view showing the current plan summary and approved safe metadata.
- Add Transfers management surface for authorized managers.
- Clearly distinguish draft/open/locked/closed/cancelled state.
- Do not show participant/group placeholders before those slices exist.

### Tests and exit criteria

- lifecycle and invalid-transition tests;
- single-open-plan concurrency/invariant tests;
- authorization/password-confirmation tests;
- no-Kingdom validation;
- captured-home-Kingdom assertions;
- cross-alliance submitted-plan ID tests;
- audit/outbox assertions;
- accessibility guard for transfer member/manage surfaces; and
- no external webhook exposure for the new event family.

Slice A is complete when an authorized alliance can manage one transfer cycle without exposing or pre-implementing participant functionality.

## 5. K2-P2 — Participant direction and destination

### Objective

Track who is staying, leaving or arriving while preserving the accepted distinction between application identity, alliance membership and game identity.

### Persistence

Add alliance/plan-scoped transfer participants with the minimum fields required for this slice:

- `transfer_plan_id` and `alliance_id`;
- direction;
- optional same-alliance `roster_entry_id`;
- optional neutral `kingdom_player_id` for incoming planning;
- plan-scoped observed player name;
- optional stable game-player identifier/provenance where supplied;
- optional source Kingdom;
- destination Kingdom under direction rules;
- optional same-alliance membership reference;
- manager-only notes;
- active/withdrawn timestamps; and
- created/updated actor provenance where needed.

Do not duplicate snapshot power/current roster fields into transfer participants.

### Domain behavior

- Add create/update/withdraw participant actions.
- Outgoing/staying roster entries must resolve under the active Alliance.
- Incoming participants may be unlinked from roster/membership.
- Same-alliance membership references are revalidated on every mutation.
- Stable game-player identity follows accepted Kingdoms rules; display-name-only automatic merge is prohibited.
- Incoming destination is normalized to the plan home Kingdom.
- Staying destination must be null.
- Outgoing destination may be null while undecided, but if present it must reference a valid active Kingdom and must not silently change neutral player identity.
- Plan must be mutable (`draft`/`open` as locked in `K2-P0`) for participant changes.
- Plan home-Kingdom drift causes mutations to fail closed.
- Privileged participant mutations are audited and produce internal outbox events.

### UI

- Add member-safe participant list grouped/filterable by direction.
- Add manager create/edit/withdraw workflow.
- Surface unresolved outgoing destinations explicitly rather than treating missing as current Kingdom.
- Surface incoming players without site accounts as valid planned participants.
- Exclude manager notes and privileged history from member payloads.

### Tests and exit criteria

- direction/destination invariant tests;
- outgoing/staying same-alliance roster binding tests;
- incoming-without-membership/roster tests;
- duplicate/name-collision identity tests;
- cross-alliance roster/membership/player-ID tampering tests;
- archived/invalid Kingdom destination tests;
- home-Kingdom drift tests;
- audit/outbox assertions; and
- member-vs-manager payload privacy tests.

Slice B is complete when direction and destination can be managed safely without transfer-group/readiness behavior hidden in the schema.

## 6. K2-P3 — Transfer groups and coordinators

### Objective

Coordinate players intended to move together without turning coordination assignment into a privilege model.

### Persistence

- Add alliance/plan-scoped `TransferGroup` records.
- Persist group name, direction/context, optional destination Kingdom, lifecycle state, optional same-alliance coordinator membership, manager-only notes and timestamps.
- Add participant-to-group assignment using the simplest invariant-preserving relationship; do not create a many-to-many model unless the approved workflow requires one participant in multiple groups.

### Domain behavior

- Add create/update/archive group actions and participant assignment/unassignment.
- Coordinator membership must belong to the active Alliance.
- Assignment never grants `kingdoms.manage`; every mutation still checks normal authorization.
- Outgoing participant destination must be compatible with a destination-bound outgoing group.
- Incoming group destination is the plan home Kingdom.
- Staying participants cannot be assigned as moving group members.
- Destination/group changes revalidate all affected participants transactionally; fail rather than silently rewriting incompatible participant intent.
- Group and assignment changes are audited and published through internal outbox events as appropriate.

### UI

- Add manager group board/list with destination and coordinator.
- Member view may show approved group/coordinator identity but never manager-only group notes.
- Provide deterministic participant ordering such as display name; do not create competitive ranking.

### Tests and exit criteria

- same-alliance coordinator tests;
- coordinator-without-permission regression tests;
- group destination compatibility tests;
- staying-assignment rejection tests;
- cross-alliance group/participant tampering tests;
- transaction rollback for incompatible bulk reassignment;
- audit/outbox assertions; and
- accessible group/assignment controls.

Slice C1 is complete when groups are coordination-only and authorization remains entirely permission-driven.

## 7. K2-P4 — Readiness and blocker workflow

### Objective

Give coordinators/managers an explainable planning state without introducing inferred eligibility, punitive scoring or destructive status overwrites.

### Persistence and transitions

- Add the readiness model locked in `K2-P0`.
- Preserve meaningful transition history through append-oriented status events or an equivalent auditable transition table.
- Add manager-maintained blockers with status/resolution metadata only as required by the approved workflow.
- Keep private blocker text and notes alliance-scoped and excluded from ordinary member payloads/logging.
- Do not store derived automatic readiness scores.

### Domain behavior

- Add explicit readiness transition actions.
- Define allowed transitions and reject invalid jumps where workflow meaning would be lost.
- `blocked` may require at least one active blocker if the chosen contract uses explicit blocker records.
- Resolving the final blocker does not automatically mark a participant `ready`; the manager explicitly chooses readiness.
- `confirmed` is a planning/readiness state and must not by itself perform roster completion; handoff belongs to `K2-P5`.
- `withdrawn` preserves participant/history and removes the participant from active coordination counts.
- Audit privileged transitions and write durable internal outbox events without embedding private blocker text in event payloads.

### UI and observability

- Add readiness filters and clear blocked/ready/confirmed distinctions.
- Member view shows only the approved safe readiness summary.
- Manager view shows blocker management and transition history.
- Structured diagnostics identify failed transitions and invalid plan state without logging private notes.

### Tests and exit criteria

- transition-state tests;
- blocker creation/resolution tests;
- no-auto-ready regression tests;
- withdrawn/history preservation tests;
- manager-only blocker privacy tests;
- tenant-isolation/object-ID tampering tests;
- audit/outbox payload-safety tests;
- accessibility validation of status controls/history; and
- realistic participant-count query-shape tests for board/filter views.

Slice C2 is complete when readiness is manual, explainable and historically attributable.

## 8. K2-P5 — Explicit completion and roster handoff

### Objective

Close the planning lifecycle through explicit, idempotent roster handoff while reusing accepted `KINGDOMS-001` invariants.

### Incoming completion

- Require an explicit manager action after the player has actually arrived.
- Re-resolve plan, participant, active Alliance, plan home Kingdom and optional player/membership references.
- Use accepted Kingdoms roster actions to create/link/update the alliance roster entry.
- Do not merge identity solely by display name.
- Preserve existing roster/private data when linking to an existing accepted roster entry.
- Do not create a fake power snapshot unless an actual observation is supplied through the accepted snapshot contract.

### Outgoing completion

- Require explicit confirmation that the player actually left.
- Re-resolve the participant's same-alliance roster entry.
- Delegate to the accepted mark-left roster action rather than updating roster persistence directly.
- Preserve snapshot history and neutral identity.

### Staying completion

- Closing/confirming a staying participant performs no roster lifecycle mutation.
- Preserve the transfer-plan history as the record of the planning outcome.

### Transaction and idempotency

- Record a unique completion/handoff record or equivalent deterministic idempotency key so retries cannot duplicate roster lifecycle changes.
- Keep transfer completion and delegated roster mutation in one transaction when repository boundaries permit; otherwise use the accepted outbox/coordinated transaction pattern without partial silent success.
- Require `kingdoms.manage` plus recent password confirmation.
- Audit completion and emit internal outbox events.

### UI

- Add explicit completion actions with confirmation language distinguishing planned/confirmed readiness from actual roster handoff.
- Show completed outcome and linked roster result.
- Do not provide an automatic “complete all ready players” bulk action in the initial increment.

### Tests and exit criteria

- incoming roster-create/link tests;
- outgoing mark-left delegation tests;
- staying no-op roster tests;
- completion retry/idempotency tests;
- transaction rollback/partial-failure tests;
- stale home-Kingdom and stale roster-binding tests;
- no-fabricated-snapshot regression tests;
- authorization/password-confirmation tests;
- audit/outbox assertions; and
- end-to-end plan → participant → group → readiness → completion workflow tests.

Slice D is complete when real-world completion can be represented without duplicating or bypassing accepted roster business logic.

## 9. K2-P6 — Hardening and increment acceptance

### Objective

Validate the complete `KINGDOMS-002` contract end to end and produce acceptance evidence.

### Required review

- full Kingdoms domain-boundary review;
- tenant/security review covering transfer-plan disclosure, private notes/blockers, object-ID tampering, coordinator privilege confusion, incoming identity ambiguity and destination manipulation;
- abuse review confirming no punitive ranking/scoring/recommendation behavior was introduced;
- accessibility review of plan, participant, group, readiness/history and completion workflows;
- migration rollback/reapply validation from the accepted `KINGDOMS-001` baseline;
- query/index review using realistic transfer-plan/participant/group/history volume;
- completion idempotency and roster-handoff integrity review;
- Alliance Kingdom drift/reconciliation review;
- observability and operations documentation review;
- API/webhook review confirming transfer events remain internal and no undocumented public exposure was introduced;
- current capability matrix and Kingdoms domain/product index updates from Planned to Implemented only after acceptance; and
- dedicated `KINGDOMS-002` exit report with validated SHA and protected-check evidence.

### Acceptance gate

The complete stack must pass the repository's protected quality/security pipeline, including frontend quality/build, PHP quality/static analysis/tests, PostgreSQL migrations, dependency/security analysis, immutable-image build, staging validation, backup/restore and image scanning where those controls remain part of the accepted repository gate.

`KINGDOMS-002` remains Planned/In progress/Candidate until the exact final evidence is recorded. Repository/product acceptance does not itself approve a real production cutover.

## 10. Pull-request sequencing

Planned dependency order:

1. **Slice A / `K2-P1` — Transfer cycle foundation** (including final `K2-P0` decisions).
2. **Slice B / `K2-P2` — Participant direction and destination**.
3. **Slice C1 / `K2-P3` — Transfer groups and coordinators**.
4. **Slice C2 / `K2-P4` — Readiness and blockers**.
5. **Slice D / `K2-P5` — Explicit completion and roster handoff**.
6. **`K2-P6` — Whole-increment hardening, audits, documentation and acceptance record**.

Each PR should be stacked only when necessary, retain its own passing protected slice gate, and avoid compatibility code or dormant future-schema fields added solely to simplify later slices.

## 11. Suggested branch naming

- `agent/kingdoms-002-slice-a`
- `agent/kingdoms-002-slice-b`
- `agent/kingdoms-002-slice-c1`
- `agent/kingdoms-002-slice-c2`
- `agent/kingdoms-002-slice-d`
- `agent/kingdoms-002-acceptance`

The planning branch may be merged independently before Slice A begins so approved scope remains distinct from implementation evidence.