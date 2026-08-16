# Kingdom governance

Status: Current  
Context: GameWorld  
Implementation: `app/Contexts/GameWorld/Governance`

## Purpose

Own current Player-scoped Kingdom governance facts and the authority primitives used to mutate them safely.

## Owns

- Kingdom role definitions/assignments represented by the GameWorld governance model;
- Kingdom permission evaluation for GameWorld-owned governance actions;
- transaction-time Kingdom mutation context/authority.

Current Kingdom permission vocabulary includes `kingdom.roles.manage`.

## Invariants

Kingdom authority is Player-scoped and concrete-Kingdom-scoped. Platform Administrator does not bypass it. Writes that depend on mutable Kingdom role/scope state must revalidate authority inside the transaction after relevant scope locks.

## Boundary

GameWorld supplies current Kingdom governance facts to Operations/Intelligence/workflows. It does not interpret Operations `events.*` or Intelligence permission keys.