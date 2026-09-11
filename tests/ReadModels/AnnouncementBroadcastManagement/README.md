# Announcement management tests

The [Content architecture](../../../docs/architecture/contexts/alliance/content.md) and [ADR-0052](../../../docs/architecture/adr/0052-bounded-current-manager-content-workspaces.md) define current-manager catalogue/history pages. [ADR-0050](../../../docs/architecture/adr/0050-scoped-announcement-outcome-projections.md) remains the separate Communications outcome contract. Tests use the agreed owner-first structure.

## Complementary contracts

`Feature/BroadcastDeliveryCompletenessTest.php` verifies exact retained read/status totals above the former sample limits, independent bounded retry candidates, invalid metadata, current manager authorization and the fresh lookup index. Its original assertions remain, now reached through the authorized per-item history instead of the removed unbounded forAlliance method.

`Feature/ContentManagementPaginationTest.php` verifies catalogue, category, media and per-item history traversal; complete totals; current tenant/permission/Kingdom scope; filter and subject cursor isolation; new insert/deleted boundary behavior; off-page selections; fixed query counts; actual pre-materialization bounds; old history beyond another item's hundred newer runs; keyset indexes; and forged retry metadata rejection. These tests use actual PostgreSQL, Laravel HTTP entry points, the encryption contract and real owner Actions, not mocked pagination or permission decisions.

`Architecture/BroadcastDeliveryOwnerBoundaryTest.php` protects both existing outcome composition and the new owner-query boundary. The controller may not reintroduce direct catalogue/history materialization; the read model cannot inspect Communications tables or become a delivery writer.

`Frontend/ContentDrafts.test.ts` runs in Node without a browser and verifies edit reconciliation, clean off-page eviction, edits after submission, locale labels and nested-form safety. `Frontend/BroadcastDeliverySummary.test.ts` still protects the distinction between selected retry IDs and complete candidate totals across all seventeen locales; it now inspects the dedicated history component and shared presentation type.

`Browser/ContentManagementPagination.spec.ts` exercises the real manager in desktop and mobile projects. It retains an editor draft and an off-page category through paging/search, recovers an explicit transient history error and visits every retained run/revision in the fixture. The existing `BroadcastProgress.spec.ts` still verifies incomplete versus complete recipient preparation and final provider outcomes. Both histories remain separate from background delivery execution.

## Commands

From the prepared repository root:

```sh
vendor/bin/phpunit --fail-on-empty-test-suite tests/ReadModels/AnnouncementBroadcastManagement
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Content
npm run test:source-contracts
npm run test:visual -- tests/ReadModels/AnnouncementBroadcastManagement/Browser
```

The browser fixture seeds only its own Alliance and Content subjects and does not reset other test owners. Keep the current one-worker browser policy. Owner selections do not replace complete regression or the Communications/NotificationDelivery source, attempt, member and endpoint-generation checks. Current execution evidence and pending full gates belong in the canonical HARD-106 delivery ledger, not an assumed passing result in this guide.
