# Request lifecycle

Status: Current — Architecture V3

A normal game-domain request follows this shape:

```text
Route
  -> transport/security middleware
  -> authenticate User
  -> resolve/validate active Player through GameWorld/Players
  -> capability-local HTTP adapter
  -> owning capability Action or ReadModel/Workflow
  -> response
```

## Request context

Authentication resolves the User account. Game-domain requests that require game authority resolve a concrete active Player owned by that User.

Request context may provide current identifiers and coarse prerequisites, but it is not the final authority for mutable writes.

## Write request

```text
Controller
  -> validate input
  -> invoke capability Action
       -> begin owner transaction
       -> lock mutable authority/scope state
       -> revalidate current permission/invariants
       -> lock/mutate owner aggregate
       -> persist audit/outbox intent if required
       -> commit
  -> response
```

The Controller does not own the transaction, locks or Eloquent persistence.

## Cross-context command

If one owner can satisfy the command, call that owner's Action directly. If the business process genuinely requires multiple write owners, invoke a Workflow such as `AccountOnboarding` or `KingdomGovernance`.

## Read request

A capability-local Query handles owner reads. A `ReadModel` handles cross-context read composition. ReadModels do not write.