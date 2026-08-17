# ReadModels

Status: Current — Architecture V3

`app/ReadModels` is the read-only composition layer for views that need data from more than one bounded-context owner.

## Rules

A ReadModel may:

- query multiple context-owned data sources;
- combine stable facts into a projection;
- shape data for dashboards, calendars, history and management views;
- use scalar identifiers to correlate owner data.

A ReadModel must not:

- call `save`, `delete`, `create`, `update` or equivalent write operations;
- open a business write transaction;
- acquire domain write locks;
- publish business commands as a substitute for an Action;
- become persistence owner of a projection's source aggregates.

If a user intent mutates more than one owner, use a Workflow. If one capability owns the write, call that capability's Action.
## Current composed surfaces

Examples of V3 cross-context composition include:

- `ReadModels/EventAnalysis` for Event history, evidence, trends and Player/Alliance/Kingdom analytical views;
- `ReadModels/ContributionHistory` for contribution-history presentation over Intelligence-owned contribution facts;
- `ReadModels/Roster` for roster/history/intelligence presentation over Alliance, GameWorld and Intelligence facts;
- `ReadModels/KingdomIntelligence` and `ReadModels/SharedKingdomIntelligence` for composed intelligence screens;
- Platform administration and launch-readiness projections that read across tenant/context ownership.

Moving a query into a ReadModel does not transfer write ownership. Source facts remain owned by their business contexts.
