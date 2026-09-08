# Kingdoms final operational-read audit

Status: Implemented; repository verification pending

## Purpose

This final post-merge audit closes remaining current-facing consumers that still resolved Kingdom identity with historical `find()` / `require()` semantics after the Kingdom lifecycle and downstream-enforcement work.

## Operational rule

Current operational surfaces must resolve the operating Kingdom through `findActive()` / `requireActive()` or an owner authorization boundary that enforces the same rule. An archived Kingdom must not remain usable merely because its historical identity row is still resolvable.

This audit applies that rule to:

- Alliance dashboard rendering;
- bot command feeds;
- Kingdom role management;
- Kingdom Intelligence tracking and ingestion management;
- diplomacy contact management, including active canonical tracked Alliance identity;
- Transfer read authorization and every projection that composes it.

## Historical exceptions

Historical `find()` / `require()` remains deliberate for:

- Event history and stored Event target resolution;
- Kingdom Governance history;
- Player snapshot history;
- Kingdom Alliance observation history;
- historical/shared Intelligence identity display;
- Transfer Evidence identity comparisons after the active target has already been authorized.

These are not operational authorization paths and must remain capable of describing archived historical state.

## Fresh deployment

No compatibility layer, migration shim, backfill, or dual-read path is introduced. This is a contract correction in the current deployment model.
