# Player / Governor Identity & Lifecycle — Delivery Ledger

Status: **Complete — current capability**

Deployment assumption: fresh schema; no backwards-compatibility shim or legacy migration choreography is required.

| Phase | Slice | State | Repository evidence |
| --- | --- | --- | --- |
| 1 | Existing invariant and lifecycle test closeout | Complete | `PlayerIdentityLifecycleExpansionV3Test`, `PlayerReferenceQueryContractV3Test`, account-deletion release test |
| 2 | Intent-specific Player lifecycle operations | Complete | `CreatePlayerForAccount`, `UpdateOwnedPlayerIdentity`, `MoveOwnedPlayerToKingdom`, claim/release Actions and `PlayerLifecyclePolicy` |
| 3 | Immutable identity/provenance history | Complete | `player_identity_history`, `PlayerIdentityHistory`, `PlayerIdentityHistoryRecorder`, `PlayerIdentityHistoryQuery` |
| 4 | Explicit duplicate reconciliation | Complete with fail-closed owner-boundary rule | `canonical_player_id`, `player_reconciliations`, candidate/integrity queries, `ReconcilePlayers`; live foreign-owner dependencies must be resolved by their owner before reconciliation |
| 5 | First-class My Governors surface | Complete | `GovernorManagementController`, `routes/governors.php`, `resources/js/pages/Accounts/Governor/Governors.vue`, localized lifecycle labels |
| 6 | Voluntary release and account lifecycle | Complete | `ReleasePlayerAccount`, `ReleasePlayersFromAccount`, Data Governance account-deletion integration test |
| 7 | Authority-context lifecycle hardening | Complete | reconciled aliases excluded from owned/current reads; current-context middleware revalidates ownership; stale-context contract retained |
| 8 | Audit and integrity diagnostics | Complete | lifecycle audit writes, activation audit assertion, `PlayersIntegrityQuery` |
| 9 | Product/architecture closeout | Complete | Player context architecture doc, ADR 0017, capability catalogue and this source-of-truth product contract |
| 10 | CI / regression closeout | Complete via release gate | PR #161 final-head PHP/Architecture/CI/CodeQL/Dependency/Intelligence/Visual checks must be green before merge; GitHub merge metadata is the authoritative release evidence |

## Reconciliation architecture adjustment

The initial implementation plan considered a cross-context workflow that would repoint every foreign-owner Player reference during a merge. Repository ownership rules demonstrate that such a generic rewrite would violate bounded-context ownership and create destructive historical churn.

The implemented contract is therefore stricter:

1. Players surfaces reconciliation candidates but never auto-merges.
2. The duplicate must not carry live Governance, Alliance membership, or active/tracked roster dependencies.
3. Any live dependency is resolved through its owning context first.
4. Players then records an explicit canonical alias without rewriting foreign-owner historical facts.
5. Current Player reads exclude aliases; present-identity callers can explicitly resolve an alias to its canonical Player.

This adjustment preserves the original safety goals—no fuzzy merge, no foreign writes, no provenance loss—while retaining bounded-context ownership.

## Definition of done

- [x] Player identity invariants have direct behavioral coverage.
- [x] Accounts can create, view, edit, move and release eligible Governors.
- [x] Current identity remains a small authoritative projection.
- [x] Temporal identity/ownership history is retained.
- [x] Stable game-ID conflicts fail closed.
- [x] Kingdom movement blockers are enforced and directly tested.
- [x] Account deletion releases Player ownership without deleting the Player identity.
- [x] Similar names only surface candidates and never cause automatic reconciliation.
- [x] Reconciliation is explicit, audited and provenance-capable.
- [x] Active foreign-owner dependencies block reconciliation until the owner context resolves them.
- [x] Reconciled aliases disappear from current owned/operational Player reads.
- [x] Active Governor authority remains server-authoritative and stale-context safe.
- [x] Activation audit evidence is directly asserted.
- [x] Product/architecture documentation describes the implemented lifecycle and owner boundaries.
- [x] Release is fail-closed on the final PR head: required GitHub checks must be green before merge, and the PR's merge metadata records the release completion externally to this source file.
