# ADR-0026: Recover role-creation collisions and bound archival records

Status: Accepted

## Context

CreateAllianceRole previously checked an absent role key with a row lock. Shared Alliance scope permits different administrators to create roles concurrently, and an absent PostgreSQL row cannot serialize their inserts. The losing unique-key insert escaped as a database error. ArchiveAllianceRole also loaded all assigned membership IDs and copied that unbounded list into both audit and outbox records. Suspended historical memberships are not bounded by active Alliance capacity.

## Decision

Alliance Access retains both mutations. CreateAllianceRole uses maintained Eloquent firstOrCreate inside its owner transaction. The framework's createOrFirst savepoint recovers a competing unique insert and reads the exact Alliance/key winner. An existing winner produces name validation before permission or event writes. Unrelated uniqueness failures propagate. Current permission checks and the database Alliance/key constraint remain authoritative.

ArchiveAllianceRole retains the exclusive Alliance revocation barrier from ADR-0023. It deletes assignments in one statement scoped by Alliance and role, using the existing membership_roles(alliance_id,role_id) index. The SQL affected-row count becomes removed_membership_count in audit/outbox metadata. Role ID/key identify the subject; no membership list is materialized. Archival, assignments and both durable records commit or roll back together. Repeating an already completed archival produces no additional event.

## Alternatives

An exclusive Alliance lock for every creation would serialize unrelated role keys unnecessarily. Catching every database exception as a duplicate name would conceal other faults and could leave PostgreSQL caller transactions unusable. Application pre-counting or chunked metadata would retain unnecessary work or unbounded durable payloads. No production consumer requires individual removed membership IDs.

## Consequences and verification

Creation preserves one complete winner per key without changing other administrators' definitions. Archival uses constant application memory and query count while database deletion scales with affected assignments. Security interpretation, tenant constraints and current lock order remain intact. The undeployed application's event contract changes directly; there is no historical backfill or dual payload.

Separate-connection regressions exercise same/different keys, safe retries, usable caller transactions, unrelated unique failures and late audit rollback. Archival regressions exercise zero, one and 1,000 historical assignments, scoped deletion, bounded exact metadata, tenant isolation, repeated archival and rollback. Runtime results remain in the delivery ledger.
