# Testing

Status: Current — Architecture V3

Architecture V3 verification must test both **source structure** and **business behavior**. A passing directory check alone is not architecture certification.

This document defines the intended V3 verification contract only. Implementing or rewriting `/tests` and CI enforcement is a separate step.

## Structural architecture verification

V3 verification should derive rules from the architecture rather than maintain a second hardcoded capability registry.

It should cover, at minimum:

- exactly seven business contexts under `app/Contexts`;
- no context-root `Actions`, `Models`, `Queries`, `Services`, `Policies` or `Http` directories;
- no `*MutationAuthority` classes/references;
- GameWorld Player has no Accounts User Eloquent relationship;
- contexts do not import Workflows;
- Workflows contain no business Models, migrations, repositories, owner permission enums or direct foreign writes;
- ReadModels perform no writes;
- HTTP adapters contain no business transactions, direct persistence or business locks;
- Communications contains no Event/KingPerk-specific delivery classes or direct source-context model dependencies;
- cross-context Eloquent navigation is prohibited;
- authorization services do not acquire locks;
- write Actions/services do not interpret foreign permission vocabularies.

## Behavior verification

Behavior verification should continue protecting high-risk identity, authority, scope, transaction, concurrency, retry/idempotency and business invariants.

Important areas include:

- User -> many Players and active Player selection;
- Player-scoped Alliance, Kingdom, Operations and Intelligence authority;
- Alliance membership and leadership changes;
- Kingdom governance and transfer behavior;
- Event execution, participation, planning and results;
- KingPerks occupancy, cooldown and scheduling rules;
- Intelligence historical attribution;
- generic Communications delivery idempotency/retry;
- Platform authority isolation.

## Full architecture certification

Final V3 certification must inspect more than tests named `Architecture*`:

```text
directories
namespaces
imports
Eloquent relationships
database ownership
controllers
routes
actions
permissions
transactions
events
listeners
tests
documentation
CI
```

The actual `/tests` implementation and CI gate design are intentionally deferred to the dedicated testing phase.