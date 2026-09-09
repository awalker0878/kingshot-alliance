# ADR-0032: Preserve account lifecycle serialization with compatible audit references

Status: Accepted

## Context

AccountIdentityQuery acquired User FOR UPDATE before GameWorld ownership writes or Alliance cleanup. Audited Alliance writers acquired Alliance and Player resources before inserting audit_events.actor_user_id. PostgreSQL checks that foreign key with a User KEY SHARE lock. A current officer's deletion or ownership operation could therefore hold User while waiting for the writer's Alliance/Player resource, with the writer waiting for the same User merely to record its actor reference.

## Decision

AccountIdentityQuery.lockCurrent and lockActive acquire FOR NO KEY UPDATE. This remains an exclusive lifecycle barrier against other account owner barriers, mutable User writes, claim, release, creation, reconciliation and finalization. It permits KEY SHARE references to the immutable User ID. Active-account validation still occurs after acquiring the barrier.

Actual account mutation owners retain their existing row locks. Account deletion first acquires the reference-compatible lifecycle barrier, completes dependent membership/ownership cleanup, and then invokes AnonymizeAccount, which acquires FOR UPDATE before changing account fields. The audit foreign key, actor attribution, transactional cleanup and terminal-state checks remain enforced.

PostgreSQL's [row-level lock compatibility rules](https://www.postgresql.org/docs/18/explicit-locking.html#LOCKING-ROWS) distinguish immutable key references from mutable row updates. Account primary keys are not changed by lifecycle-reference consumers; an owner that changes a key still acquires the database-required stronger lock.

## Alternatives

Removing the audit foreign key would weaken referential integrity. Acquiring no account lock would reintroduce ownership/finalization races. Locking every actor account before every Alliance operation would spread account ownership synchronization across unrelated domain entrypoints. Deferring the foreign-key constraint until commit would retain the same circular resource dependency because transaction locks are still held while constraints are checked.

## Consequences and verification

Account owner mutations remain serialized while unrelated audit insertions can finish and release the resources awaited by cleanup. Six PostgreSQL connection cases exercise both account-barrier/writer orders, successful and failed cleanup, preserved actor attribution, revoked terminal authority, exclusive claim/profile competitors and full rollback. Existing creation, ownership and onboarding concurrency hooks recognize both exclusive row-lock spellings while preserving their blocking and current-state assertions. Full account, identity and containing suites remain required; execution evidence belongs in the delivery ledger.
