# Alliance

Owns Alliance tenant lifecycle and cohesive Alliance operations: core/settings, membership, R1-R5 leadership, specialist roles, invitations, recruitment and Alliance content.

## Authority model

Alliance game-domain authority is **Player-scoped**, never User-scoped.

- A `User` is an account identity and may own multiple Players, but does not itself hold Alliance membership, rank, specialist roles, or Alliance permissions.
- The active `Player` is the game-domain security principal.
- Alliance membership belongs to a Player through `player_id`.
- R1-R5 rank belongs to that Player's Alliance membership.
- Specialist Alliance roles are assigned to that Player's Alliance membership.
- Alliance permissions are evaluated from the active Player's current active membership, rank, and specialist roles within the concrete Alliance scope.
- Authority is never aggregated across all Players owned by the same User.
- Switching active Player changes the effective Alliance authority to that selected Player's authority.
- A Platform Administrator is not an Alliance/game-domain authorization bypass.

Authentication establishes which User account is operating the application. It does not, by itself, grant Alliance access or permissions.
