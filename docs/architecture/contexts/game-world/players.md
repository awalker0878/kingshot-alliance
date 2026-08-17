# GameWorld — Players

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Players`

Players owns durable Player identity/claim behavior, the scalar User ownership reference, Player lookup/ownership queries and active Player selection.

## Invariants

- one User may own multiple Players;
- the active Player is the game-domain principal;
- active Player selection must validate ownership by the authenticated User;
- authority is never aggregated across a User's Players;
- Player does not expose an Eloquent relationship into Accounts User.

Active Player activation is a GameWorld/Players Action, not a Workflow.