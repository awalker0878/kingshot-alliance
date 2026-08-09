# Kingdoms transfer planning

[← Kingdoms](kingdoms.md)

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice A / `K2-P1` candidate  
**Decision record:** [KINGDOMS-002 Slice A decisions](../product/kingdoms-transfer-planning-slice-a-decisions.md)

Slice A introduces only the alliance-owned transfer-cycle aggregate. It does not yet track players, transfer direction, destinations, groups, readiness, blockers, or completion.

## Ownership and tenancy

`TransferPlan` is Kingdoms-domain tenant data owned by one Alliance. Its `home_kingdom_id` points to global neutral `Kingdom` reference data but does not make the plan global or kingdom-wide.

All plan reads and mutations are constrained by the active Alliance. A submitted plan ID is re-resolved with `alliance_id` before mutation, so another Alliance cannot read or advance a plan merely because it shares the same Kingdom.

## Transfer-plan data

Slice A stores only:

- Alliance ID;
- captured home Kingdom ID;
- human-readable cycle label;
- optional start date;
- optional end date;
- lifecycle state; and
- normal timestamps.

No participant/group/readiness fields are reserved in this schema. Later slices must add their own minimum persistence only when their runtime capability is implemented.

## Lifecycle

The normal lifecycle is:

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

More precisely:

- create always produces `draft`;
- `draft → open`;
- `open → locked`;
- `locked → closed`;
- `draft/open/locked → cancelled`;
- `closed` and `cancelled` are terminal; and
- repeating the same completed transition is idempotent and produces no duplicate audit/outbox evidence.

An Alliance may have multiple drafts, but only one plan may be `open`. Opening is serialized with an Alliance row lock and backed by a PostgreSQL partial unique index on open plans.

The member current-cycle query prefers an open plan, then a locked plan, then the newest draft. Closed/cancelled plans are history and are shown on the management surface rather than as the member current cycle.

## Home-Kingdom capture and drift

Creation requires an Alliance with an active current Kingdom. The plan captures that Kingdom as immutable planning context.

If the Alliance's current Kingdom later differs from the captured home Kingdom, normal progression (`open`, `lock`, `close`) fails closed. Cancellation remains available as the safe recovery operation so stale plans cannot become undeletable operational dead ends.

Slice A deliberately does not implement plan reconciliation or automatic reassignment of a plan to a new Kingdom.

## Authorization

- member view: `alliance.view`;
- management view: `kingdoms.manage`;
- create/open/lock/close/cancel: `kingdoms.manage` plus recent password confirmation.

Assignment concepts do not exist in Slice A. Future coordinator assignment will be workflow responsibility, not authorization, and cannot bypass these permissions.

## Audit and outbox

Material lifecycle events are attributable and durable:

- `kingdoms.transfer_plan_created`;
- `kingdoms.transfer_plan_opened`;
- `kingdoms.transfer_plan_locked`;
- `kingdoms.transfer_plan_closed`; and
- `kingdoms.transfer_plan_cancelled`.

These events use the existing transactional audit/outbox pattern. They remain internal because generic external webhook fan-out rejects every `kingdoms.*` event family unless a future integration contract explicitly changes that boundary.

## Explicit Slice A non-capabilities

Slice A does not implement participant intent, incoming/outgoing/staying status, destination Kingdom selection, groups, coordinators, readiness, blockers, transfer-resource/ticket tracking, eligibility rules, automated transfer execution, marketplace/public advertising, diplomacy/NAP intelligence, cross-alliance visibility, game scraping/OCR/bots, undocumented APIs, rankings, AI recommendations, or a public Kingdoms API/webhook contract.
