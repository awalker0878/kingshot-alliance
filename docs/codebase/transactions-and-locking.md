# Transactions and locking

Status: Current — Architecture V3

Transactions, locks and mutable authorization belong to the **owning capability write path**, not to HTTP adapters or generic authorization helpers.

## Write pattern

```text
Capability Action
  ↓
DB transaction
  ↓
lock mutable scope / authority state
  ↓
resolve actor + concrete scope
  ↓
assert current owner permission/invariants
  ↓
lock target aggregate in deterministic order
  ↓
mutate owner state
  ↓
write audit/outbox intent when required
  ↓
commit
```

## Separation of responsibilities

Authorization services interpret owner permission vocabulary. They do not acquire database locks or own transactions.

Write-state/application services may acquire the locks required by their owner, but they do not interpret foreign-context permissions.

Controllers, middleware, route closures and ReadModels do not own business write transactions.

Workflows coordinate owner Actions. They do not become the place where participating contexts' model locks and permission checks are implemented.

## Cross-context writes

A context never locks or mutates another context's aggregate through its Eloquent model. It invokes the owner's supported Action using stable identifiers and command data.

Where a process spans multiple owners, prefer explicit process coordination and durable state/events over a Workflow reaching through all participating models inside one shared transaction.

## Avoid

- request-time authorization treated as sufficient for mutable writes;
- `*MutationAuthority` abstractions combining lock acquisition and permission interpretation;
- direct foreign-model mutation;
- inconsistent lock ordering;
- remote side effects before commit;
- stale scope/role assumptions after locks are acquired.

## Testing

Concurrency-sensitive capability tests should cover duplicate requests, capacity/slot races, role changes, leadership changes, idempotent retry and conflicting transitions where applicable.