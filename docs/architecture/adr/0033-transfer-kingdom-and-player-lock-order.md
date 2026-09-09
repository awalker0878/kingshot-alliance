# ADR-0033: Stabilize transfer Kingdom scope before Player handoff

Status: Accepted

## Context

Transfer completion held its target Player before acquiring the outgoing destination Kingdom through PersistPlayerIdentity. Ordinary identity mutation acquired Kingdom before Player. Destination archival could complete a circular wait between these writers. TransferWriteState also exclusively locked the actor Player even though consumers only read actor identity; opposing Alliances completing transfers of each other's active officers could therefore block before rejecting their existing memberships.

## Decision

Completion reads scoped participant routing, then acquires current Alliance authority and all required Kingdom rows before any Player mutation lock. Home and outgoing destination Kingdom IDs are sorted and locked shared. Home must remain active. The destination current-state lock includes archived rows so an already completed outgoing transfer can remain an idempotent retry; an actual new identity movement still requires an active destination.

The current locked participant must match the discovered Player, direction and destination before any new handoff. Existing completion returns without repeating owner effects. The target Player must remain canonical and is locked before roster handoff. Both transfer roster Actions acquire their own current Player lock before roster rows, so correctness does not depend on one caller's implementation.

TransferWriteState retains its exclusive Alliance and current active actor membership barriers. It reads canonical actor identity without locking the actor Player. Existing identity lifecycle policies prohibit moving, releasing or reconciling an identity with active membership; revoking that membership must acquire the held Alliance/membership scope. Consumers of this authority contract do not mutate actor identity through the returned value object. A target writer acquires its own required identity lock in its proper resource order.

## Consequences and verification

Alliances can complete independent Players in the same Kingdoms concurrently. Archival serializes with completion before Player handoff; changed routing rejects atomically. Permissions, locked-plan requirements, active membership/governance/roster guards, capacity reconciliation, immutable identity history, audit and outbox remain in the same transaction.

Fifteen PostgreSQL cases cover all three directions against archival in both orders, sorted outgoing Kingdom locks, independent Alliances, opposing officer targets, changed routing, completed retry after destination archival, direct roster admission versus identity movement in both orders, and late delivery rollback for every direction. Existing planning, completion-capacity and Evidence suites remain required. Execution evidence is recorded separately in the delivery ledger.

The separate ResolveTransferPlayer planning adapter is addressed by [ADR-0034](0034-current-stable-player-identity-and-registration.md); completion verification alone does not establish planning coverage.
