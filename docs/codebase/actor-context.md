# Actor context

Status: Current — Architecture V3

The application carries distinct account and game principals.

## Account actor

`App\Contexts\Accounts\Identity\Models\User` is the authenticated account identity. Account assurance capabilities such as Authentication, Credentials, EmailVerification and MultiFactorAuthentication operate on this principal.

## Game actor

`App\Contexts\GameWorld\Players\Models\Player` is the game-domain principal. `GameWorld/Players` owns active Player resolution/activation.

A User may own multiple Players through scalar `user_id`, but game authority is derived only from the currently active Player.

## Scope authority

- Alliance authority: `Alliance/Access`, using the active Player's current Alliance relationship.
- Kingdom authority: `GameWorld/Governance`, using current Player-scoped Kingdom governance state.
- Operations authority: `Operations/Access`.
- Intelligence authority: `Intelligence/Access`.
- Platform authority: `Platform/Administration`, User-scoped only.

## Writes

Request-level actor context establishes who is acting. Mutable authorization for sensitive writes is revalidated by the owning capability Action inside its transaction after the owner acquires the required state locks.