# Communications Recipient Delivery Delivery Ledger

Status: Closed — current complete capability

Canonical product contract: [Communications — Recipient Delivery & Notification Experience](communications-recipient-delivery-expansion.md).
Acceptance matrix: [Communications Recipient Delivery Acceptance Matrix](communications-recipient-delivery-acceptance.md).

| Phase | Scope | Status | Repository evidence |
| --- | --- | --- | --- |
| 1 | Product contract, ADR, acceptance/ledger and global documentation registration | Complete | Product contract, ADR-0015, acceptance matrix and reconciled global documentation |
| 2 | Current Communications closeout fixes | Complete | `account.security` and channel/status/health localization; endpoint-delete busy state; existing Communications behavior regression suite |
| 3 | `NotificationMessage` / `NotificationDelivery` separation and normalized source intent/receipt | Complete | Fresh schema, `NotificationMessage`, route-only `NotificationDelivery`, `NotificationIntent`, `NotificationQueueReceipt`, idempotency/replay V3 tests |
| 4 | Routing policy engine | Complete | `NotificationRouteResolver`, `EffectiveRoutingPolicy`, `ResolvedDeliveryPlan`/`ResolvedDeliveryRoute`, routing acceptance coverage |
| 5 | Account-default / Governor-override preference hierarchy | Complete | `SetNotificationPreference`, reset-to-account behavior and cross-account/Governor acceptance coverage |
| 6 | Quiet hours, urgency and temporary mute | Complete | `NotificationRoutingPolicy`, generic urgency, quiet/defer/mute resolver behavior and urgent-bypass acceptance coverage |
| 7 | Multiple named endpoints per channel | Complete | Multiple endpoint schema/indexes, concrete route endpoint IDs and multi-endpoint/multi-device acceptance coverage |
| 8 | Endpoint test/pause/reverify/health lifecycle | Complete | Endpoint lifecycle Actions/HTTP routes, audit evidence and healthy/degraded/paused acceptance coverage |
| 9 | Web Push | Complete | Web Push endpoint validation, VAPID channel, service worker/UI, stale subscription handling and security acceptance coverage |
| 10 | Notification Center 2.0 | Complete | `NotificationInboxQuery`, logical-message read/archive state, cursor/filter reads, subordinate route details and bulk message operations |
| 11 | Generic digest/deferred delivery | Complete | `BuildNotificationDigestDispatches`, `ProcessNotificationDigests`, 20-member bound, idempotency/retry tests and scheduled workers |
| 12 | Email | Complete | Accounts-owned `VerifiedNotificationEmailQuery`, native email route, revalidation before send and mail readiness checks |
| 13 | Delivery diagnostics | Complete | Recipient route details plus privacy-safe bounded Platform Administration counts/failure fingerprints and acceptance assertions |
| 14 | Delivery processor hardening | Complete | Row-locked claims, stale-pending recovery, current endpoint/policy/Governor recheck, route cancellation/redefer, bounded attempts and retry timing |
| 15 | Source-context integration normalization | Complete | Account Security, Alliance announcements, Events, Gift Codes, Intelligence, King Perks and Officer Briefs publish `NotificationIntent` and consume scalar receipts |
| 16 | Security and abuse controls | Complete | Encrypted/hidden credentials, provider destination validation, safe relative action URLs, endpoint/route/digest/bulk/payload bounds, test throttling and isolation checks |
| 17 | Complete behavior/security/concurrency test matrix | Complete | Communications acceptance suite plus existing queue/delivery and source-specific V3 suites; Architecture V3 verification passed on the immutable closeout candidate |
| 18 | UX closeout | Complete | Logical Notification Center, filters, route details, preferences/routing policy, named destinations, Web Push controls, localization/accessibility checks |
| 19 | Documentation and operational closeout | Complete | Canonical Communications architecture, product/global docs, immediate/digest scheduler wiring and conditional Web Push/mail launch readiness |
| 20 | Final repository reconciliation and release gates | Complete | Stale route-as-inbox/read-model/test assumptions removed; immutable candidate `f880cb40014b2ef5236facaf65ac2b68f90fd5ae` passed every required release workflow |

## Closeout evidence

Implementation and repository evidence are complete. Immutable implementation candidate `f880cb40014b2ef5236facaf65ac2b68f90fd5ae` passed CI, Architecture V3 Verification, Intelligence Verification, King Perks Verification, Visual Regression, CodeQL and Dependency Review. The extension is therefore closed and promoted to **Current complete capability**.