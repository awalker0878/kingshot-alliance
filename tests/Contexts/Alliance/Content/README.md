# Alliance Content verification

Follow the [owner-first layout](../../../README.md). [ADR-0049](../../../../docs/architecture/adr/0049-bounded-announcement-occurrences.md) owns bounded broadcast execution; [Content architecture](../../../../docs/architecture/contexts/alliance/content.md) owns publication, Rules, reactions and source authority.

`Integration/Concurrency/AnnouncementBroadcastTraversalTest.php` uses real durable PostgreSQL without an outer rollback transaction. Its 22 cases cover budgets, restart, multiple pages, independent connections, revocation/deletion, source revision/cancellation, recurrence generations/exhaustion, suppression, rollback and external-source denial. Connection fixtures require distinct connection names as well as PDOs; copying the original name can route model saves back to the first connection.

Existing Feature announcement and notification-source scenarios retain actual actions, recipients, persistence and assertions. The fixture explicitly opts into notification and calls the canonical coordinator instead of the removed eager action signature. Pending preparation is not full delivery, and a selected membership ID is not an authorization grant.

```sh
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Content/Integration/Concurrency/AnnouncementBroadcastTraversalTest.php
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Content tests/Contexts/Communications/Delivery tests/Workflows/NotificationDelivery
npm run test:visual -- tests/ReadModels/AnnouncementBroadcastManagement/Browser
```

The owner-local browser fixture uses actual Content actions and checks Pending-to-complete state in both projects. Existing fingerprints and PNGs remain unchanged. Full PHP/frontend/architecture/schema/security/image/recovery/browser gates remain required; the canonical ledger records results against immutable revisions, not merely authored tests.
