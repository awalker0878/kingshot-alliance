# Intelligence context

Status: Current  
Implementation: `app/Contexts/Intelligence`

## Purpose

Intelligence owns observational, historical and analytical state derived from game/alliance activity. It does not duplicate neutral GameWorld identity as writable identity state.

## Capabilities

- `Observations` — captured Player/Alliance/game observations;
- `Ingestion` — intake, normalization and reconciliation;
- `Roster` — roster intelligence;
- `Contributions` — contribution ledger and reporting facts;
- `EventAnalysis` — analytical Event history/metrics/trends;
- `Diplomacy` — relationship/diplomacy analysis;
- `Sharing` — shared intelligence/grants/history;
- `Access` — Intelligence-owned authorization semantics.

## Boundary

Operations owns live Event execution. Intelligence may consume operational facts and produce durable analytical/history state, but must not mutate Operations aggregates or recreate Player identity ownership.