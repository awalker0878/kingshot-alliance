# Kingdoms identity lifecycle and reconciliation

Status: Implemented

## Goal

Complete `GameWorld/Kingdoms` as the canonical neutral identity boundary for Kingdoms and game-side Alliances.

## Delivered capability

- explicit active-versus-historical Kingdom and Alliance reference contracts;
- lifecycle actions for Kingdom and game-side Alliance archive/restore;
- atomic archive cascade from Kingdom to active child Alliance identities;
- temporal Alliance identity history for name, tag and stable game ID;
- source/provenance metadata on identity changes;
- explicit duplicate-identity reconciliation with permanent alias preservation;
- late stable-ID transfer during reconciliation;
- conservative similarity-only reconciliation candidates;
- integrity diagnostics for lifecycle/history/canonicalization faults;
- dedicated Architecture V3 behavior tests.

## Product rules

1. Name/tag similarity is not proof of identity.
2. A missing stable game ID never causes an implicit name/tag merge.
3. Archived identity remains visible in history but cannot be used for new operational work.
4. A stable game Alliance ID cannot be replaced in place.
5. An old alias remains resolvable after reconciliation.
6. New operational work must use the active canonical identity.
7. Evidence stays in its owning context; Kingdoms stores only source references and provenance metadata needed to explain the identity fact.

## Non-goals

Kingdoms does not own Alliance membership, Governance roles, Intelligence evidence/observations, diplomacy policy, transfer policy or KingdomMaps spatial data.

## Deployment model

Fresh deployment only. The schema is defined in final form; there are no backwards-compatibility shims or legacy migration paths.
