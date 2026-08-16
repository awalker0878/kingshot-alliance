# Intelligence context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence`

Intelligence owns observational and analytical state derived from game and Alliance activity.

## Capabilities

```text
Intelligence/
├── Access/
├── Observations/
├── Ingestion/
├── Roster/
├── Contributions/
├── EventAnalysis/
├── Diplomacy/
└── Sharing/
```

- **Access** owns Intelligence permission vocabulary and authorization interpretation.
- **Observations** owns durable observed facts and provenance.
- **Ingestion** owns import/reconciliation of external observations.
- **Roster** owns roster intelligence/history projections, not authoritative Alliance membership.
- **Contributions** owns contribution facts, history and reporting.
- **EventAnalysis** owns Event history, analytics and projections.
- **Diplomacy** owns diplomacy intelligence.
- **Sharing** owns Intelligence grants/distribution.

## Boundary

GameWorld owns neutral Player/Kingdom identity. Alliance owns current authoritative Alliance membership. Operations owns live Event execution/results. Intelligence consumes stable identifiers/facts without mutating those owners or duplicating their writable identity/state.

Root `Intelligence/Http` or other technical buckets are not V3 modules; HTTP and other implementation layers belong under the owning capability.