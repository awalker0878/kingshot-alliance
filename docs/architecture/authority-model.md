# Authority model

Status: Current

Kingshot Alliance separates **account authority** from **game-domain authority**.

```text
User (Accounts)
 |
 | owns/claims
 v
Player (GameWorld)  <---- active Player selected for the request
 |
 +--> current Kingdom / Kingdom governance
 |
 +--> Alliance membership
 |      +--> R1-R5 rank
 |      +--> specialist roles
 |
 +--> Operations authority derived for this Player and scope
 +--> Intelligence authority derived for this Player and scope

User
 +--> Platform Administrator grant (Platform only)
```

## Rules

- A `User` is the authenticated account identity and may own multiple Players.
- The active `Player` is the security principal for game-domain behavior.
- Authority is never aggregated across every Player owned by one User.
- Switching active Player changes effective Alliance/Kingdom/game authority to the selected Player's authority.
- Alliance membership, rank and specialist roles belong to the Player's membership, not the User.
- Kingdom role assignments are Player-scoped within the concrete Kingdom.
- Operations and Intelligence own interpretation of their own permission vocabulary. They may consume current Alliance/GameWorld facts but must not delegate their policy semantics back to those contexts.
- Platform Administrator is a User-scoped cross-tenant SaaS grant and does not bypass Alliance, Kingdom, Operations or Intelligence game authorization.

## Transaction-time authority

For protected writes, authorization that depends on mutable scope state must be resolved inside the write transaction after the relevant scope records are locked. Pre-request context is useful for navigation and early rejection; it is not a substitute for transaction-time authorization.