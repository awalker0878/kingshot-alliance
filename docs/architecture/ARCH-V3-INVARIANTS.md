# Architecture V3 Clean-Room Invariants

Status: Active rewrite contract

## Core rule

Identity crosses boundaries. Eloquent models do not carry authority across boundaries. Current mutable authority is reloaded and validated where the protected operation actually occurs.

## Business contexts

Exactly seven business contexts are permitted under `app/Contexts`:

- Accounts
- GameWorld
- Alliance
- Operations
- Intelligence
- Communications
- Platform

`Workflows`, `ReadModels`, and `Shared` are architectural categories, not business contexts.

## Capability ownership

Technical layers live inside an owning capability. Context-root `Actions`, `Models`, `Queries`, `Services`, `Policies`, and `Http` directories are prohibited.

## Authority model

`User` owns account/platform authority. A claimed `Player` is the game principal. Alliance membership, Alliance rank and roles, Kingdom roles, game visibility, and game actor identity are Player-scoped.

Request/authentication code may resolve Eloquent state while establishing identity, but downstream request/security context contains only immutable references, snapshots, scalar IDs, enums, and value objects.

Mutable authorization facts such as rank or role are not durable write authority. Protected writes reacquire current authority inside the transaction that owns the mutation.

## Persistence and transactions

Each context owns writes to its state. Cross-context callers use IDs, immutable references, owner Actions/Queries, durable events, or explicit Workflows. Cross-context Eloquent relationships are prohibited as application navigation APIs. Public write contracts neither accept nor return Eloquent models. Write actions do not interpret another context's permission enum; they call semantic authorization APIs owned by that context.

HTTP adapters do not own transactions or direct persistence. Workflows do not own domain persistence. ReadModels do not write.

## Communications

Communications owns generic delivery concepts only. Operations owns Event, King Perk, Participation, Rally, and other reminder semantics.

## Clean-room rule

The V3 implementation does not preserve deprecated namespaces, aliases, dual signatures, compatibility branches, or legacy fixtures merely to keep V2 callers alive. Replaced callers and tests are rewritten against final V3 contracts.
