# ADR-0001: Composed management reads and scoped cursor pagination

Status: Accepted

Date: 2026-08-20

## Context

Some management controllers assembled data by querying several owner models directly and returned a fixed number of rows. Recruitment was the clearest example: its controller combined Recruitment, Membership, Player, and Alliance facts and silently stopped after 250 candidates. That made the controller an implicit projection owner, prevented complete navigation, and gave each future list an opportunity to invent a different paging contract.

## Decision

User-facing reads that combine multiple owners live in `app/ReadModels`, including their transport shaping. Context controllers remain write adapters and may not import ReadModels. A dedicated ReadModel HTTP adapter may validate read filters, authorize the view, invoke the projection, and render it.

Unbounded management collections use keyset pagination through the shared `PageSlice` transport shape. Cursor payloads are encrypted and bound to the concrete tenant, view, and normalized filter set through `ScopedCursorCodec`. A cursor from another Alliance or filter state is rejected as validation input rather than interpreted against the current query.

The standard page shape is:

- `items`
- `nextCursor`
- `hasMore`
- `pageSize`
- `isFirstPage`

The first implementations are the Recruitment management projection, Alliance roster view, and Alliance membership administration projection. Shared Kingdom intelligence history now uses the same cursor codec. Other unbounded management and history surfaces migrate vertically as their UX and behavior tests are completed.

## Consequences

- Management controllers no longer hide cross-owner query composition.
- Fixed row caps cannot masquerade as complete lists.
- Filters are part of cursor identity, so pagination cannot leak or mix state across Alliances or searches.
- ReadModels remain read-only and business writes continue through owner Actions.
- Cursor navigation favors deterministic forward paging and a first-page reset. Offset page numbers are not promised.

## Rejected alternatives

- Offset pagination was rejected for mutable operational lists because inserts and removals make later pages unstable and increasingly expensive.
- Framework-generated, unbound cursors were rejected because they do not prove that the token belongs to the current Alliance and filter set.
- Keeping composition in context controllers was rejected because it violates the direction from contexts toward presentation-level ReadModels.
