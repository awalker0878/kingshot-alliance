# Kingdom Map workspace acceptance matrix

This matrix maps the visible planning package names to the existing delivery ledger. It defines required evidence, not completion status. The exact shared-conversation body remains unverified; decisions unique to that URL must be reconciled before declaring full source compliance. [Implementation](kingdom-map-workspace-implementation.md) defines the current design and the [ledger](kingdom-map-workspace-delivery-ledger.md) records actual results.

| Plan package | Ledger | Owner and implementation area | Required evidence |
| --- | --- | --- | --- |
| KM00 baseline | KMAP-001 | Repository integration and canonical docs | Refreshed main SHA, preserved concurrent PR ownership, source reconciliation and exact candidate receipt. |
| KM01 contracts | KMAP-002 | KingdomMaps schema/geometry and TerritoryPlanning contract/snapshot/import services | One current schema per boundary, all callers migrated, unsupported inputs rejected, empty PostgreSQL installation. |
| KM02 data materialization | KMAP-003 | KingdomMaps releases and artifact loader | Actual fixed structures, facilities, terrain/resource geometry; checksums, provenance and honest unavailable states. |
| KM03 artwork | KMAP-004 | Versioned registry, preparation/check scripts, public artwork pack | Every required family/variant has icon/sprite/detail, safe inputs, hashes, anchors, source rights and inspected output. |
| KM04 renderer | KMAP-005 | Territory engine scene/camera/index/renderer and TerritoryCanvas | Nonzero origin, axis, rotation, edges, hit testing, gestures/cancellation, culling/HiDPI; shared PHP/TypeScript fixtures. |
| KM05 Explorer | KMAP-006 | TerritoryPlanningPageController and Explorer/workspace components | All navigation/search/inspector/layers/minimap/views/fit/fullscreen controls, responsive and recoverable states. |
| KM06 editing | KMAP-007 | Editor, command/selection composables | Exact coordinates, preview, transforms, groups, locks, alignment, undo/redo, bounded semantic alternatives. |
| KM07 persistence | KMAP-007 | Save/Publish/Import/Restore/Clone Actions and persistence client | Normalized saves, saved-state publication, stale/revoked/scope-switch handling, concurrency and recovery journeys. |
| KM08 hives/templates | KMAP-008 | HiveLayoutGenerator, template and assignment workflows | Map-aware reproducible previews, exact requested count or diagnostics, roster/external identities, reservations and alternatives. |
| KM09 analysis | KMAP-009 | Coverage/Layout analyzers, suggestions, MarchAnalysisPanel | Explainable coverage/connectivity/resources/efficiency/density/distance; assumptions, input identity and explicit acceptance. |
| KM10 observations | KMAP-010 | TerritoryReconciliationQuery and Reconciliation view | Authorized evidence, freshness/coverage, uncertain matching, movement/unexpected objects, no implicit intent mutation. |
| KM11 collaboration | KMAP-011 | Territory comments/reviews/grants Actions and collaboration view | Object identity, revision notes, optimistic concurrency, per-layer grants enforced on actual writes, approval bound to checksum. |
| KM12 integrations | KMAP-012 | Owner roster/event/audit/Intelligence contracts and NotificationDelivery workflow | Actual invocation, current authority, preferences, bounded fan-out, idempotency, retry/failure diagnostics. |
| KM13 interchange | KMAP-013 | TerritoryPlanImport, import/export UI | Strict bounded schema 2 round trip, dry run, duplicate handling, safe commit, obsolete schema rejection. |
| KM14 visual exports | KMAP-013 | Shared export scene/layout and artwork loader | PNG/SVG viewport/selection/Alliance/map, labels/legends/identity/context, embedded art, large-output/cleanup/failure tests. |
| KM15 sharing | KMAP-013 | Private revision share Actions/query/view | Scope filtered on server, authenticated recipient, exact revision, expiration/revocation/current-authority denial and cache isolation. |
| KM16 accessibility/localization | KMAP-014 | Workspace controls, semantic list and locale chunks | Keyboard/focus/announcements, touch targets, non-color status, reduced motion, long translations and RTL visual inspection. |
| KM17 security/performance | KMAP-014 | Boundary tests, renderer/export limits and measurement fixtures | Cross-tenant/revoked/stale/cache/unsafe-input tests; released-data and labeled synthetic timings/memory against explicit budgets. |
| KM18 release | KMAP-015 | Normal CI, KingdomMaps assurance, browser suites and cleanup | Exact-commit all required gates, reviewed desktop/tablet/mobile images, clean schema/container/recovery, no obsolete paths/placeholders, full scope reconciliation. |

## Runnable verification entry points

Use the lockfile-selected Node 24/PHP 8.5 toolchain. `composer check:ci` and `npm run check` are the repository-wide gates; normal CI adds dependency advisories, PostgreSQL/Redis services and clean installation. `.github/workflows/kingdom-maps-assurance.yml` owns map release validation and browser/server geometry parity. Territory HTTP/Action/ReadModel tests run against PostgreSQL, not an SQLite surrogate. `npm run test:visual` runs the configured Playwright projects. Commands must retain their assertions and required exit codes.

Required real journeys include inspect/picture; create/save/reopen hive; assign Governors; reject illegal placement; resolve concurrent edits; compare alternatives and observed drift; publish immutable intent; artwork-bearing export; private scoped share; and revoke then deny. Loading failures, unavailable data/art, stale tabs and authority loss are first-class cases.

Performance targets must be accompanied by machine/network/viewport/record-count methodology and actual measurements. Initial planning targets are desktop/mobile first useful render 2.5/4 seconds, input p95 50/100 ms, frame p95 16.7/33.3 ms, JS heap 160/96 MiB and decoded artwork 96/48 MiB. These are targets, not measured results. Mobile network measurement uses 10 Mbps and 100 ms latency; synthetic geometry is clearly labeled and cannot certify released data coverage.
