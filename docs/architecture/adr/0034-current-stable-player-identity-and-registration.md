# ADR-0034: Enforce stable Player identity and registration on current owner facts

Status: Accepted

## Context

PersistPlayerIdentity locked an expected Player and then an unrelated Player owning a proposed game ID, even when it only needed to reject the edit. Opposing replacements could form an inverse dependency. Two writers observing an absent stable ID could also reach the unique index and expose a database error. CreatePlayerForAccount separately checked existing identity before the persistence owner's Kingdom lock; a concurrently arriving unclaimed identity could then be reused and silently claimed after that stale absence check.

The transfer planning adapter repeated the early Player locking pattern before its source Kingdom and did not exclude reconciled aliases from the current identity query.

## Decision

PersistPlayerIdentity remains the single current identity/history writer. Under its active Kingdom and canonical Player locks, immutable stable-ID replacement is rejected before any foreign identity lookup. A foreign owner is observed as a nonlocking rejection witness. The existing unique constraint remains authoritative for concurrent absent-ID attachment or creation. Only its exact `players_game_player_id_unique` PostgreSQL diagnostic is translated to ordinary game-ID validation after the owner transaction/savepoint has rolled back; unrelated integrity exceptions propagate unchanged. Callers can continue using an enclosing transaction after handling that validation.

Account registration passes an explicit expected existing account-owner precondition into persistence. It applies to the actual locked identity before any name, placement or history change. Existing identity reuse is permitted only when it is currently owned by that account; an unclaimed or foreign-owned identity requires the existing explicit claim/recovery flow. New identity creation, claim, history and audit remain one transaction under the active account barrier. The early duplicate lookup is removed, restoring account, Kingdom, Player acquisition order. Trusted identity observations retain their existing unclaimed/reuse semantics and do not infer account ownership.

ResolveTransferPlayer starts an owner transaction, locks its active source Kingdom shared before Player, requires current canonical identity and rejects a conflicting stable ID without locking the foreign Player. It retains transfer-specific placement validation and delegates all identity/history writes to PersistPlayerIdentity. An existing participant bound to a reconciled alias must be explicitly replaced through the established participant workflow.

## Alternatives and consequences

Removing uniqueness would permit duplicate durable identities. Retrying registration by adopting the winner would silently change ownership intent. Serializing every identity through a global lock would unnecessarily block independent Players. The selected contracts keep ordinary scope concurrency, enforce current preconditions at the owner, and retain a recoverable database arbiter for absent identities.

Twenty-six PostgreSQL cases cover planning archival and movement orders, opposing edits, alias rejection, late planning rollback, absent stable-ID creation and attachment with both winners, usable caller transactions, unrelated primary-key failures, unclaimed/foreign registration arrivals, same-owner reuse, registration archival ordering and complete identity/claim/history rollback. Existing current-account, identity lifecycle, HTTP, reconciliation, transfer and containing suites remain required. Executable evidence belongs in the delivery ledger.
