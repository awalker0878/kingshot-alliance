# ADR 0017 — Preserve Player identity history and reconcile by canonical alias

Status: Accepted

Date: 2026-09-08

## Context

`GameWorld/Players` began with a deliberately small durable Player record containing current owner, Kingdom, stable game Player ID and current name. That is appropriate for active-context and authorization reads, but overwriting those fields alone loses the provenance needed to explain renames, Kingdom changes, account claims/releases and identity corrections.

The product also needs a safe way to resolve duplicate Player records. A duplicate may be discovered from a stable game ID, trusted operational evidence or a human review. Name similarity is not strong enough to establish identity. A generic merge that rewrites every foreign key in Alliance, Governance, Operations, Intelligence, Transfers, Gift Codes and other contexts would also violate owner boundaries and destroy useful historical attribution.

## Decision

1. Keep `players` as the current authoritative Player projection.
2. Add append-only temporal `player_identity_history` rows recording identity/ownership snapshots plus source/reference/observation/confidence/reason metadata.
3. Permit exactly one current history row for an unreconciled Player.
4. Add `players.canonical_player_id` and `player_reconciliations` to represent an explicitly reconciled duplicate as an alias of a canonical Player.
5. Never automatically reconcile from name similarity.
6. Reconciliation requires explicit canonical and duplicate IDs, a reason, and transaction-time validation.
7. Reject reconciliation across current Kingdoms, across conflicting account owners, or when both Players have conflicting stable game Player IDs.
8. Reject reconciliation while the duplicate has live Governance, Alliance membership, or active/tracked roster dependencies.
9. Do not rewrite foreign-owner tables from `GameWorld/Players`. The owning context must resolve any live dependency first. Historical references may continue to identify the alias that existed at the time.
10. Exclude reconciled aliases from current ownership, stable-ID and operational Player reads. Provide explicit bounded canonical resolution for callers that need present identity from an historical alias.
11. Preserve active Player authorization semantics: account ownership and current authority are always revalidated server-side.

## Consequences

### Positive

- Current Player reads remain simple and fast.
- Renames, claims, releases and Kingdom changes no longer erase identity history.
- Reconciliation is explicit, explainable and auditable.
- Similar names cannot silently collapse distinct Governors.
- Stable game IDs can be transferred to the canonical record when evidence shows a dormant duplicate is the same Governor.
- Bounded contexts continue to own their tables and historical facts.
- Historical records can retain the exact Player alias that existed when the fact was recorded.

### Trade-offs

- Reconciliation is intentionally conservative. An operator cannot reconcile a duplicate with unresolved live owner-context dependencies in a single generic merge operation.
- Callers that start from an historical alias and need the present identity must opt into canonical resolution.
- Current and historical identity are separate concepts and must not be conflated in queries.

## Rejected alternatives

### Overwrite-only Player history

Rejected because it prevents reliable identity provenance and makes account release, rename and Kingdom-move investigations ambiguous.

### Automatic fuzzy/name merge

Rejected because Kingshot names are mutable and non-unique. Similarity may produce a candidate, never an identity decision.

### Global foreign-key rewrite during reconciliation

Rejected because Players would mutate data owned by Alliance, Governance, Operations, Intelligence, Transfers, Gift Codes and other contexts. It would also rewrite historical attribution rather than preserve it.

### Delete duplicate Player rows

Rejected because historical evidence may legitimately reference the duplicate identity that existed before reconciliation. Canonical aliases preserve that history while removing the alias from current operational selection.

## Verification

The decision is enforced by:

- the Player lifecycle migration and partial unique current-history index;
- `PlayerIdentityHistoryRecorder`;
- `PlayerReferenceQuery` current/alias/canonical semantics;
- `PlayerReconciliationCandidateQuery`;
- `ReconcilePlayers` transaction-time guards;
- `PlayerLifecyclePolicy` live-dependency blockers;
- `PlayersIntegrityQuery` diagnostics;
- Player lifecycle/reconciliation/query contract tests;
- account-deletion release coverage;
- the first-class My Governors management surface.
