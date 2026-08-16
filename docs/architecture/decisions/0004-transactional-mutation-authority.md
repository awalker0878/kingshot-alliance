# ADR 0004: Transactional mutation authority

Status: Accepted

## Decision

For writes whose authorization depends on mutable membership, role, scope or lifecycle state, resolve final mutation authority inside the transaction after locking the relevant scope state.

## Rationale

Pre-request authorization can become stale before the write commits. Transaction-scoped authority prevents time-of-check/time-of-use privilege errors and gives the action one consistent view of authority plus target state.

## Consequences

Actions/services own the authoritative write decision; controllers remain thin. Locks are acquired in deterministic order, invariants are checked against locked state, and cross-context workflows invoke each owner's mutation contract rather than bypassing it.