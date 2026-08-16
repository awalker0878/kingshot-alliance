# Transactions and locking

Status: Current

Architecture V2 uses explicit transaction-time mutation authority for sensitive writes.

## Preferred pattern

```text
DB transaction
  -> lock mutable scope/authority state
  -> resolve actor + concrete scope
  -> assert current permission/invariant
  -> lock target aggregate in deterministic order
  -> mutate
  -> write audit/outbox intent if required
  -> commit
```

Context-specific mutation authority services include Alliance, GameWorld/Kingdom, Operations/Intelligence and Platform boundaries as implemented in their `Access`/`Governance` packages.

## Avoid

- authorizing only before the transaction and assuming the membership/role is unchanged;
- loading a target, doing unrelated work, then locking later;
- locking resources in inconsistent orders between competing code paths;
- modifying another context's aggregate directly from a workflow/controller;
- dispatching non-transactional remote side effects before commit.

## Testing

Concurrency-sensitive capabilities should test duplicate requests, capacity races, role/leadership changes, idempotent retries and lock-sensitive transitions where applicable.