# ADR 0004: Transactional write authorization

Status: Accepted

## Decision

For writes whose authorization depends on mutable membership, role, scope, lifecycle, or ownership state, the owning action or service opens the transaction, locks the relevant current state in deterministic order, and then performs the final authorization check against that locked state.

Authorization services express policy and evaluate the current locked principal/scope. They do not act as cross-context authority facades. Capability-specific write-state helpers may only acquire or revalidate state; they must not interpret another context's permission vocabulary.

## Rationale

Request-time authorization can become stale before a write commits. Revalidating after locks prevents time-of-check/time-of-use privilege errors while keeping transaction ownership with the context that owns the data being changed.

## Consequences

Controllers remain thin. Owning actions/services define transaction boundaries, lock their aggregates and mutable scope records, enforce invariants, and invoke authorization against the current state. Cross-context Workflows coordinate owner APIs rather than owning persistence or bypassing a context's write contract.
