# Actor context

Status: Current

The application carries two distinct actor concepts.

## Account actor

`App\Contexts\Accounts\Models\User` is the authenticated account identity. Account-level security such as verified email, password confirmation and MFA attaches to this identity.

## Game actor

`App\Contexts\GameWorld\Models\Player` is the game-domain principal. `ResolvePlayerContext` and the GameWorld Player context services support selection/validation of the active Player.

Game authorization must use the active Player rather than aggregating every Player owned by the authenticated User.

## Scope authority

- Alliance authority: current active Player membership + rank + specialist roles in the concrete Alliance.
- Kingdom authority: current Player-scoped Kingdom governance assignment in the concrete Kingdom.
- Operations/Intelligence authority: interpreted by those owning contexts using the current Player/scope facts they require.
- Platform authority: User-scoped Platform Administrator grant only; never a game bypass.

## Writes

Request-level actor context is not enough for mutable authorization. Actions whose permission depends on mutable membership/role/scope state must use their transaction-time mutation authority services inside the write transaction.