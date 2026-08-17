# Architecture V3 compliance

Status: Current

Architecture V3 compliance is defined by structural and semantic architecture invariants. Test implementation and CI enforcement are documented and implemented separately.

## 1. Canonical bounded contexts

Exactly seven business contexts exist under `app/Contexts`:

```text
Accounts
GameWorld
Alliance
Operations
Intelligence
Communications
Platform
```

`Workflows`, `ReadModels` and `Shared` are composition/infrastructure layers, not business contexts.

## 2. Capability-first physical structure

Context-root technical buckets are not valid V3 structure:

```text
Actions
Models
Queries
Services
Policies
Http
```

Those technical layers belong inside an owning capability.

## 3. Identity and cross-context persistence

Accounts owns User identity. GameWorld owns Player identity. A User may own multiple Players, but the active Player is the game-domain principal.

Cross-context Eloquent relationships are not the V3 integration mechanism. In particular, GameWorld `Player` has no Eloquent relationship into Accounts `User`; ownership is represented through scalar `user_id` and supported owner queries/contracts.

## 4. Context-owned writes

A business write is implemented by the owning capability Action. Cross-context callers use explicit owner Actions/Queries and stable identifiers instead of foreign Models. Public write contracts neither accept nor return Eloquent models.

Authorization services interpret owner permission vocabulary. They do not acquire locks or own transactions. Foreign write actions call semantic owner authorization methods rather than importing and interpreting another context's permission enum.

`*MutationAuthority` abstractions are not part of V3.

## 5. Thin adapters

Controllers, middleware and route closures do not own:

- business `DB::transaction` blocks;
- direct domain persistence;
- business `lockForUpdate` / `sharedLock` behavior;
- outbox business writes.

The write path is:

```text
HTTP -> owning capability Action -> transaction/invariants/persistence
```

## 6. Workflow boundary

V3 Workflows are true multi-owner command processes. The intended packages are:

```text
AccountOnboarding
KingdomGovernance
```

Workflows own process coordination, not business Models, migrations, repositories, owner permission vocabularies or foreign aggregate writes.

Player activation belongs to `GameWorld/Players`. Kingdom transfer belongs to `GameWorld/KingdomTransfers`.

## 7. ReadModels and Shared

ReadModels combine cross-context reads and own no writes.

Shared contains business-neutral infrastructure only and does not import business contexts or encode business permissions/policy.

## 8. Communications boundary

Communications contains generic delivery behavior only. It does not own source-domain reminder semantics such as Event or King Perk reminder meaning/timing and does not inspect source-domain Models to reconstruct that meaning.

## 9. Full architecture boundary

Architecture compliance applies across the complete implementation surface:

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
documentation
```

A correct directory shape does not excuse semantic ownership leakage elsewhere in the implementation.