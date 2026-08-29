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

## Management collections and pagination

Unbounded management/history collections return `App\Shared\Infrastructure\Pagination\PageSlice`: `items`, `nextCursor`, `hasMore`, `pageSize`, and `isFirstPage`. Cursors are opaque and scope-bound through `ScopedCursorCodec`; the scope includes the tenant/resource identity, view, and normalized filters. Reusing a cursor across Alliances or filter states must fail validation.

ReadModel HTTP adapters may validate filters, authorize a view, invoke the projection, and render it. Context HTTP adapters do not import ReadModels. See [ADR-0001](../architecture/adr/0001-composed-management-reads-and-scoped-cursors.md).

## Current composed surfaces

Examples of V3 cross-context composition include:

- `ReadModels/EventAnalysis` for Event history, evidence, trends and Player/Alliance/Kingdom analytical views;
- `ReadModels/EventManagement` for occurrence readiness/closeout and factual Rally roster gaps over Operations owners;
- `ReadModels/CommandOverview` for recomputable R4/R5 owner attention plus deterministic Officer Brief projections;
- `ReadModels/NotificationDelivery` for bounded active-membership recipient pages used by scheduled Officer Brief and Intelligence delivery orchestration;
- `ReadModels/ContributionHistory` for contribution-history presentation over Intelligence-owned contribution facts;
- `ReadModels/RecruitmentManagement` for the filterable, cursor-paginated recruitment pipeline and authorized Transfer Campaign workspace over Recruitment, Membership, Transfer, Evidence and Communications facts;
- `ReadModels/Roster` for roster/history/intelligence presentation and the factual Member Capability Profile over Alliance, GameWorld, Operations and Intelligence facts;
- `ReadModels/KingdomIntelligence` and `ReadModels/SharedKingdomIntelligence` for composed intelligence screens, including the bounded owner-linked Kingdom Intelligence Timeline;
- `ReadModels/AllianceAssistant` for closed, source-backed questions over exact authorized owner projections and navigation-only write handoffs;
- Platform administration and launch-readiness projections that read across tenant/context ownership.

Officer Brief fingerprints and Intelligence signals are semantic values derived from composed owner facts. Their queue actions authorize and build those read projections, then pass immutable scalar payloads to Communications-owned publishers. Communications may persist preference/attempt/receipt state, but recipient sweeps, briefs, signals and Alliance attention are not persisted as parallel domain truth.

Moving a query into a ReadModel does not transfer write ownership. Source facts remain owned by their business contexts.
