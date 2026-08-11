# KINGDOMS-002 Slice A decision record

[← KINGDOMS-002 implementation plan](kingdoms-transfer-planning-implementation-plan.md)

**Scope ID:** `KINGDOMS-002`  
**Delivery gate:** `K2-P0` decisions + `K2-P1` Slice A candidate  
**Status:** Candidate pending protected validation

## K2-P0 decisions locked for Slice A

The Slice A implementation uses these explicit business rules:

1. `TransferPlan` is alliance-owned tenant data. It captures `home_kingdom_id` from the Alliance's current first-class Kingdom at creation time.
2. A plan is created as `draft`. The normal lifecycle is `draft → open → locked → closed`.
3. `draft`, `open`, and `locked` may transition to `cancelled`. `closed` and `cancelled` are terminal.
4. Repeating an already-completed lifecycle transition is an idempotent no-op: it produces no duplicate audit/outbox evidence.
5. At most one `open` plan may exist per Alliance. The application serializes opening under an Alliance row lock and PostgreSQL enforces a partial unique index as the final concurrency backstop.
6. Multiple drafts are allowed. A locked plan may coexist with a later draft, but Slice A member presentation prefers `open`, then `locked`, then the newest `draft` as the current cycle summary.
7. A plan cannot be created when the Alliance has no current active Kingdom.
8. The captured home Kingdom is planning context, not mutable current Alliance state. If the Alliance Kingdom later differs from `home_kingdom_id`, `open`, `lock`, and `close` fail closed.
9. Cancellation remains permitted after home-Kingdom drift so managers can terminate a stale cycle safely. Slice A does not implement a reconciliation workflow.
10. Ordinary transfer-cycle visibility uses `alliance.view`. Creation and lifecycle mutations use `kingdoms.manage` plus recent password confirmation.
11. Submitted transfer-plan IDs are always re-resolved beneath the active Alliance before mutation. Sharing a Kingdom never authorizes another Alliance's plan.
12. New `kingdoms.transfer_plan_*` outbox events are internal durability events. The accepted `kingdoms.*` webhook exclusion continues to prevent wildcard external fan-out.
13. Slice A contains no participant, direction, destination, group, coordinator, readiness, blocker, completion, marketplace, diplomacy, ingestion, ranking, or public API/webhook schema.

## Slice A runtime boundary

Slice A adds only:

- `TransferPlan` persistence and lifecycle state;
- create/open/lock/close/cancel domain actions;
- member-safe current-cycle presentation;
- management lifecycle UI;
- tenant, authorization, password-confirmation, audit and outbox controls; and
- migration/security/accessibility/operations evidence for this foundation.

Participant intent begins only in Slice B / `K2-P2` after Slice A is validated in the dependency stack.
