# Intelligence — EventAnalysis

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/EventAnalysis`

EventAnalysis owns analytical history, trends and evidence derived from operational Event facts.

## Ownership split

- Operations owns schedules, occurrences, participation, planning and captured operational results.
- Intelligence/EventAnalysis owns analytical/history state derived from those facts.
- ReadModels own read-only composition for screens/history views that need several owners.

Current membership/placement may govern access to organization-scoped history but does not rewrite historical Player/Alliance/Kingdom attribution.

Analytical metrics preserve their Event-specific meaning rather than pretending all Event types share one universal contribution score.