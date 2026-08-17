# Persistence

Status: Current — Architecture V3

The application may use one PostgreSQL database, but business persistence ownership follows bounded-context capabilities rather than database proximity.

## Ownership rule

A capability owns the write model and invariants for its business state. Other contexts do not mutate that state through foreign Eloquent models or shared-table reach-through.

Cross-context references should normally use stable scalar identifiers such as:

```text
user_id
player_id
alliance_id
kingdom_id
event_id
```

A scalar reference does not transfer aggregate ownership.

## Eloquent relationships

Relationships are appropriate inside an ownership boundary where they express the owning model. Cross-context Eloquent navigation is not the integration mechanism for V3.

In particular, GameWorld `Player` must not expose an Eloquent relationship back into Accounts `User`; Player ownership is represented by `user_id` and owner queries/contracts.

## Cross-context access

For reads, use:

- the owner's Query/service contract when a stable owner fact is required; or
- a ReadModel for cross-context composition.

For writes, call the owning capability Action. Do not import a foreign Model merely because the database is shared.

## Migrations

Database migrations remain under `database/migrations`, but schema ownership must still be identifiable from the capability that writes the data. Migrations do not create a separate architectural layer or imply shared business ownership.

## Constraints

Database constraints should protect critical persistence invariants where practical. Application Actions remain responsible for business policy, current authorization and transactional sequencing.