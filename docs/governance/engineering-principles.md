# Engineering principles

Status: Current — Architecture V3

## Context first, capability second

Identify the owning bounded context and cohesive capability before choosing Models, Actions, Services or endpoints. Do not create a bounded context for every noun, and do not organize a context around framework-layer folders.

## Capability-first source shape

Business code lives under:

```text
app/Contexts/<Context>/<Capability>/...
```

Actions, Models, Queries, Services, Policies and Http are subordinate implementation details inside a capability.

## Player-scoped game authority

User authenticates the account. Active Player is the game-domain principal. Never aggregate privileges across all Players owned by a User. Platform Administrator is platform authority only.

## Owner-controlled writes

Controllers stay thin. Owning capability Actions enforce business invariants, transactions and current authorization. Authorization services interpret permissions but do not acquire locks.

## No persistence reach-through

A shared database is not permission to mutate or navigate another context's aggregate. Cross-context calls use owner Actions/Queries, scalar identifiers, durable events, Workflows or ReadModels.

## Narrow composition layers

Workflows coordinate true multi-owner commands and own no participating business persistence. ReadModels compose reads and own no writes. Shared contains business-neutral infrastructure only.

## Generic communications

Communications owns delivery, not the business fact that caused delivery. Source contexts retain reminder/event semantics.

## Transactional side effects

Persist durable outbox intent with the owner business transaction where required. Execute remote/retryable effects after commit and design consumers for at-least-once delivery.

## Delete superseded structure

When V3 replaces a source package, capability name or documentation page, remove the superseded structure. Do not leave compatibility folders or historical docs unless they are part of an explicit product/runtime compatibility contract.