# Accounts — Identity

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Identity`

Identity owns the durable User account identity used by authentication and account-scoped platform behavior.

## Boundary

User is the account principal, not the game-domain principal. Player identity belongs to `GameWorld/Players`.

Cross-context consumers use stable User identifiers or explicit Accounts contracts rather than owning Accounts persistence.