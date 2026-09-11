# ADR-0052: Bounded current-manager Content workspaces

Status: Accepted

## Problem and ownership

The Content manager previously loaded the entire Alliance catalogue, category and media collections, all revisions and all schedules. The announcement read model also sampled the latest hundred Alliance runs and then five per item, making older retained history unreachable. This mixed page presentation with a lossy history boundary. Paging only after loading the collections would not bound database hydration or response work.

Content owns publication, revisions, media, schedules and occurrence preparation. Communications owns retained delivery/read outcomes. [ADR-0050](0050-scoped-announcement-outcome-projections.md) remains authoritative for the complete per-run outcome projection; [ADR-0049](0049-bounded-announcement-occurrences.md) remains authoritative for bounded recipient preparation. Neither is replaced or duplicated by management pagination.

## Decision

`ContentManagementQuery` is the authoritative current-manager read boundary for catalogue and related history. Every entry point requires the acting Governor's current `ContentManage` authority and an active Kingdom before returning Content facts. Item histories also resolve the actual Alliance-local item and exclude the reserved Alliance Rules identity. HTTP routes keep current account/Governor/Alliance middleware; they do not accept an actor or tenant from query parameters.

The owner returns the existing `PageSlice` contract with complete current matching counts. Catalogue pages contain twenty items, categories and media twenty-five, revisions ten, and per-item broadcast history five. SQL limits apply before Eloquent hydration, with one look-ahead row. Only schedules and aggregate revision/run counts for the displayed catalogue page are loaded. The existing unique schedule-per-item constraint bounds that join independently. Canonical fresh-schema indexes match `(alliance_id, id)` and `(alliance_id, content_item_id, id)` traversal.

Cursors use the existing encrypted `ScopedCursorCodec`. Scope binds Alliance, current Governor, collection purpose, subject and normalized filters. A cursor cannot be reused across actors, tenants, collections, subjects or changed filters. Descending immutable ULID order avoids moving a row merely because its title, status or update timestamp changes. The first page captures an upper ID frontier; continuation carries the last ID and frontier, so deleting the boundary row does not lose later records and newer IDs do not displace an ongoing traversal. Returning to the first page observes new inserts.

This is not an MVCC snapshot across HTTP requests. Authorization, filters and totals are re-evaluated on each request; records can be edited, archived or deleted between pages. Complete totals describe current matching retained records, while continuation respects its captured frontier. Global published/scheduled/awaiting-occurrence counts remain separate from the visible page and from a title/status filter. Search is bounded in input length and treats percent/underscore characters literally. The database still performs current scoped aggregates; no speculative persisted counter or immutable all-time total is introduced.

`AnnouncementBroadcastManagementQuery::history` composes only that authorized five-run page with Communications' existing exact outcome contract. It does not inspect delivery tables or reconstruct preparation counters. Concrete retry IDs remain limited independently from the complete candidate count. The retry Action validates Alliance, actual Content and run metadata consistently with the read contract before delegating stateful retry to Communications. A forged concrete ID is not authorization.

The unbounded `ContentQuery::managerList` and read model's `forAlliance` implementation are removed after migrating callers and tests. There is no alias, compatibility wrapper, parallel counter or historical rollout mode.

## Frontend and operational consequences

Catalogue, category and media navigation are independent. Per-item revision/run histories load on demand, replace one visible page at a time, and expose first/next controls with explicit errors. An aborted or superseded request cannot overwrite the newer page. Selected category/media values are resolved in the current tenant separately from their search page, so an off-page selection stays understandable rather than being silently cleared.

Unsaved content, category and recurrence edits survive same-owner paging. Clean off-page draft rows are evicted instead of retaining every visited catalogue page. A save acknowledges only the submitted state and must not discard edits typed after that request started. Changing the Alliance clears owner-specific drafts and remounts scoped choice controls. The UI does not derive permission or delivery authority from these cached edits; all mutations still reauthorize at their owner Action.

Member/public Content ordering and publication rules are unchanged. The manager's catalogue uses the documented newest-ID order and an explicit status filter rather than an unbounded mixed-priority list. The review queue explicitly describes its current-page scope. Existing retained delivery outcomes, read counts, retry totals, preparation states and provenance remain visible through their appropriate pages.

This remains a modular-monolith read composition. Existing owner Queries, `PageSlice`, cursor encryption, Actions and route middleware suffice; a generic pagination framework, search service, background projection or new bounded context would add no necessary authority or resilience here. The application is undeployed, so the canonical create migration is corrected directly without backfill or upgrade shims.

## Verification and acceptance

Runtime regression first reproduced unbounded 47-row content and 31-row option payloads, missing history continuation, ineffective cursor validation and forged retry metadata acceptance. The corrected PostgreSQL cases verify complete traversal, old history beyond another item's 125 newer runs, selected values, current authority, tenant/filter/subject isolation, deletion/insertion behavior, indexed fresh schema, constant query count and actual model hydration limits. Existing 6,025-route outcome and preparation-counter assertions remain through the new history contract.

Node cases protect draft reconciliation, in-flight save edits, clean-page eviction, locale coverage and embedded option-search behavior. Real browser journeys exercise off-page edits/selections, recoverable history errors and all retained fixture histories in both viewports. Architecture checks prohibit reintroducing unbounded catalogue queries in the controller or direct delivery-table reads in the composition.

The canonical HARD-106 ledger records executed results and the containing gate. Accepting this decision does not mark the implementation or repository program Complete without those results.
