# Routing and HTTP adapters

Status: Current — Architecture V3

HTTP is an adapter layer, not a business write owner.

## Request path

```text
Route
  ↓
Capability-local Controller / Request
  ↓
Owning capability Action
  ↓
transaction + current authorization + persistence
  ↓
HTTP response
```

Controllers and requests should live under the capability they expose, for example:

```text
app/Contexts/Accounts/Profile/Http/
app/Contexts/GameWorld/Players/Http/
app/Contexts/Operations/KingPerks/Http/
```

## HTTP adapter responsibilities

HTTP adapters may:

- parse route/request input;
- validate transport-level input;
- resolve authenticated User and request-scoped active Player context;
- construct command data;
- invoke an Action or Workflow;
- convert application results/exceptions into HTTP/Inertia responses.

HTTP adapters must not:

- own `DB::transaction` blocks;
- call `save`, `delete`, `create`, `update` or equivalent domain persistence directly;
- use `lockForUpdate` or `sharedLock` for business writes;
- write outbox/audit business intent directly;
- interpret another context's permission vocabulary;
- coordinate multi-context persistence themselves.

## Routes

Route files register endpoints and middleware only. Route closures must not contain domain writes or transaction logic.

## Middleware

Middleware may establish request context or enforce transport/security prerequisites. Mutable business authorization that can change concurrently must be revalidated by the owning write Action inside its transaction.