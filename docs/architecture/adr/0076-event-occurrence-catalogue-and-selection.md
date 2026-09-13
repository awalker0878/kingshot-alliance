# ADR-0076 — Event occurrence catalogue and selected management

Status: Accepted

## Context

Event management eagerly loaded every retained occurrence before authorization and repeated operational projections for each. Event Command searched the loaded history even though it only presents one selected occurrence. Its twelve-occurrence closeout search bound limited the later loop, not the initial database materialization. Rally management independently fetched all occurrences again. Update/cancel adapters, bulk cancellation preview and King Perk selection also inherited the eager history read.

## Decision

Events retains authorization and occurrence facts. EventCalendarQuery.eventForManage authorizes the Event without loading occurrences. EventOccurrenceCatalogueQuery provides a current-authorized 25-row catalogue with one sentinel, exact total and encrypted actor/Event-scoped continuation. The cursor records the initial ULID frontier and the last UTC second/id position. The captured ULID frontier excludes higher-ID insertions from the existing traversal; a fresh traversal includes them. This is a finite current-authorized traversal, not a transactionally frozen snapshot. Pages follow descending start time/id, and the native selector presents its bounded choices in chronological order.

EventManagement retains read-only readiness/closeout composition. Event Command selects one explicit Event-scoped occurrence, otherwise queries the earliest active occurrence, at most twelve recent ended occurrences requiring closeout, the earliest upcoming occurrence, then the most recent occurrence. Each query bounds its materialization; it never loads an Event's full history. A selected occurrence outside the current catalogue page remains a separate choice, so at most 26 choices are exported. The total counts stored occurrences, not just the displayed choices.

Management composes occurrence-dependent operations for this one selected occurrence. EventRallyQuery honors that selected relation instead of fetching unrelated history. King Perk selection uses a constrained one-row query. Mutation owners and their current revalidation remain unchanged.

Paging preserves the selected occurrence, unrelated form drafts and scroll, with explicit loading failure and retry. Changing the selected occurrence navigates to its own management view and initializes its forms. The existing small-history Event Command presentation is preserved. All navigation still passes current owner authorization; cursors confer no authority.

## Limits and verification

This decision bounds occurrence discovery, selection and multiplicative management composition. It does not claim that each selected occurrence's phase, poll, participant, roster, Rally, result or reminder collection is bounded; those independent collections remain under HARD-134. Debrief result projection remains HARD-135. Existing retained rows are not deleted or hidden by a fixed overall cutoff.

EventOccurrenceHistoryTest covers complete traversal across retained history, insertion frontiers, second-precision ordering, empty history, cross-scope/malformed cursors, revoked authority before hydration, a thousand-row admission/selection budget, independent old selection and actual selected management payloads. EventCommand's existing owner/query-budget cases and desktop/mobile visual fingerprints remain active. Browser coverage additionally checks 101-occurrence navigation, retained draft/selection, injected network failure/retry and older cancelled occurrence management. Full containing hosted verification remains a release requirement.
