# GameWorld context

Status: Current  
Implementation: `app/Contexts/GameWorld`

GameWorld is the neutral KingShot identity and governance foundation. It owns durable Player/Kingdom identity, placement/reference state and Kingdom governance primitives without absorbing downstream feature policy.

## Capabilities

- [Player context](player-context.md) — Player claim/ownership and active Player resolution.
- [Reference and placement state](reference-state.md) — neutral Player, Kingdom and game-Alliance reference facts.
- [Kingdom governance](kingdom-governance.md) — Kingdom roles, assignments and transaction-time Kingdom transaction-time authorization.

## Boundary

GameWorld exposes stable IDs and current governance/reference facts. It does not interpret `events.*`, Intelligence or Alliance-specific permission vocabularies for downstream contexts.

Player is the game-domain principal. User is the account principal.