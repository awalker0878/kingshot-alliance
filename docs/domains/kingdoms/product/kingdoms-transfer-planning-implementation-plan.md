# KINGDOMS-002 implementation plan

[← Kingdoms transfer planning product increment](kingdoms-transfer-planning-increment.md)

**Status:** **Accepted — `K2-P0` through `K2-P6` complete**  
**Scope ID:** `KINGDOMS-002`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` scope and implementation  
**Validated whole-increment implementation:** `64189559c66e15dc56ec31f9b340284c89c30e6c`  
**Acceptance evidence:** [KINGDOMS-002 exit report](kingdoms-transfer-planning-exit-report.md)  
**Important:** These are implementation phases inside `KINGDOMS-002`; they are not a continuation of historical program phase numbering.

## 1. Purpose

This plan sequenced the approved `KINGDOMS-002` transfer-planning scope into independently reviewable slices while preserving one whole-increment acceptance boundary.

The accepted implementation continues the platform rules established by `KINGDOMS-001`:

- domain-first runtime ownership under `app/Domain/<Domain>`;
- explicit active-Alliance tenancy for tenant-owned observations/workflows;
- global Kingdom/Player data remains neutral reference identity only;
- `alliance.view` for ordinary authenticated visibility and `kingdoms.manage` for transfer mutations;
- coordinator assignment is workflow responsibility, never authorization;
- policy/permission authorization rather than controller role-name checks;
- thin controllers with business behavior in actions/services/queries;
- transactional persistence for related business mutations;
- attributable audit evidence for privileged changes;
- transactional outbox for durable internal side effects;
- append-oriented/auditable transition history where state changes matter;
- accepted roster actions reused for roster lifecycle handoff;
- no compatibility shims after migrations are complete;
- code/tests authoritative for exact runtime behavior;
- security, accessibility, operations and living documentation updated with the capability; and
- no dormant future diplomacy, ingestion, marketplace, ranking, eligibility or public-API placeholders.

## 2. Final phase status

| Phase | Final status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K2-P0` | **Complete** | Identity, tenancy, lifecycle and handoff contract locked | Slice A preparation |
| `K2-P1` | **Validated** | Transfer-cycle foundation | Slice A |
| `K2-P2` | **Validated** | Participant direction and destination | Slice B |
| `K2-P3` | **Validated** | Transfer groups and coordinators | Slice C1 |
| `K2-P4` | **Validated** | Manual readiness and blocker workflow | Slice C2 |
| `K2-P5` | **Validated** | Explicit completion and roster handoff | Slice D |
| `K2-P6` | **Accepted** | Whole-increment hardening and acceptance | Whole increment |

Slice-specific validation records remain historical evidence. `K2-P6` validates the integrated dependency stack rather than compensating for incomplete earlier slices.

## 3. `K2-P0` — Design and contract lock — Complete

The accepted contract establishes:

- `TransferPlan` is alliance-owned and captures current Alliance Kingdom as `home_kingdom_id`;
- at most one `open` transfer plan per Alliance;
- plan states `draft`, `open`, `locked`, `closed`, `cancelled`;
- participant directions `staying`, `outgoing`, `incoming`;
- readiness states `not_started`, `preparing`, `ready`, `blocked`, `confirmed`, `withdrawn`;
- outgoing/staying participants normally resolve through same-alliance roster entries;
- incoming participants may exist before site membership/roster entry;
- display name alone never merges neutral identity;
- incoming destination is plan home Kingdom, staying has no transfer destination, outgoing may target another Kingdom;
- active-plan home-Kingdom drift fails closed;
- groups/coordinators are alliance-owned workflow data and coordinator assignment never grants `kingdoms.manage`;
- readiness/blocker privacy is split between member-safe and manager-only presentation;
- completion is explicit/idempotent and delegates to accepted roster actions; and
- transfer outbox events remain internal and ineligible for generic webhook fan-out.

## 4. `K2-P1` / Slice A — Transfer cycle foundation — Validated

Delivered:

- `TransferPlan` under the Kingdoms domain using repository ULID conventions;
- alliance/home-Kingdom ownership and lifecycle state;
- database-safe one-open-plan invariant;
- create/open/lock/close/cancel actions;
- active-Alliance/home-Kingdom revalidation;
- `kingdoms.manage` plus recent-password-confirmation mutation boundary;
- transaction/locking protection for lifecycle changes;
- attributable audit/internal-outbox events;
- member and manager transfer-cycle entry points; and
- accessibility, tenant isolation, lifecycle and external-event-boundary tests.

No participant/group/readiness placeholders were added to the plan table.

## 5. `K2-P2` / Slice B — Participant direction and destination — Validated

Delivered alliance/plan-scoped participants with:

- explicit direction;
- optional same-alliance roster binding;
- optional neutral Player reference for incoming planning;
- plan-scoped observed name and optional stable game-player identifier;
- source/destination Kingdom under direction rules;
- optional same-alliance membership link;
- private manager notes and withdrawal history; and
- actor/audit/outbox provenance.

