# Request lifecycle

Status: Current

A typical authenticated game-domain request follows this shape:

```text
HTTP request
  -> Laravel route/middleware
  -> authenticate User
  -> assign request/correlation context
  -> resolve active Player where game context is required
  -> resolve current scope facts
  -> controller
  -> owning action/service/query or ReadModel
  -> transaction + transaction-time authorization for writes
  -> persistence
  -> audit/outbox intent in same transaction when required
  -> commit
  -> response / Inertia render
  -> asynchronous side effects after commit
```

## Reads

A read belonging to one context should normally use that context's query/service. A screen that genuinely composes several owners should use an explicit ReadModel rather than allowing a controller to become a persistence integration layer.

## Writes

Controllers should validate/translate HTTP input and call the owning application action. The owning action is responsible for transaction boundaries, target locks, current authority, invariant enforcement and persistence.

## Side effects

Email/reminder/webhook/other retryable side effects should not make the owning transaction depend on remote delivery. Persist durable intent/outbox state, commit, then process asynchronously.