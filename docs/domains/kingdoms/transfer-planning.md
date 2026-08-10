# Kingdoms transfer planning

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as `KINGDOMS-002`  
**Owning domain:** `Kingdoms`

## 1. Purpose

`KINGDOMS-002` is an Alliance-owned transfer-planning workflow layered on the accepted Kingdom/player/roster foundation. It supports explicit planning, manual readiness/blockers, and explicit real-world completion/roster handoff while continuing to exclude inferred eligibility, resource optimization, bulk/automatic execution, and public/cross-Alliance transfer workflows.

## 2. Scope and non-scope

In scope:

- Alliance-owned transfer plans;
- incoming/outgoing/staying participant intent;
- outgoing destination planning;
- groups and same-Alliance coordinators;
- manual readiness transitions and blocker history;
- explicit per-participant completion; and
- accepted roster handoff for incoming/outgoing/staying outcomes.

Out of scope:

- transfer passes/tickets/resources/eligibility rules;
- automatic readiness/eligibility inference;
- destination ranking/stay-leave recommendations;
- bulk completion;
- automated in-game execution;
- marketplace/public advertising;
- automated diplomacy;
- cross-Alliance transfer visibility;
- scraping/OCR/bots/undocumented APIs;
- punitive/AI player scoring; and
- public Kingdoms API/webhook contracts.

## 3. Model and state

`TransferPlan`, `TransferParticipant`, `TransferGroup`, `TransferBlocker`, `TransferReadinessTransition`, and `TransferCompletion` are tenant-owned by one Alliance. `Kingdom`/`KingdomPlayer` remain neutral reference data.

### Plan lifecycle

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

Planning mutations occur only in `draft`/`open`. `locked` is the explicit real-world handoff phase. Completion is allowed only in `locked`. A locked plan cannot close while a non-withdrawn participant lacks completion.

The plan captures immutable `home_kingdom_id`. If the Alliance later points to another Kingdom, normal transfer mutations fail closed. Cancellation is the stale-plan recovery path; the system does not silently retarget the plan.

### Participant direction

- `staying` — active/tracked roster player expected to remain;
- `outgoing` — active/tracked roster player expected to leave; and
- `incoming` — player expected to arrive in the plan home Kingdom, potentially before site membership/roster exists.

Incoming destination is always the plan home Kingdom. Outgoing destination may be undecided/another active Kingdom. Staying has no transfer destination. Planning never mutates neutral `KingdomPlayer.kingdom_id`.

### Readiness

States are:

- `not_started`;
- `preparing`;
- `ready`;
- `blocked`;
- `confirmed`; and
- terminal `withdrawn`.

`confirmed` is planning state only, not proof that the in-game transfer occurred.

### Completion

One `TransferCompletion` exists per participant and records Alliance/plan/participant, direction at handoff, resulting same-Alliance roster entry where applicable, completing actor when retained, and completion time.

## 4. Invariants

1. Every transfer read/mutation is constrained by active Alliance and selected plan.
2. Neutral Kingdom/player identity never shares transfer intent/private state across tenants.
3. `home_kingdom_id` is captured/immutable for a plan; later Alliance-Kingdom drift fails mutations closed.
4. Participant/group/readiness/blocker planning changes happen only in `draft`/`open`.
5. Completion happens only in `locked`.
6. Locked plan cannot close until every non-withdrawn participant has completion.
7. Coordinator assignment is responsibility only and grants no `kingdoms.manage` authority.
8. Staying participants cannot be moving-group members.
9. Group/participant direction and outgoing destination compatibility fail transactionally instead of rewriting intent.
10. Entering `blocked` requires an active blocker.
11. `ready`/`confirmed` cannot coexist with active blockers.
12. Resolving the final blocker never automatically chooses next readiness.
13. Completion requires explicit `confirmed` readiness and is idempotent.
14. Completion never fabricates `PlayerSnapshot` history.

## 5. Workflows

### Plan transfer cycle

Leadership creates the cycle with captured home Kingdom, moves it through draft/open planning, locks it only when planning should hand off to real-world outcome confirmation, and closes only after all active participants are completed.

### Manage participants/destinations

Incoming/outgoing/staying semantics remain explicit. Outgoing destination planning may be undecided or active destination Kingdom; incoming destination is the home Kingdom; staying has no destination.

### Groups/coordinators

