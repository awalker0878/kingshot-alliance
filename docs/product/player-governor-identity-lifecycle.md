# Player / Governor identity and lifecycle

Status: **Current complete capability**

Architectural owner: `GameWorld/Players`

User-facing term: **Governor**. Internal domain term: **Player**.

This document is the implementation source of truth for durable Governor identity, account ownership, active Governor context, identity history, self-service lifecycle management, and explicit duplicate reconciliation.

## Product outcome

A Kingshot Alliance account can own multiple Governors while operating as exactly one active Governor at a time. Governors remain durable game identities independent of account deletion or release. The product provides a first-class **My Governors** surface for creating, viewing, renaming, attaching a stable game Player ID, moving an eligible Governor between Kingdoms, and voluntarily releasing an eligible Governor from the account.

The active Governor remains the game-domain principal. Account identity is never substituted for game authority and authority is never aggregated across Governors.

## Ownership boundaries

`GameWorld/Players` owns:

- durable Player identity;
- current account ownership reference (`players.user_id`);
- current name, current Kingdom and stable game Player ID projection;
- immutable temporal identity/ownership history;
- canonical/duplicate identity reconciliation metadata;
- Player lookup and ownership queries;
- active Player selection;
- Player lifecycle policy and Player-owned lifecycle audit events.

Other contexts retain ownership of their facts. In particular, Players does not own Alliance membership, roster state, Kingdom governance, transfer-plan facts, Events, Rallies, Results, Evidence, Gift Code redemption facts, Communications facts, or other operational state.

Cross-context facts are not copied into Player history. Provenance stores source type/reference metadata only.

## Core invariants

1. One User may own zero, one, or many Players.
2. At most one Player is active in a request/session context.
3. Active Player selection validates authenticated User ownership server-side.
4. Browser-provided Alliance, Kingdom, rank, role, membership or capability identifiers never establish authority.
5. Authority is never unioned across Players.
6. Player does not expose an Eloquent relationship into Accounts User.
7. A stable game Player ID, when known, is the strongest application identity key.
8. An established stable game Player ID cannot be silently replaced.
9. Name similarity is discovery evidence only and never authorizes an automatic merge.
10. Reconciled aliases are not current operational/owned Players.
11. Player release clears account ownership but preserves the durable game identity and history.
12. Kingdom movement is explicit and fails closed while current-Kingdom Governance, active Alliance membership, or conflicting active/tracked roster facts remain.
13. Reconciliation is explicit, audited, reasoned and evidence/provenance capable.
14. Reconciliation never directly rewrites foreign-owner tables from `GameWorld/Players`.

## Current authoritative projection

The `players` table remains the small current-state projection used for hot-path reads:

- `user_id` — nullable current account owner;
- `current_kingdom_id` — current Kingdom identity;
- `game_player_id` — nullable stable game identity;
- `current_name` — current observed/display name;
- `canonical_player_id` — nullable canonical Player when this row is a reconciled alias.

Current ownership, active-context selection, stable-ID lookup and normal Kingdom reads exclude rows whose `canonical_player_id` is set.

`PlayerReferenceQuery::findCanonical()` / `requireCanonical()` provide explicit canonical resolution for historical alias identifiers. Canonical traversal is bounded and fails on loops/excessive chains.

## Immutable temporal identity history

`player_identity_history` records temporal snapshots rather than overwriting the historical record. Exactly one current history row may exist for an unreconciled Player.

Each history row can record:

- Player;
- account owner at that time;
- Kingdom;
- name;
- stable game Player ID;
- `valid_from` / `valid_to`;
- provenance source type and source reference;
- observation time;
- optional confidence in basis points;
- reason.

The current `players` row remains authoritative for current reads; the history table is provenance/history, not a competing truth store.

## Lifecycle operations

Public intent-specific Actions are used instead of a broad Player CRUD service:

- `CreatePlayerForAccount` — create a Governor for the authenticated account while enforcing identity rules;
- `UpdateOwnedPlayerIdentity` — update safe owned identity fields;
- `MoveOwnedPlayerToKingdom` — perform an explicit guarded Kingdom move;
- `ClaimPlayerAccount` — claim an unowned Player or idempotently retain same-account ownership;
- `ReleasePlayerAccount` — voluntarily release an owned Governor after lifecycle blockers pass;
- `ReleasePlayersFromAccount` — account-deletion orchestration path;
- `PersistPlayerIdentity` — lower-level identity persistence used by trusted domain/workflow integrations;
- `ReconcilePlayers` — explicit canonical/duplicate reconciliation for safe dormant duplicate identities.

Lifecycle changes write temporal history and the appropriate audit event.

## Kingdom movement

A Player may remain in the same Kingdom without invoking cross-Kingdom blockers. A move to a different Kingdom is rejected while any of these facts remain:

- effective Kingdom Governance assignments in the current Kingdom;
- active Alliance membership;
- active or tracked Alliance roster state that conflicts with the destination Kingdom.

