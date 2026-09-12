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
- `ReadModels/AnnouncementBroadcastManagement` for current-manager catalogue presentation, independent category/media and per-item revision/run pages, and exact retained outcomes composed from Communications; [ADR-0052](../architecture/adr/0052-bounded-current-manager-content-workspaces.md) defines the owner boundary and cursor semantics;
- `ReadModels/RecruitmentManagement` for the filterable, cursor-paginated recruitment pipeline, authorized candidate-detail composition with independent bounded history pages, and authorized Transfer Campaign workspace over Recruitment, Membership, Transfer, Evidence and Communications facts;
- `ReadModels/TransferManagement` for authorized Transfer overview and management composition over Alliance, Player and GameWorld owners; the context HTTP controller remains a thin adapter for plan mutations.
- `ReadModels/Roster` for roster/history/intelligence presentation and the factual Member Capability Profile over Alliance, GameWorld, Operations and Intelligence facts;
- `ReadModels/KingdomIntelligence` and `ReadModels/SharedKingdomIntelligence` for composed intelligence screens, including the bounded owner-linked Kingdom Intelligence Timeline;
- `ReadModels/AllianceAssistant` for closed, source-backed questions over exact authorized owner projections and navigation-only write handoffs;
- Platform administration and launch-readiness projections that read across tenant/context ownership.

Officer Brief fingerprints and Intelligence signals are semantic values derived from composed owner facts. `Workflows/NotificationDelivery` owns their queue Actions, publishers, CLI adapters and execution results. It consumes the authorized ReadModel projections and passes immutable delivery intent to Communications. Communications persists preference/attempt/receipt state; ReadModels own no queue Actions or writer dependencies, and briefs/signals/Alliance attention are not persisted as parallel domain truth.

Moving a query into a ReadModel does not transfer write ownership. Source facts remain owned by their business contexts.
