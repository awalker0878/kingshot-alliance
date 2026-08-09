# Kingdoms transfer planning

[← Kingdoms](kingdoms.md)

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice C1 / `K2-P3` candidate on validated Slice B  
**Slice B evidence:** [KINGDOMS-002 Slice B validation](../product/kingdoms-transfer-planning-slice-b-validation.md)

`KINGDOMS-002` is an alliance-owned planning workflow layered on the accepted `KINGDOMS-001` Kingdom/player/roster foundation. Slice C1 adds transfer groups and coordinator responsibility without implementing readiness, blockers, eligibility/resources, or transfer completion.

## Ownership and tenancy

`TransferPlan`, `TransferParticipant`, and `TransferGroup` are Kingdoms-domain tenant data owned by one Alliance. `Kingdom` and `KingdomPlayer` remain global neutral reference data only.

Every group/participant read and mutation is constrained by the active Alliance and selected transfer plan. Submitted plan, participant, group, roster, membership, source-Kingdom, and destination-Kingdom values are re-resolved under the applicable domain boundary.

Sharing the same Kingdom or neutral player reference never grants another Alliance access to transfer intent, group assignments, coordinator responsibility, manager notes, membership linkage, or destination planning.

## Transfer plan lifecycle

The validated plan lifecycle remains:

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

Participant and group changes are permitted only while the plan is `draft` or `open`. `locked`, `closed`, and `cancelled` plans are read-only for Slice C1.

The plan captures immutable `home_kingdom_id`. If the Alliance's current Kingdom later differs, participant/group mutations fail closed. Cancellation remains the stale-plan recovery path.

## Participant directions

Slice B direction semantics remain authoritative:

- `staying` — active/tracked alliance roster player not planned to move;
- `outgoing` — active/tracked alliance roster player planned or potentially planned to move away; and
- `incoming` — player planned to arrive in the plan home Kingdom, potentially before site membership or roster entry exists.

Incoming destination is always the plan home Kingdom. Outgoing destination may be undecided or another active Kingdom. Staying has no transfer destination. Destination planning never mutates `KingdomPlayer.kingdom_id`.

## Transfer groups

A `TransferGroup` is alliance/plan-scoped coordination data. It stores only the C1 workflow fields required at runtime:

- name;
- direction/context (`incoming` or `outgoing`);
- optional destination Kingdom according to direction rules;
- lifecycle state (`active` or `archived`);
- optional same-alliance coordinator membership;
- manager-only notes; and
- normal timestamps.

A participant may belong to at most one group. A participant may remain unassigned. C1 deliberately does not introduce a participant/group many-to-many relationship.

### Incoming groups

An incoming group:

- has `incoming` direction;
- always uses the plan home Kingdom as destination regardless of submitted destination input; and
- accepts only active incoming participants from the same plan.

### Outgoing groups

An outgoing group:

- has `outgoing` direction;
- may have an undecided destination; or
- may bind to another active canonical Kingdom as destination.

The plan home Kingdom is not a valid outgoing group destination.

If an outgoing group has a destination, assigned outgoing participants must already have that exact destination. Assignment or later edits fail rather than silently rewriting participant destination intent.

### Staying participants

Staying participants cannot be assigned to moving transfer groups. Slice C1 groups are coordination for participants who are actually moving (`incoming` or `outgoing`).

## Compatibility and transaction rules

Compatibility is checked from both mutation directions:

- participant → group assignment checks plan/Alliance, active group, moving direction, direction equality, and outgoing destination compatibility;
- group updates lock and revalidate every active assigned participant before changing direction/destination; and
- participant updates revalidate the existing active group before changing direction/destination.

If any active assigned participant would become incompatible, the transaction fails and neither the group nor participant intent is rewritten.

This preserves the previously approved direction/destination decision rather than treating group data as a source of truth that silently overwrites participant planning.

## Coordinator responsibility

A group may have one optional coordinator membership.

The coordinator membership must be active and belong to the active Alliance when assigned. The coordinator is workflow responsibility only:

- being named coordinator does not grant `kingdoms.manage`;
- it does not alter roles or permissions;
- it does not bypass password confirmation; and
- every privileged group/participant mutation continues through normal policy authorization.

C1 does not create coordinator-specific mutation permissions.

## Group lifecycle

Groups begin `active`. Managers may archive an active group only while its plan is Draft/Open and the Alliance home-Kingdom context has not drifted.

A group with active assigned participants cannot be archived. Move/unassign those participants first. Withdrawn historical participants may retain the group reference so history is not erased.

Archive retries are idempotent and do not duplicate audit/outbox evidence. Active group names are case-insensitively unique within one plan; an archived name may be reused by a later active group.

## Visibility and authorization

- member transfer view: `alliance.view`;
- management view: `kingdoms.manage`;
- plan/participant/group mutations: `kingdoms.manage` plus recent password confirmation.

Member payloads may expose operationally useful group name, direction, destination, and coordinator display name. They exclude group IDs, coordinator membership IDs, participant group IDs, manager-only group notes, participant manager notes, and private membership details.

## Audit and outbox

Slice C1 emits attributable internal events for material changes:

- `kingdoms.transfer_group_created`;
- `kingdoms.transfer_group_updated`;
- `kingdoms.transfer_group_archived`; and
- `kingdoms.transfer_participant_group_changed`.

Existing plan and participant events remain unchanged.

Group/participant manager notes are never copied into audit/outbox metadata. `kingdoms.*` remains excluded from generic external webhook fan-out, so C1 creates no public webhook contract.

## Explicit Slice C1 non-capabilities

Slice C1 does not implement:

- readiness states or blocker tracking;
- transfer passes/tickets/resources or eligibility rules;
- inferred eligibility, automated destination ranking, or stay/leave recommendations;
- transfer execution or roster completion/handoff;
- marketplace/public advertising;
- diplomacy/NAP intelligence;
- cross-alliance transfer visibility;
- scraping, OCR, bots, or undocumented game APIs;
- AI/punitive scoring; or
- public Kingdoms API/webhook contracts.

`KINGDOMS-002` remains in progress until later slices and the whole-increment acceptance gate pass.
