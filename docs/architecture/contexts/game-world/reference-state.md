# Reference and placement state

Status: Current  
Context: GameWorld

GameWorld owns the neutral identity/reference facts that higher-level contexts need without owning those contexts' behavior.

## Owns

- durable Player identity;
- Player-to-User claim/ownership relationship;
- Kingdom identity;
- current Player placement/state required as neutral game facts;
- game-Alliance references needed to anchor Alliance identity/placement.

## Boundary

GameWorld reference models should expose identifiers/reference state rather than navigating into higher-level context aggregates. An Intelligence observation about a Player is not a second writable Player identity. An Alliance membership is not GameWorld state merely because it references a Player.

## Historical use

Downstream historical facts may retain GameWorld identifiers, but later changes to current placement must not rewrite historical Event/Intelligence attribution.