A participant belongs to at most one transfer group. Group/participant direction/destination compatibility is enforced. Coordinators are same-Alliance workflow references and do not change permissions.

### Readiness/blockers

Readiness is manually maintained. Transitions are append-oriented and actor-attributable. Blocker detail remains management-private and never enters ordinary member or generic audit/outbox payloads.

### Complete incoming

Incoming completion occurs only after the player actually arrives. If no existing roster result is selected, it delegates to accepted `SaveRosterEntry` using observed identity/stable ID when available.

If an existing active/tracked same-Alliance roster entry is explicitly selected, stable IDs must agree when the participant has one. Existing roster name/role/state/joined date/source/notes/membership linkage are preserved; participant membership may be added only when the existing entry does not already link another membership.

The application never chooses an existing roster entry by display name alone. Source/planning neutral KingdomPlayer is not rewritten merely because the participant arrived.

### Complete outgoing

Outgoing completion re-resolves the captured same-Alliance roster entry and neutral binding, then delegates to accepted `MarkRosterEntryLeft`. That delegated action is itself idempotent and preserves history/neutral identity.

### Complete staying

Staying completion validates the same-Alliance roster binding and records transfer outcome without changing roster lifecycle state.

### Close cycle

Only after every non-withdrawn participant has one completion may a locked plan close. There is intentionally no “complete all ready/confirmed players” action.

## 6. Authorization, tenancy and privacy

- ordinary transfer view: `alliance.view`;
- transfer management/readiness/completion: `kingdoms.manage`;
- all transfer mutations: `kingdoms.manage` plus recent password confirmation.

Ordinary member payloads may expose approved transfer planning/current readiness/safe completion time. They exclude blocker detail/history, participant/group manager notes, completion actor, completion IDs, selected/result roster IDs, and richer handoff provenance.

Sharing the same Kingdom or neutral player never exposes another Alliance's plan/group/readiness/blocker/completion state.

## 7. Persistence and query semantics

Transfer state is tenant/plan-scoped. Participant queries eager-load completion in their normal bounded relation set; manager reads additionally load completion actor/resulting roster/player relationships.

Rendering participant rows must not introduce one completion query per participant.

## 8. Events/integrations/background processing

Accepted completion emits internal `kingdoms.transfer_participant_completed` audit/outbox evidence with scoped IDs, direction, and resulting roster-entry ID. Delegated roster actions keep their existing roster events when they materially mutate roster state.

Private transfer notes/blockers are excluded from event payloads.

`kingdoms.*` remains excluded from generic external webhooks. No public transfer API/webhook contract exists.

No transfer executor/background bot exists.

## 9. Failure, idempotency and concurrency

Expected fail-closed conditions include:

- missing `kingdoms.manage` or stale password confirmation;
- cross-Alliance plan/participant/roster IDs;
- plan not `locked` for completion;
- home-Kingdom drift;
- withdrawn/non-`confirmed` participant;
- stale/missing outgoing/staying roster binding;
- selected incoming roster stable-ID mismatch; and
- closing with incomplete active participant.

Completion locks Alliance → plan → participant before checking for an existing completion. Retry returns the existing completion before delegated roster side effects are repeated.

## 10. Operations and observability

Structured diagnostics may identify safe IDs/state/invariant context but must not log private blockers or manager notes.

Whole-increment query acceptance models **150 participants**, **20 transfer groups**, readiness history, blockers, and completion projections with bounded SELECT count.

See [Kingdoms transfer planning operations](../../operations/kingdoms-transfer-planning.md).

## 11. Tests and validation

Accepted `K2-P6` evidence covers tenant scope, plan lifecycle/home-Kingdom drift, participant directions/destinations, group/coordinator rules, readiness/blockers, explicit completion, incoming/outgoing/staying roster handoff, idempotency, privacy split, rollback/reapply, query shape, and external API/webhook non-exposure.

See the [KINGDOMS-002 exit report](../../product/kingdoms-transfer-planning-exit-report.md) and [security review](../../security/kingdoms-transfer-planning-security-review.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Roster](roster.md)
- [Snapshots](snapshots.md)
- [KINGDOMS-002 implementation plan](../../product/kingdoms-transfer-planning-implementation-plan.md)
- [KINGDOMS-002 exit report](../../product/kingdoms-transfer-planning-exit-report.md)
