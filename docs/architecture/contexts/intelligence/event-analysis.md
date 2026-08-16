# Event analysis

Status: Current  
Context: Intelligence  
Implementation: `app/Contexts/Intelligence/EventAnalysis` and Event-history ReadModels

EventAnalysis owns analytical history, trends and evidence derived from operational Event facts.

## Ownership split

- Operations: schedule, occurrences, participation, planning and captured operational results.
- Intelligence: analytical/history state derived from those facts.
- ReadModels: read-only composition for screens/history views that need multiple owners.

Current membership/placement can govern access to organization-scoped history but does not rewrite the historical Player/Alliance/Kingdom target of the underlying Event fact.

Analytical metrics should preserve their event-specific meaning rather than pretending all Event types share one universal contribution score.