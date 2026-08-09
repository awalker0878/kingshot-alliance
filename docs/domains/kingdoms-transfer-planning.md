# Kingdoms transfer planning

[← Kingdoms](kingdoms.md)

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice C2 / `K2-P4` candidate on validated Slice C1  
**Slice B evidence:** [KINGDOMS-002 Slice B validation](../product/kingdoms-transfer-planning-slice-b-validation.md)  
**Slice C1 evidence:** [KINGDOMS-002 Slice C1 validation](../product/kingdoms-transfer-planning-slice-c1-validation.md)

`KINGDOMS-002` is an alliance-owned planning workflow layered on the accepted `KINGDOMS-001` Kingdom/player/roster foundation. Slice C2 adds manual readiness, private blockers and attributable transition history without implementing inferred eligibility, transfer resources, transfer execution or roster completion.

## Ownership and tenancy

`TransferPlan`, `TransferParticipant`, `TransferGroup`, `TransferBlocker`, and `TransferReadinessTransition` are Kingdoms-domain tenant data owned by one Alliance. `Kingdom` and `KingdomPlayer` remain global neutral reference data only.

Every transfer read/mutation is constrained by the active Alliance and selected transfer plan. Submitted plan, participant, group, blocker, roster, membership, source-Kingdom, and destination-Kingdom values are re-resolved under the applicable domain boundary.

Sharing the same Kingdom or neutral player reference never grants another Alliance access to transfer intent, group assignments, coordinator responsibility, readiness, blockers, manager notes, membership linkage, or destination planning.

## Transfer plan lifecycle

The validated plan lifecycle remains:

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

Participant, group, readiness and blocker changes are permitted only while the plan is `draft` or `open`. `locked`, `closed`, and `cancelled` plans are read-only for Slice C2.

The plan captures immutable `home_kingdom_id`. If the Alliance's current Kingdom later differs, transfer mutations fail closed. Cancellation remains the stale-plan recovery path.

## Participant directions

Slice B direction semantics remain authoritative:

- `staying` — active/tracked alliance roster player not planned to move;
- `outgoing` — active/tracked alliance roster player planned or potentially planned to move away; and
- `incoming` — player planned to arrive in the plan home Kingdom, potentially before site membership or roster entry exists.

Incoming destination is always the plan home Kingdom. Outgoing destination may be undecided or another active Kingdom. Staying has no transfer destination. Destination planning never mutates `KingdomPlayer.kingdom_id`.

## Transfer groups

Slice C1 group rules remain authoritative. A `TransferGroup` is alliance/plan-scoped coordination data with name, incoming/outgoing context, compatible destination, active/archived lifecycle, optional same-alliance coordinator and manager-only notes.

A participant may belong to at most one group and may remain unassigned. Staying participants cannot be assigned to moving groups. Group/participant direction and outgoing destination compatibility is revalidated from both mutation directions and fails transactionally rather than silently rewriting planning intent.

Coordinator assignment is workflow responsibility only. It never grants `kingdoms.manage`, changes roles/permissions, or bypasses password confirmation.

## Readiness

Each transfer participant has one explicit current readiness state:

- `not_started` — readiness work has not begun;
- `preparing` — readiness work is underway;
- `ready` — management has explicitly marked the participant ready;
- `blocked` — readiness is blocked and at least one active blocker exists when entering this state;
- `confirmed` — management has explicitly confirmed the planning/readiness state; and
- `withdrawn` — terminal planning state for a participant no longer active in the transfer cycle.

Readiness is manual workflow state. It is not eligibility, transfer execution, player quality, value, priority, or a prediction.

The application does not calculate readiness from power, spending, inventory, transfer passes, game activity, external APIs, scraped data, undocumented mechanics, or any score.

### Allowed transitions

The initial C2 state machine permits:

```text
not_started → preparing | blocked | withdrawn
preparing   → ready | blocked | withdrawn
ready       → preparing | blocked | confirmed | withdrawn
blocked     → preparing | ready | withdrawn
confirmed   → ready | blocked | withdrawn
withdrawn   → terminal
```

Submitting the current state is idempotent. Other direct jumps fail validation so intermediate workflow meaning and history are not silently skipped.

Entering `blocked` requires at least one active blocker. A participant cannot be marked `ready` or `confirmed` while active blockers remain. Leaving `blocked` for an active readiness state also requires all active blockers to be resolved first.

