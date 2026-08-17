# Intelligence context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence`

Intelligence owns durable observational, ingestion, contribution, roster-intelligence, diplomacy, and sharing state derived from game and Alliance activity. Cross-context analytical presentation is composed in `app/ReadModels`.

## Capabilities

```text
Intelligence/
├── Access/
├── Observations/
├── Ingestion/
├── Roster/
├── Contributions/
├── Diplomacy/
└── Sharing/
```

- **Access** owns Intelligence permission vocabulary and authorization interpretation.
- **Observations** owns durable observed facts and provenance.
- **Ingestion** owns import/reconciliation of external observations.
- **Roster** owns roster intelligence/history projections, not authoritative Alliance membership.
- **Contributions** owns contribution facts, history and reporting.
- **Diplomacy** owns diplomacy intelligence.
- **Sharing** owns Intelligence grants/distribution.

## Boundary

GameWorld owns neutral Player/Kingdom identity. Alliance owns current authoritative Alliance membership. Operations owns live Event execution/results. Intelligence consumes stable identifiers/facts without mutating those owners or duplicating their writable identity/state. Event-analysis views that combine Operations, Alliance, GameWorld, and Intelligence facts live under `app/ReadModels/EventAnalysis`.

Root `Intelligence/Http` or other technical buckets are not V3 modules; HTTP and other implementation layers belong under the owning capability.