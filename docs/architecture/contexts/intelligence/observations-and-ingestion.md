# Observations and ingestion

Status: Current  
Context: Intelligence  
Implementation: `app/Contexts/Intelligence/Observations` and `Ingestion`

## Purpose

Capture game-world observations and reconcile incoming data into Intelligence-owned analytical state without turning observations into a second GameWorld identity system.

## Responsibilities

- observation persistence/history;
- ingestion/intake processing;
- normalization and reconciliation behavior;
- provenance/timing needed to distinguish observed facts from current neutral identity state.

## Boundary

GameWorld remains owner of canonical Player/Kingdom identity/reference state. Intelligence may key observations by those identifiers but does not mutate or replace them.

Ingestion paths should be idempotent where the same source data can be replayed, and should retain enough provenance to explain analytical results without storing unnecessary sensitive payloads.