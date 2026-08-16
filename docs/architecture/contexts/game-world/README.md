# GameWorld context

Status: Current  
Implementation: `app/Contexts/GameWorld`

## Purpose

GameWorld is the neutral KingShot identity and governance foundation. It owns durable Player/Kingdom identity, placement/reference state and Kingdom governance primitives without absorbing downstream feature policy.

## Owns

- Player identity persistence and account claim relationship;
- Kingdom identity/resolution;
- game-Alliance reference/placement facts;
- active Player context support;
- current placement facts;
- Kingdom roles and governance assignment state;
- Kingdom/Player transaction-time mutation authority primitives.

## Boundary

GameWorld exposes IDs and current governance/reference facts. It does not interpret `events.*`, Intelligence or Alliance-specific permission vocabularies for downstream contexts.

Player is the game-domain principal. User is the account principal.