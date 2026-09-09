# ADR-0023: Serialize role-definition revocation with Alliance writes

Status: Accepted

## Problem and superseded implementation

AllianceWriteState already acquires a shared Alliance lock and the actor's membership before an ordinary protected write. Updating or archiving a role previously used that same shared scope, then changed permissions or detached multiple holders without coordinating with those holders' transactions. PostgreSQL's ordinary permission reads could see the old committed definition during revocation, or revocation could finish while an already-authorized write still held its transaction.

## Decision and alternatives

UpdateAllianceRole and ArchiveAllianceRole use the existing exclusive Alliance scope before acquiring the administrator membership and role. Ordinary writes retain the shared scope. If a writer is admitted first, revocation waits for it. If revocation starts first, later writers wait and then interpret the current committed permissions. Current-role assignment and rank changes continue through their existing owner boundaries.

This applies to changes affecting an existing role's holders. Creating an unpublished role or provisioning initial roles does not revoke existing authority. No new permission version, cache invalidation authority, advisory lock or per-holder lock sweep is introduced. Locking every assigned membership would grow with holder count and create additional lock-order interactions; the existing Alliance row already provides the required coordination point. Using an exclusive lock for every ordinary write would unnecessarily serialize unrelated work.

## Ownership, security and scaling

Alliance remains the owner of role facts and transaction-time authority acquisition. Permission interpretation remains lock-free in the authorization services. The lock order is Alliance, current Kingdom, administrator membership, then role; raw/network work is not introduced into these transactions. Definition changes briefly serialize writes within one Alliance, while other Alliances remain independent. Audit and outbox intent commit or roll back with the definition and its assignments. Operations and other consumers retain their existing protected authority contracts.

## Verification

AllianceRoleRevocationConcurrencyV3Test uses independently named PostgreSQL connections for both lock orders, with actual protected content writes and actual role updates/archives. It checks bounded lock contention and rejection on retry, independence of another Alliance in the same Kingdom, and real late-audit rollback preserving definition, assignments and effective authority. HARD-046 records executable containing results.
