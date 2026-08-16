# ADR 0002: Player-scoped game authority

Status: Accepted

## Decision

Treat User as account identity and active Player as the game-domain security principal.

Alliance membership/rank/specialist roles and Kingdom/game authority are Player-scoped. Platform Administrator remains the only User-scoped administrative grant and provides no in-game bypass.

## Rationale

A User can own multiple Players with different memberships, Kingdom placement and roles. Aggregating authority at User level would allow one persona's privileges to leak into another.

## Consequences

Every game-domain request that depends on actor authority resolves and validates the active Player. Protected writes re-evaluate mutable authority at transaction time where required.