The target Kingdom must exist and be available. Transfer planning/eligibility remains owned by `GameWorld/KingdomTransfers`; Players owns only the resulting current Player Kingdom identity fact.

## Release and account deletion

Voluntary release is intentionally stricter than account deletion orchestration.

Voluntary release requires the Player to be owned by the requesting account and to have no effective Governance role, active Alliance membership, or active/tracked roster presence. Release clears `user_id`, preserves the Player row and stable identity, transitions history and emits audit evidence.

Platform Data Governance remains responsible for account-deletion orchestration. It coordinates foreign-owner cleanup first and then calls `ReleasePlayersFromAccount`, preserving durable Player identities while removing account ownership.

## Reconciliation

Reconciliation is never inferred from a similar name. `PlayerReconciliationCandidateQuery` can surface candidates and reason codes, but `ReconcilePlayers` requires an explicit canonical Player, duplicate Player and non-empty reason.

Direct reconciliation currently requires:

- two distinct direct Player identities;
- the same current Kingdom;
- no conflicting non-null stable game Player IDs;
- no conflicting non-null account owners;
- a duplicate that is dormant with respect to current Governance, Alliance membership and active/tracked roster state.

When accepted:

- late stable-ID/account-ownership facts may transfer to the canonical identity;
- the duplicate's current identity-history row is closed;
- duplicate ownership and stable ID are cleared;
- `canonical_player_id` points to the canonical Player;
- an immutable `player_reconciliations` row is written;
- `player.reconciled` audit evidence is emitted;
- current owned/operational Player reads exclude the alias.

This is a deliberate owner-boundary rule: `GameWorld/Players` does **not** rewrite foreign-owner operational tables during reconciliation. A duplicate with live owner-context dependencies must first be resolved through the owning context's normal workflow, then reconciled. Historical references may continue to identify the historical alias; callers that need present identity use canonical resolution.

## Self-service My Governors

Authenticated, verified accounts receive these routes:

- `GET /governors` — My Governors;
- `POST /governors` — create Governor;
- `PATCH /governors/{player}` — safe identity update;
- `POST /governors/{player}/move` — explicit Kingdom move;
- `DELETE /governors/{player}` — voluntary release, protected by recent password confirmation.

The page exposes current identity, Kingdom, stable ID, active state, lifecycle blockers and bounded recent identity history. The existing Identity Switcher remains the fast active-context control. For multi-Governor accounts its menu links to **Manage Governors** without changing the closed application shell on unrelated pages.

Zero-Governor accounts can use My Governors to create their first Governor. One-Governor accounts continue to auto-select that Governor in request context. Multiple-Governor accounts require an explicit active selection.

## Authority-context safety

The existing authority-context version remains authoritative and includes the Player plus Alliance/Kingdom authorization facts. Non-safe game mutations require the current `X-Game-Context-Version`; stale authority fails with `409 CONTEXT_STALE`.

Lifecycle operations that invalidate current ownership or Player identity cannot be used to preserve stale game authority: current Player resolution reloads ownership, reconciled aliases are excluded from current ownership reads, and game mutations re-read authority at request time.

Governor activation remains exempt from the current-context precondition because it is the operation used to establish/change active context; it still validates account ownership server-side.

## Audit and diagnostics

Player lifecycle events include the existing `player.context_changed` plus lifecycle events emitted by the new Actions, including creation/claim/release/identity/Kingdom/reconciliation changes. Audit payloads identify the Player lifecycle transition and provenance needed for investigation without copying unrelated owner-context truth.

`PlayersIntegrityQuery` provides bounded integrity diagnostics, including unresolved duplicate candidates and reconciliation/identity inconsistencies. Diagnostics are operational evidence, not automatic mutation triggers.

## Acceptance contract

The capability is complete only while all of the following remain true:

- blank names fail closed and normalized names are persisted;
- stable-ID attach/reuse works and mutation/collision is rejected;
- guarded Kingdom movement directly tests Governance, membership and roster blockers;
- claim is same-owner idempotent and cross-owner safe;
- voluntary release preserves identity and rejects live dependencies;
- account deletion releases ownership while preserving Player identity;
- activation records `player.context_changed` audit evidence;
- PlayerReferenceQuery current reads exclude reconciled aliases and canonical resolution remains bounded;
- similarity-only candidates never auto-merge;
- conflicting Kingdom, owner or stable-ID reconciliation fails closed;
- self-service Governor routes enforce authenticated ownership and explicit move/release semantics;
- stale browser context cannot retain authority after Player lifecycle changes;
- application-shell visual baselines remain stable except where Governor management UI is intentionally exercised.

## Non-goals

- official Kingshot account authentication;
- storing game credentials;
- name-based automatic merges;
- authority aggregation across Governors;
- moving Alliance/Governance/Transfer/Evidence/Operations ownership into Players;
- silently rewriting foreign-owner historical facts when an identity is reconciled.
