# ADR-0029: Mutate owned Players under current account and identity locks

Status: Accepted

## Context

MoveOwnedPlayerToKingdom and UpdateOwnedPlayerIdentity read claimed Player snapshots before entering identity persistence. The later transaction did not revalidate the requesting account. Release, deletion or reassignment could revoke ownership after the initial read; concurrent system observations could also change the name, stable ID or Kingdom that the wrapper copied into its command.

## Decision

Both owned mutation Actions open a transaction and obtain OwnedPlayerWriteState. This GameWorld service locks the active account, discovers the current owned canonical Player, locks the intended active Kingdom, then locks and rechecks the owned Player and discovered Kingdom. Account, Kingdom, Player ordering matches Alliance creation and trusted identity persistence. Foreign or released identities retain not-found behavior; finalized accounts and changed Kingdom placement produce the existing owner validation vocabulary.

Movement reads name and stable ID from the locked Player. Identity edits use the locked current Kingdom and explicit requested identity input. Both continue through PersistPlayerIdentity in the same outer transaction. History, current projection and audit retain one persistence implementation and commit or roll back together.

## Alternatives

Adding only a second unlocked ownership check would leave the race open. Locking Player before Kingdom would reverse trusted persistence ordering. Copying transition/history code into the account-facing wrappers would create another identity authority. System ingestion remains a separate trusted entry point and does not impersonate an account; current placement is revalidated when its updates interleave with account-bound intent.

## Consequences and verification

Account-bound movement and edits serialize with release, deletion and reassignment while preserving the existing identity lifecycle rules. Unrelated accounts and Players remain independent. Sixteen PostgreSQL regressions cover both mutation types and commit orders against all three ownership changes, current name/stable-ID observations, changed placement and late audit rollback. Existing Governor HTTP and identity lifecycle suites remain required. Runtime results are recorded in the delivery ledger.
