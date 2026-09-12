# Accounts — Identity

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Identity`

Identity owns the durable User account identity used by authentication and account-scoped platform behavior.

## Boundary

User is the account principal, not the game-domain principal. Player identity belongs to `GameWorld/Players`.

Cross-context consumers use stable User identifiers or explicit Accounts contracts rather than owning Accounts persistence.

Ordinary account mutations acquire the current User row lock and call its explicit ensureActive guard before reading dependent credentials or writing personal state. Anonymization is terminal; stale authenticated model instances are not lifecycle authority. AccountIdentityQuery.lockActive applies the same guard for cross-context owner composition. Reporting and explicit lifecycle queries can still read finalized identities; there is no global query scope or model-save callback hiding them.

AnonymizeAccount captures registered session IDs while locked, removes durable authentication state and commits the terminal account with its audit. Raw session-handler cleanup runs only after the outermost transaction commits, including Platform deletion request status and intent. Rollback discards cleanup; individual storage exceptions are reported while other sessions/accounts continue. The terminal guard remains authoritative if raw storage survives until expiry.
