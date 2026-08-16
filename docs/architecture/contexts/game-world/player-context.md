# Player context

Status: Current  
Context: GameWorld  
Implementation: `app/Contexts/GameWorld/Services/PlayerContext.php` and `app/Workflows/PlayerContext`

## Purpose

Resolve the concrete KingShot persona being used for a game-domain request.

## Invariants

- a Player activated for a request must belong to the authenticated User;
- one User may own/claim multiple Players;
- the active Player, not the User, is the game-domain security principal;
- authority is never unioned across the User's other Players;
- switching Player changes the effective Alliance/Kingdom/Operations/Intelligence authority;
- if a User owns several Players, the application requires an explicit valid selection rather than guessing which persona should carry authority.

`PlayerContext::activate()` validates ownership before exposing the Player. Callers that require a Player should fail closed when context has not been resolved.

## Cross-context use

Alliance uses the active Player to resolve membership/rank/roles. GameWorld governance uses it for Kingdom roles. Operations and Intelligence use it as the actor identity while interpreting their own permission vocabulary.