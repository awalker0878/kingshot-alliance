# GameWorld — Players

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Players`

Players owns durable Player identity/claim behavior, the scalar User ownership reference, Player lookup/ownership queries, active Player selection, temporal identity history and explicit canonical identity reconciliation.

User-facing product term: **Governor**. Internal/domain term: **Player**.

## Invariants

- one User may own zero, one or multiple Players;
- the active Player is the game-domain principal;
- active Player selection must validate ownership by the authenticated User;
- authority is never aggregated across a User's Players;
- Player does not expose an Eloquent relationship into Accounts User;
- `players` remains the current authoritative identity projection;
- temporal identity/ownership history is retained in `player_identity_history` rather than inferred from current state;
- a stable game Player ID is the strongest identity key when known and cannot be silently replaced;
- name similarity may surface a reconciliation candidate but can never authorize an automatic merge;
- reconciled duplicate rows remain as historical aliases through `canonical_player_id` and are excluded from current owned/operational Player reads;
- Players does not rewrite foreign-owner tables during reconciliation;
- live Governance, Alliance membership or active/tracked roster dependencies must be resolved by their owning context before a duplicate can be reconciled;
- account release clears Player ownership but preserves the durable Player identity and its history;
- cross-Kingdom movement fails closed while current-Kingdom Governance, active Alliance membership or conflicting roster state remains.

## Lifecycle

Player-owned intent Actions include creation for an account, safe owned identity update, explicit owned Kingdom movement, claim, voluntary release, account-deletion release, trusted identity persistence and explicit reconciliation.

Ownership changes acquire the current Accounts lock before Player locks. Claim, creation, voluntary release and reconciliation consume `AccountIdentityQuery::lockActive`, which rejects finalized accounts while allowing accounts in the cooling-off period. DataGovernance release uses the current account lock already held by finalization. Accounts owns lifecycle validation; GameWorld owns every Player and history write.

Owned identity edits and Kingdom movement obtain OwnedPlayerWriteState within their mutation transaction: active account, active intended Kingdom, then current owned canonical Player. Changed placement is rejected after locking; movement uses the current name/stable ID and identity edits retain the current Kingdom. Both compose PersistPlayerIdentity so current projection, history and audit remain one atomic owner write. See [ADR-0029](../../adr/0029-current-owned-player-mutations.md).

Registration delegates its expected existing account-owner precondition to PersistPlayerIdentity, which checks the actual locked identity before mutation. Existing unclaimed or foreign-owned game identities cannot be silently adopted after an absent lookup. Stable-ID replacement uses the locked current identity and nonlocking foreign rejection witnesses; concurrent absent-ID conflicts are translated only after rolling back the owner transaction/savepoint, leaving the caller transaction usable. The unique constraint and unrelated integrity errors remain authoritative. See [ADR-0034](../../adr/0034-current-stable-player-identity-and-registration.md).

Reconciliation first discovers the two current owner IDs, locks those accounts in ascending order, then locks Players in ascending order and compares current owners with the discovery snapshot. A changed owner causes a retryable validation rejection before any identity write. Finalization holds the same account lock while enumerating and releasing the complete current ownership set, so assignments cannot arrive after enumeration.

The first-class `/governors` surface exposes the safe self-service subset. Voluntary release is recent-auth protected and subject to lifecycle blockers.

Active Player activation remains a `GameWorld/Players` Action, not a Workflow. It validates account ownership, emits `player.context_changed`, updates session context through its controller, and never accepts browser authority facts.

## History and reconciliation

`player_identity_history` stores temporal snapshots with provenance fields such as source/reference, observed time, optional confidence and reason. The current Player row remains authoritative for current reads.

`player_reconciliations` records the explicit canonical/duplicate decision. Historical aliases are preserved instead of deleted or globally repointed. Callers that need present identity from an historical alias use explicit canonical resolution.

See:

- `docs/product/player-governor-identity-lifecycle.md`
- `docs/product/player-governor-identity-lifecycle-delivery-ledger.md`
- `docs/architecture/adr/0017-preserve-player-identity-history-and-reconcile-by-canonical-alias.md`
