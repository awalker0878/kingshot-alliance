# Kingdoms transfer planning

[← Kingdoms](kingdoms.md)

**Increment:** `KINGDOMS-002`  
**Current delivery:** **Accepted** — `K2-P0` through `K2-P6` complete  
**Acceptance evidence:** [KINGDOMS-002 exit report](../product/kingdoms-transfer-planning-exit-report.md)  
**Whole-increment security:** [KINGDOMS-002 security review](../security/kingdoms-transfer-planning-security-review.md)

`KINGDOMS-002` is an alliance-owned planning workflow layered on the accepted `KINGDOMS-001` Kingdom/player/roster foundation. The accepted runtime includes explicit real-world completion and roster handoff while continuing to exclude inferred eligibility, transfer resources, automated transfer execution and public/cross-alliance transfer workflows.

## Ownership and tenancy

`TransferPlan`, `TransferParticipant`, `TransferGroup`, `TransferBlocker`, `TransferReadinessTransition`, and `TransferCompletion` are Kingdoms-domain tenant data owned by one Alliance. `Kingdom` and `KingdomPlayer` remain global neutral reference data only.

Every transfer read/mutation is constrained by the active Alliance and selected transfer plan. Submitted plan, participant, group, blocker, roster, membership, source-Kingdom, and destination-Kingdom values are re-resolved under the applicable domain boundary.

Sharing the same Kingdom or neutral player reference never grants another Alliance access to transfer intent, group assignments, coordinator responsibility, readiness, blockers, completion provenance, manager notes, membership linkage, or destination planning.

## Transfer plan lifecycle

The lifecycle is:

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

Participant, group, readiness, blocker and withdrawal planning changes are permitted only while the plan is `draft` or `open`.

`locked` is the explicit real-world handoff phase. Transfer completion is permitted only while the plan is `locked`. A locked plan cannot become `closed` while any non-withdrawn participant lacks a completion record.

The plan captures immutable `home_kingdom_id`. If the Alliance's current Kingdom later differs, planning and completion mutations fail closed. Cancellation remains the stale-plan recovery path; the system does not silently retarget a plan or incoming participant.

## Participant directions

- `staying` — active/tracked alliance roster player expected to remain;
- `outgoing` — active/tracked alliance roster player expected to leave; and
- `incoming` — player expected to arrive in the plan home Kingdom, potentially before site membership or roster entry exists.

Incoming destination is always the plan home Kingdom. Outgoing destination may be undecided or another active Kingdom. Staying has no transfer destination. Destination planning never mutates `KingdomPlayer.kingdom_id`.

## Groups and coordinators

A transfer group is alliance/plan-scoped coordination data. A participant may belong to at most one group. Staying participants cannot be moving-group members. Group/participant direction and outgoing destination compatibility fail transactionally rather than silently rewriting intent.

Coordinator assignment is workflow responsibility only. It never grants `kingdoms.manage`, changes roles/permissions, bypasses recent password confirmation, or grants completion authority.

## Readiness and blockers

Readiness states are:

- `not_started`;
- `preparing`;
- `ready`;
- `blocked`;
- `confirmed`; and
- terminal `withdrawn`.

Readiness is manual workflow state, not eligibility, transfer execution, player value or an automatic prediction. Entering `blocked` requires an active blocker. `ready` and `confirmed` cannot coexist with active blockers. Resolving the final blocker never chooses the next readiness state.

`confirmed` remains planning state. It does **not** change the roster. The completion contract requires `confirmed` before handoff so leadership must explicitly confirm planning readiness before separately confirming the real-world outcome.

Readiness transitions remain append-oriented and actor-attributable. Blocker text/details remain management-private and never enter ordinary member payloads or durable audit/outbox metadata.

## Explicit completion and roster handoff

Completion is represented by one `TransferCompletion` record per participant. The participant uniqueness constraint is the durable idempotency boundary.

A completion contains:

- Alliance, transfer-plan and participant scope;
- participant direction at handoff;
- resulting same-alliance roster entry where applicable;
- completing actor when still retained; and
- completion timestamp.

Completion is allowed only when:

1. the plan belongs to the active Alliance;
2. the plan is `locked`;
3. the Alliance's current Kingdom still matches the plan's captured home Kingdom;
4. the participant belongs to the same Alliance and plan;
5. the participant is active/not withdrawn;
6. readiness is explicitly `confirmed`; and
7. any submitted existing roster result resolves beneath the same Alliance.

