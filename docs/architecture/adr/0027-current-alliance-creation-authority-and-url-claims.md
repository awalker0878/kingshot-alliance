# ADR-0027: Create Alliances with current ownership and recover URL claims

Status: Accepted

## Context

Alliance creation accepted only a Player identifier and checked an unlocked claimed-Player snapshot. Account release, reconciliation or deletion could revoke that ownership before creation established R5 membership. Competing creation or membership activation could also pass the absent active-membership check. Creation and settings updates checked URL availability before the database's unique constraint resolved concurrent claims; locking an absent URL row does not serialize those claims.

## Decision

CreateAlliance requires the authenticated account ID alongside the intended Player ID. Every caller supplies that identity explicitly. The owner transaction locks the active account through Accounts, discovers and verifies the current canonical Player owner, locks its active Kingdom shared through GameWorld, then locks the current Player and rechecks ownership and Kingdom placement. The order is account, Kingdom, Player. The account barrier coordinates creation with release, reconciliation and deletion; Kingdom-before-Player matches identity persistence. A changed placement is rejected for reload. Existing membership discovery remains nonlocking, avoiding a Player-to-existing-membership lock inversion.

The database remains authoritative for the globally unique Alliance URL and one active membership per Player. Creation uses maintained Eloquent firstOrCreate and its savepoint-backed createOrFirst recovery. A pre-existing or competing exact URL winner produces slug validation before any bootstrap records. The new R5 membership insert uses a savepoint; a competing active membership produces Player validation and rolls back the new Alliance. Unrelated unique failures propagate.

Settings updates retain their current Alliance authorization and lock scope. The save occurs inside a savepoint. A uniqueness failure is translated only when a nonlocking exact URL lookup finds another Alliance; otherwise the original database exception propagates. No operation locks a different Alliance merely to check its URL. Bootstrap records, settings, audit and outbox remain in the same outer transaction.

## Alternatives

Inferring the account from the supplied Player would authorize whichever account owns that Player at execution time instead of the caller. Controller-only checks would leave direct and delayed owner calls unsafe. Locking all Alliances or a global URL mutex would serialize independent administration. Catching all database failures or querying after an unrecovered PostgreSQL statement error would conceal faults or poison enclosing transactions.

## Consequences and verification

The undeployed application's creation contract changes directly with all HTTP, scenario and visual callers migrated. There is no compatibility signature or duplicated persistence path. Independent owners and URLs can proceed concurrently; contested claims preserve one winner with ordinary field feedback. Lock scope is bounded to the current account, Kingdom and Player plus the new Alliance's records.

Separate-connection tests cover release, deletion, reconciliation and competing creation in both commit orders; ownership mismatch, inactive Kingdom, placement changes between discovery and locking, and competing membership activation. URL tests cover all create/update pairings, independent URLs, repeat collisions, usable enclosing transactions, unrelated uniqueness errors and late audit rollback of all bootstrap records. Runtime results are recorded in the delivery ledger.
