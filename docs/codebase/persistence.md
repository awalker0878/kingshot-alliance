# Persistence

Status: Current

The application uses PostgreSQL as the relational store. Eloquent models are located with the context/capability that owns the writable fact.

## Ownership

Database proximity does not make tables shared. A context may reference another context's durable identifier when required, but the owning context remains the only place that should create/change that business fact except through supported application contracts.

Examples:

- Accounts owns User account state.
- GameWorld owns Player/Kingdom identity and neutral placement/governance state.
- Alliance owns Alliance membership/rank/role state.
- Operations owns operational Event state.
- Intelligence owns observations/analytical history.
- Communications owns delivery state.
- Platform owns platform grants/integration administration.

## Constraints

Use database uniqueness/foreign-key/check constraints where they express invariants that must survive concurrent application requests. Application validation is not a substitute for constraints that protect persistence integrity.

## Migrations

Migrations live under `database/migrations`. A migration should reflect the logical owner even though migrations are physically centralized. Schema changes must consider forward deployment, existing data, rollback/recovery and concurrent runtime behavior.

## Read models

ReadModels may query several owners to compose a projection but do not take ownership of those source records or write back into the source aggregates.