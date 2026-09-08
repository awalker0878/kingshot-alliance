# ADR 0011 — Preserve aliases while canonicalizing Kingdom Alliance identity

Status: Accepted

## Context

Game-side Alliance names and tags can change and are not safe identity keys. A stable game Alliance ID may also arrive only after Intelligence has already created one or more neutral references. Existing observations, diplomacy records and other historical rows may legitimately reference whichever neutral ID existed at capture time.

Silently merging by name/tag risks false identity joins. Destructively rewriting every historical foreign key would erase provenance and make reconciliation difficult to audit.

## Decision

Kingdoms uses explicit canonical identity links.

- Direct neutral Alliance identities have `canonical_kingdom_alliance_id = null`.
- An explicitly reconciled duplicate is archived and points to the direct canonical identity.
- Historical `find/require` operations preserve the alias.
- Operational consumers resolve an alias through `requireCanonical` / `requireActiveCanonical` before creating or mutating current state.
- Reconciliation decisions are appended to `kingdom_alliance_reconciliations` with source, reason and confidence metadata.
- Mutable name/tag/stable-ID facts are versioned in `kingdom_alliance_identity_history`.
- Similar names/tags may create candidate diagnostics only. They never cause an automatic merge.

## Stable-ID transfer

If the duplicate owns the only known stable game Alliance ID, reconciliation first closes the duplicate history and clears its current stable ID, then assigns that stable ID to the canonical row. This preserves the database uniqueness invariant while history records where the identifier was previously observed.

If both identities hold different non-null stable IDs, reconciliation fails closed.

## Lifecycle

Archived Kingdoms and archived/reconciled Alliance identities are historical only. Operational resolution and mutation require an active Kingdom and a direct active canonical Alliance identity. Kingdom archive cascades to active child Alliance identities; restore is deliberately non-cascading.

## Boundaries

Kingdoms owns canonical neutral identity. It does not rewrite Intelligence, Operations or Alliance persistence directly. Owner actions/workflows consuming Kingdoms must canonicalize new operational writes while historical records remain valid.

## Deployment consequence

This is a fresh deployment with an empty database. The existing Kingdom Alliance create migration is changed to the final schema directly. No compatibility migration, legacy backfill, dual-write shim or shadow identity system is introduced.
