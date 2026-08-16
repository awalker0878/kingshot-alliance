# Integration model

Status: Current — Architecture V3

Bounded contexts collaborate through deliberate contracts rather than unrestricted model imports.

## Same-context collaboration

Capabilities inside one bounded context may collaborate using context-owned Actions, Queries, Services, value objects and domain events while preserving each capability's responsibilities.

## Cross-context reads

For a stable fact owned by another context, use the owner's supported Query/service contract and scalar identifiers.

For a user-facing projection combining multiple owners, use `app/ReadModels`.

ReadModels are read-only.

## Cross-context writes

Call the owning capability Action. Do not import the foreign Model and mutate it directly.

Example:

```text
Workflows/AccountOnboarding
    -> Accounts/Registration/RegisterUser
    -> Alliance/Membership/AcceptInvitation
```

not:

```text
Workflow
    -> User::query()/save()
    -> AllianceMembership::query()/save()
```

## Multi-owner processes

Use `app/Workflows` only when a command genuinely coordinates multiple owners. V3 intended workflow packages are `AccountOnboarding` and `KingdomGovernance`.

Player activation belongs to `GameWorld/Players`. Kingdom transfer belongs to `GameWorld/KingdomTransfers`.

## Durable integration

Use outbox-backed events/messages when eventual coordination is appropriate or when a remote/retryable side effect must survive process failure.

The event publisher owns the fact being published. Consumers must not treat an event as permission to mutate the publisher's aggregate.

## Communications

Source capabilities retain the semantics/timing of notifications they request. `Communications/Delivery` accepts generic delivery intent and owns recipient preferences, channels, attempts, retries/failure and idempotency.

Communications does not import source-domain Models to reconstruct Event/KingPerk/business meaning.

## Shared infrastructure

`app/Shared/Infrastructure` implements generic mechanisms such as audit/outbox/runtime plumbing. It is not an integration shortcut between business contexts.