# Consistency and transactions

Status: Current

The application uses a shared relational database, but consistency is defined around the aggregate and capability being mutated—not around a global transaction spanning arbitrary contexts.

## Write rule

A protected mutation should generally:

1. enter the owning application action/service;
2. start a database transaction;
3. lock the mutable scope/authority rows needed for the decision;
4. resolve transaction-time actor and scope authority;
5. lock the target aggregate rows in a stable order;
6. enforce invariants;
7. persist the state change;
8. append audit/outbox state in the same transaction when required;
9. commit;
10. perform retryable side effects asynchronously after commit.

## Why transaction-time authority matters

Membership, role, capacity, schedule and lifecycle state can change between the initial HTTP authorization check and the actual write. Mutations therefore cannot rely solely on stale pre-transaction authority snapshots.

## Cross-context writes

A single context must not directly modify another context's aggregate for convenience. When one user intent requires several owners to change, an explicit workflow coordinates supported application contracts. Each owner remains responsible for its invariant and persistence.

## Concurrency

Use row-level locking or database constraints for invariants that can be violated by concurrent requests. Common examples include membership/role transitions, capacity/waitlist transitions, appointment occupancy, leadership transfer and idempotent delivery claiming.

Lock ordering should be deterministic to reduce deadlocks. Retry behavior must distinguish expected serialization/concurrency conflicts from permanent validation failures.