Resolving blockers never chooses the next readiness state. In particular, resolving the final blocker leaves a participant `blocked` until an authorized manager explicitly selects `preparing` or `ready`.

`confirmed` is planning/readiness state only. It does not create/update a roster entry, mark a roster player left, move a neutral `KingdomPlayer`, or execute any K2-P5 completion/handoff behavior.

## Readiness history

Every material readiness change creates an append-oriented `TransferReadinessTransition` containing:

- Alliance, plan and participant scope;
- prior readiness state;
- resulting readiness state;
- actor user when still retained; and
- transition timestamp.

Transition rows are historical evidence and are not rewritten when the current state changes again.

Existing participants that were already withdrawn before the C2 migration are normalized to current readiness `withdrawn` without fabricating an actor or synthetic historical transition.

Withdrawal through the normal transfer workflow delegates to the readiness transition action so `readiness_state = withdrawn`, `withdrawn_at`, transition history, audit evidence and the existing participant-withdrawn event remain aligned. Repeated withdrawal is idempotent.

## Blockers

A `TransferBlocker` is alliance/plan/participant-scoped management data. It stores:

- lifecycle state (`active` or `resolved`);
- required manager-maintained summary;
- optional private details;
- creator actor when retained;
- resolver actor when retained;
- resolution timestamp; and
- normal timestamps.

Blockers do not automatically change readiness when created or resolved. They are explainable observations/coordination records, not an automatic eligibility engine.

Resolved blockers remain historical records. Resolution retries are idempotent.

Withdrawn participants cannot receive or change blockers.

## Visibility and authorization

- ordinary transfer view: `alliance.view`;
- transfer management and readiness board: `kingdoms.manage`;
- plan/participant/group/readiness/blocker mutations: `kingdoms.manage` plus recent password confirmation.

Ordinary member payloads may expose approved transfer direction, destination, group/coordinator display information, and **current readiness state**.

They exclude:

- blocker IDs, summary/details and lifecycle metadata;
- readiness transition history and actor data;
- participant/group manager notes;
- internal group/participant assignment IDs; and
- private membership metadata.

The manager Readiness board exposes blockers and transition history only after the normal `kingdoms.manage` authorization check.

## Query shape

Management readiness reads eager-load blocker creator/resolver and readiness-transition actor relations in bounded relation queries. Rendering additional participant rows must not introduce per-participant blocker/history database reads.

The C2 feature suite exercises realistic multi-participant board data with a bounded query-count assertion.

## Audit and outbox

C2 emits internal attributable events for material changes:

- `kingdoms.transfer_readiness_changed`;
- `kingdoms.transfer_blocker_created`;
- `kingdoms.transfer_blocker_resolved`; and
- existing `kingdoms.transfer_participant_withdrawn` when withdrawal occurs.

Audit/outbox metadata may contain scoped IDs, from/to readiness states, blocker lifecycle state and active-blocker counts. Private blocker summary/details and manager notes are never copied into audit/outbox payloads.

`kingdoms.*` remains excluded from generic external webhook fan-out. C2 introduces no public API or webhook contract.

## Diagnostics

Structured failure diagnostics should identify safe object IDs/state/invariant context without logging private blocker text or manager notes.

Expected fail-closed causes include:

- active-Alliance mismatch or cross-tenant submitted IDs;
- missing `kingdoms.manage`;
- stale password confirmation;
- locked/closed/cancelled plan;
- home-Kingdom drift;
- invalid readiness jump;
- entering `blocked` without an active blocker;
- leaving `blocked` while active blockers remain; and
- attempting `ready`/`confirmed` with active blockers.

## Explicit Slice C2 non-capabilities

Slice C2 does not implement:

- transfer passes/tickets/resources or eligibility rules;
- inferred/automatic readiness or eligibility;
- automated destination ranking or stay/leave recommendations;
- transfer execution or roster completion/handoff;
- automatic bulk completion of confirmed participants;
- marketplace/public advertising;
- diplomacy/NAP intelligence;
- cross-alliance transfer visibility;
- scraping, OCR, bots, or undocumented game APIs;
- AI/punitive player scoring; or
- public Kingdoms API/webhook contracts.

`KINGDOMS-002` remains in progress until K2-P5 and whole-increment K2-P6 acceptance pass.