The completion action locks Alliance → plan → participant before checking for an existing completion. A retry therefore returns the existing completion before any delegated roster side effect is repeated.

### Incoming

Incoming completion requires an explicit manager action after the player actually arrives.

If no existing roster result is selected, completion delegates to accepted `SaveRosterEntry` creation using the plan participant's observed identity and stable game-player identifier when available. The accepted roster action resolves the roster player under the Alliance's current/home Kingdom identity contract.

If a manager explicitly selects an existing active/tracked same-alliance roster entry, stable game-player identifiers must agree when the participant has one. Existing roster name, game role, lifecycle state, joined date, source, manager notes and membership linkage are preserved; a participant membership may be added only when the existing roster entry does not already link another membership.

The application never chooses an existing roster entry by display name alone.

The source/planning neutral `KingdomPlayer` is not rewritten merely because the participant arrived. The roster result is captured by the completion record.

### Outgoing

Outgoing completion re-resolves the participant's captured same-alliance roster entry and validates its neutral player binding before delegating to accepted `MarkRosterEntryLeft` behavior.

The delegated action is itself idempotent when the roster entry is already left. Historical player snapshots and neutral identity are preserved.

### Staying

Staying completion re-resolves the same-alliance roster binding and records the transfer outcome without changing roster lifecycle state. This is an explicit transfer-plan outcome, not a roster update.

### Snapshots

Completion never fabricates a `PlayerSnapshot`. A snapshot exists only when an actual observation is supplied through the accepted snapshot contract. Completion does not rewrite existing snapshot history.

## Closing a cycle

A locked plan may close only when every non-withdrawn participant has one completion record. Withdrawn participants remain historical planning outcomes and do not need completion.

There is intentionally no “complete all ready/confirmed players” route or action. Each real-world completion is explicitly confirmed participant-by-participant.

## Visibility and authorization

- ordinary transfer view: `alliance.view`;
- transfer management, readiness and completion workspaces: `kingdoms.manage`;
- all transfer mutations including completion: `kingdoms.manage` plus recent password confirmation.

Ordinary member payloads may expose approved transfer planning information, current readiness and safe completion time.

They exclude blocker details/history, participant/group manager notes, completion actor, completion record IDs, selected/result roster IDs, and other privileged handoff provenance.

Manager completion presentation may show completion actor and resulting roster entry after `kingdoms.manage` authorization.

## Audit and outbox

The accepted runtime emits internal `kingdoms.transfer_participant_completed` audit/outbox evidence containing scoped IDs, direction and resulting roster-entry ID. Delegated accepted roster actions continue to emit their existing roster audit/outbox evidence when they materially mutate roster state.

Private transfer notes/blocker text are not copied into completion event payloads.

`kingdoms.*` remains excluded from generic external webhook fan-out. `KINGDOMS-002` introduces no public API or webhook contract.

## Diagnostics

Expected completion failures include:

- missing `kingdoms.manage` or stale password confirmation;
- cross-Alliance plan/participant/roster IDs;
- a plan that is not `locked`;
- home-Kingdom drift;
- withdrawn or non-`confirmed` participant;
- stale/missing roster binding for outgoing/staying;
- explicitly selected incoming roster identity mismatch; and
- attempting to close a locked plan with an incomplete active participant.

Structured diagnostics may identify safe IDs/state/invariant context but must not log private blocker details or manager notes.

## Query shape

Participant queries eager-load completion with their normal bounded relation set. Manager reads additionally eager-load completion actor and resulting roster/player relationships. Rendering participant rows must not introduce per-participant completion queries.

Whole-increment acceptance includes a realistic-volume query gate with 150 participants and 20 transfer groups, readiness history, blockers and completion projections, with bounded SELECT-query count.

## Explicit non-capabilities

Accepted transfer planning does not implement:

- transfer passes/tickets/resources or eligibility rules;
- inferred/automatic readiness or eligibility;
- automated destination ranking or stay/leave recommendations;
- bulk completion;
- automated in-game transfer execution;
- marketplace/public advertising;
- diplomacy/NAP intelligence;
- cross-alliance transfer visibility;
- scraping, OCR, bots, or undocumented game APIs;
- AI/punitive player scoring; or
- public Kingdoms API/webhook contracts.

`KINGDOMS-002` is **Accepted** repository/product capability. Real production cutover remains separately **not yet approved**.
