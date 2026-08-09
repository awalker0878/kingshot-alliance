# Kingdoms transfer planning operations

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice A / transfer-cycle foundation candidate

## Runtime shape

Slice A is synchronous request/response behavior using PostgreSQL plus the existing audit and transactional-outbox infrastructure. It adds no Kingdoms-specific scheduler, queue, crawler, bot, or external game integration.

## Migration

`2026_08_09_090000_create_transfer_plans.php` creates the alliance-owned transfer-plan table and the partial unique index that permits at most one `open` cycle per Alliance.

Development/test rollback drops `transfer_plans`. No `KINGDOMS-001` roster/player/snapshot persistence is modified by Slice A.

## Operational diagnosis

When plan creation fails, verify:

1. the request has an active Alliance context;
2. the Alliance has an active first-class Kingdom;
3. the actor has `kingdoms.manage`; and
4. recent password confirmation is present for the mutation route.

When opening fails, additionally check whether another plan is already `open`.

When open/lock/close reports Kingdom drift, compare `alliances.kingdom_id` with `transfer_plans.home_kingdom_id`. Slice A does not reconcile those references automatically. Managers may cancel the stale plan and deliberately create a new cycle under the current Kingdom.

## Audit/outbox evidence

Material state changes should produce matching `audit_events` and `outbox_messages` entries using the `kingdoms.transfer_plan_*` event family. Idempotent retry of an already-completed transition should not increase either count.

`kingdoms.*` remains excluded from external webhook delivery. Seeing a transfer event in the internal outbox does not mean it is an external integration contract.

## Rollback and recovery

Before rollback, confirm no downstream Slice B+ migration depends on `transfer_plans`. Slice A rollback is safe only before dependent transfer-planning slices are applied.

A home-Kingdom drifted plan should normally be cancelled rather than altered in place; immutable captured home context is intentional evidence of which Kingdom the plan was created for.
