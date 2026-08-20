# ADR-0003: Previewed bulk actions with per-item results

Status: Accepted

Date: 2026-08-20

## Context

Operational pages need bounded multi-item changes, but applying one opaque command to a selection hides authorization, stale state, invalid transitions, and partial failure. Making every bulk operation atomic is also misleading when users need valid items to progress while separately correcting blocked items.

## Decision

Bulk commands use a two-step contract:

1. The owner context authorizes and previews no more than 50 explicit scalar item IDs.
2. The user confirms the concrete eligible count and target operation.
3. The owner re-reads current state and authorizes each write at commit time.
4. The result reports every requested item as `succeeded`, `failed`, or `skipped` with a stable reason code.
5. Failed item IDs remain available for a selective retry after the underlying issue is corrected.

`BulkActionResult` and `BulkItemResult` are business-neutral transport value objects. They standardize counts and item outcomes without owning context-specific transition rules. Each owner action remains responsible for its transaction, audit event, durable effects, and idempotency semantics. A bulk coordinator adds one aggregate audit receipt but does not replace per-item evidence.

Recruitment candidate stage triage, Alliance membership status administration, Event cancellation, Contribution approval, and Notification inbox updates implement the contract. Each implementation uses the same transport result while keeping authorization, transition reasons, and audit semantics inside its owner context.

## Consequences

- Users see blocked rows before committing and never receive a false all-or-nothing success message.
- A concurrent state change is handled by the commit-time owner action and appears as an item failure.
- Valid items may succeed when unrelated items fail; this is explicit in the result rather than an accidental partial write.
- Clients cannot submit arbitrary query filters as a bulk write. They submit bounded, concrete IDs from an authorized view.
- Retry is selective and uses the same preview/authorization path.

## Rejected alternatives

- One transaction for every selected item was rejected because one stale record would prevent unrelated valid work and still provide poor recovery UX.
- Client-only previews were rejected because browser state cannot prove current authorization or transition eligibility.
- Backgrounding all bulk operations was rejected because bounded local domain writes do not inherently require asynchronous processing; external effects continue through the outbox.