Accepted invariants include:

- outgoing/staying roster references are same-alliance;
- incoming participants may be pre-roster/pre-membership;
- display-name-only identity merging is prohibited;
- incoming destination normalizes to plan home Kingdom;
- staying destination is null;
- outgoing destination may remain unresolved during planning;
- planning destination never moves neutral player identity;
- participant mutations are limited to mutable plans; and
- home-Kingdom drift/object-ID tampering fail closed.

## 6. `K2-P3` / Slice C1 — Transfer groups and coordinators — Validated

Delivered alliance/plan-scoped groups with direction/context, destination where applicable, lifecycle state, optional same-alliance coordinator, private notes and participant assignment.

Accepted invariants include:

- coordinator membership belongs to the active Alliance;
- coordinator assignment grants no permission;
- outgoing participant/group destinations must be compatible;
- incoming groups target the plan home Kingdom;
- staying participants are not placed in moving groups;
- destination/group changes revalidate affected participants transactionally; and
- group/assignment changes remain attributable through audit/internal outbox.

## 7. `K2-P4` / Slice C2 — Readiness and blocker workflow — Validated

Delivered:

- explicit manual readiness transitions;
- append-oriented readiness transition history;
- manager-maintained blocker records and resolution history;
- manager-safe/member-safe presentation split;
- readiness filters/coordination summary; and
- structured operational diagnostics without private-note logging.

Accepted invariants include:

- no automatic eligibility/readiness scoring;
- invalid readiness jumps fail;
- blocked state is explicit and explainable;
- resolving the final blocker does not automatically mark a participant ready;
- `confirmed` is still planning state and does not perform roster handoff; and
- withdrawn participants/history remain retained.

## 8. `K2-P5` / Slice D — Explicit completion and roster handoff — Validated

Delivered a unique alliance/plan/participant-scoped `TransferCompletion` as the idempotency boundary.

Completion requires:

- `kingdoms.manage` plus recent password confirmation;
- a `locked` plan;
- explicitly `confirmed` readiness;
- a non-withdrawn participant; and
- unchanged captured home-Kingdom context.

Direction-specific behavior:

- **incoming:** explicitly create or link an accepted same-alliance roster result through accepted roster actions; never name-only auto-match; preserve accepted existing roster/private fields on explicit link; require stable-ID agreement where available;
- **outgoing:** re-resolve the same-alliance roster binding and delegate to accepted mark-left behavior;
- **staying:** record completion without changing roster lifecycle state.

Completion is serialized/idempotent, preserves neutral identity/snapshot history, creates no fabricated `PlayerSnapshot`, emits attributable audit/internal-outbox evidence, and has no bulk-complete path.

A locked plan cannot become closed while any non-withdrawn participant lacks explicit completion.

## 9. `K2-P6` — Whole-increment hardening and acceptance — Accepted

The final whole-stack review covered:

- Kingdoms domain ownership and cross-domain contracts;
- active-Alliance tenancy and object-ID tampering;
- private notes/blockers/completion provenance;
- coordinator privilege confusion;
- incoming identity ambiguity;
- destination manipulation and home-Kingdom drift;
- abuse boundaries confirming no ranking/scoring/recommendation capability;
- accessibility across plan, participant, group, readiness/history and completion workflows;
- migration rollback/reapply to the accepted `KINGDOMS-001` baseline;
- realistic-volume query/index behavior;
- completion idempotency and roster integrity;
- operations/observability documentation; and
- public API/webhook non-exposure.

Additional P6 evidence includes one integrated end-to-end workflow and a realistic-volume query gate with 150 participants and 20 groups.

The exact validated implementation SHA and protected check evidence are recorded in the [KINGDOMS-002 exit report](kingdoms-transfer-planning-exit-report.md). The [whole-increment security review](../security/kingdoms-transfer-planning-security-review.md) and [accessibility review](kingdoms-transfer-planning-accessibility.md) are part of the acceptance record.

## 10. Accepted pull-request sequence

Delivery order:

1. Slice A / `K2-P1` — Transfer cycle foundation.
2. Slice B / `K2-P2` — Participant direction and destination.
3. Slice C1 / `K2-P3` — Transfer groups and coordinators.
4. Slice C2 / `K2-P4` — Readiness and blockers.
5. Slice D / `K2-P5` — Explicit completion and roster handoff.
6. `K2-P6` — Whole-increment hardening, audits, documentation and acceptance record.

Each slice remained independently migratable/testable and did not add compatibility shims or dormant future-schema fields solely for later slices.

## 11. Branch record

Historical implementation branches:

- `agent/kingdoms-002-slice-a`
- `agent/kingdoms-002-slice-b`
- `agent/kingdoms-002-slice-c1`
- `agent/kingdoms-002-slice-c2`
- `agent/kingdoms-002-slice-d`
- `agent/kingdoms-002-acceptance`

Repository/product acceptance does **not** approve real production cutover. Production launch remains governed by its separate approval record.
