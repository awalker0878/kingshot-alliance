# ADR-0055: Transfer management catalogue and assignment pages

Status: Accepted

## Previous design and problem

Transfer management loaded complete cohort, group and capacity history collections, while windows, plans and condition history used fixed caps without continuation. Official groups expanded every member Kingdom. Participant assignment choices filtered the loaded cohort catalogue in the browser, excluding valid off-page choices once that catalogue became bounded.

## Decision and ownership

TransferManagement remains a read-only composition. `TransferManagementCatalogueQuery` authorizes each read and returns independent 25-record pages for windows, plans, official groups, conditions, capacities and cohorts. Each cursor binds the current actor, Alliance, collection and selected plan; stable IDs and an initial upper frontier permit deleted-boundary continuation without admitting later insertions. Complete counts are separate live SQL observations, not counts of the current page or a cross-request snapshot.

A selected plan is resolved in the current Alliance independently of its catalogue page. Closed and cancelled plans remain readable; only a currently mutable plan in the Alliance's current Kingdom exposes edit controls. Current write Actions still reauthorize and lock their own facts. Group rows expose a complete membership count; their immutable revision membership is available through a separately authorized 25-Kingdom page.

The existing choice endpoint and `TransferChoicePicker` gain participant-scoped cohort choices. KingdomTransfers owns `TransferCohortAssignmentQuery`, which the authoritative assignment Action also uses while holding its existing locks. Direction, destination, activation and withdrawal rules therefore remain with the Context. Cursors additionally bind participant direction/destination/withdrawal facts. ReadModel cursors and returned options never authorize a later write.

Independent catalogue navigation retains unsaved form and row drafts, replaces only the visible page, and offers retry after failed navigation. Changes of principal, Alliance or selected plan clear old drafts and abort obsolete requests. Group detail and choice components retain only one loaded page.

## Alternatives and consequences

Raising collection caps would preserve truncation. Chunking all collections would bound individual queries but retain unbounded page rendering and synchronous work. A second options endpoint would duplicate the already-authoritative choice protocol. No persisted projection, compatibility alias or new mutation authority is introduced.

Remove the four superseded eager catalogue query classes and `TransferPlanQuery::forAlliance` after migrating their last callers. Canonical fresh-schema indexes support the scope/ID scans. The bound concerns hydration and rendering; complete indexed counts still require database work proportional to matching history.

## Verification

HARD-095 tracks actual execution. Tests traverse every catalogue beyond 50 records, test deleted boundaries and later insertions, reject changed scopes/current membership, retain readable closed plans, page large official-group memberships and verify shared assignment compatibility. Desktop/mobile journeys cover independent pages, retained drafts, failed navigation/retry, off-page cohort assignment and archived plans. Acceptance still requires hosted PostgreSQL/browser and final containing gates.
