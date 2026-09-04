# Communications Recipient Delivery Acceptance Matrix

Status: Complete candidate — requires containing PR release gates

Canonical product contract: [Communications — Recipient Delivery & Notification Experience](communications-recipient-delivery-expansion.md).

Primary behavior evidence:

- `tests/v3/Contexts/Communications/Delivery/CommunicationsRecipientDeliveryAcceptanceV3Test.php`
- `tests/v3/Contexts/Communications/Delivery/NotificationDeliveryBehaviorV3Test.php`
- `tests/v3/ReadModels/NotificationDelivery/NotificationQueueDeliveryV3Test.php`
- source-specific V3 integration tests for Accounts, Alliance announcements, Gift Codes, Intelligence, King Perks, Events and Officer Briefs
- Architecture V3 Verification and frontend quality/localization/accessibility checks

| ID | Acceptance criterion | Concrete evidence |
| --- | --- | --- |
| COM-01 | One source notification creates one logical `NotificationMessage` regardless of selected delivery routes. | `NotificationQueueDeliveryV3Test`; `CommunicationsRecipientDeliveryAcceptanceV3Test::test_notification_center_pages_logical_messages_once_with_cursor_filters_and_route_details` |
| COM-02 | Route fan-out creates zero or more `NotificationDelivery` records with stable route idempotency. | `NotificationQueueDeliveryV3Test`, including replay/newly-enabled-route behavior; multi-endpoint acceptance coverage |
| COM-03 | Source callers submit scalar/value-object notification intent and never inspect Communications persistence models. | `NotificationIntent`/`NotificationQueueReceipt`; Architecture V3 Verification; normalized source-specific V3 integration tests |
| COM-04 | Account defaults and Governor overrides resolve deterministically and cannot cross Governor/account boundaries. | `CommunicationsRecipientDeliveryAcceptanceV3Test::test_preferences_and_routing_policy_inherit_with_quiet_hours_urgency_and_temporary_mute` |
| COM-05 | Quiet hours defer external delivery without hiding the in-app message. | Same routing-policy acceptance test verifies deferred Discord plus sent in-app route |
| COM-06 | Urgent quiet-hours bypass is recipient-controlled. | Same routing-policy acceptance test verifies blocked and recipient-enabled urgent bypass |
| COM-07 | Temporary mute preserves message identity and prevents disallowed external sends. | Same routing-policy acceptance test verifies Governor mute/defer and inheritance reset |
| COM-08 | Multiple named endpoints per external channel are supported and concrete routes reference concrete endpoints. | Schema uniqueness/indexes plus `test_multiple_named_endpoints_have_independent_health_lifecycle_and_audit_evidence` and Web Push multi-device coverage |
| COM-09 | Endpoint save/test/pause/resume/reverify/delete is scoped, audited and does not expose credentials. | Endpoint Actions/HTTP controller; encrypted/hidden endpoint model; endpoint lifecycle acceptance test; audit assertions |
| COM-10 | Endpoint health records successful verification and provider failure without disabling on transient rate limiting. | Endpoint lifecycle acceptance test: 204 -> healthy, 429 -> degraded/retry while enabled |
| COM-11 | Web Push supports multiple devices and shares routing/retry/idempotency policy with other external channels. | `test_web_push_rejects_unsafe_destinations_supports_multiple_devices_and_exhausts_stale_subscriptions`; common route resolver/provider worker |
| COM-12 | Unsafe Web Push destinations, stale subscriptions and bounded-payload violations are rejected/handled safely. | Web Push acceptance test; `EndpointConfigurationValidator`; `WebPushChannel` host/private-address/key/payload/410 handling |
| COM-13 | Notification Center reads logical messages with bounded cursor pagination and filters. | `NotificationInboxQuery`; logical-message pagination/filter acceptance test; Notification Center HTTP surface |
| COM-14 | Mark read/unread/archive/restore and bounded bulk actions recheck current recipient ownership at commit. | `NotificationDeliveryBehaviorV3Test`; `PreviewNotificationInboxBulkAction`; `BulkUpdateNotificationInbox`; HTTP controller tests/authorization checks |
| COM-15 | Delivery details appear beneath one logical inbox message; fan-out never appears as duplicate inbox notifications. | Logical-message inbox acceptance test; `NotificationInboxQuery`; `Accounts/Notifications/Index.vue`; frontend quality/accessibility checks |
| COM-16 | Generic digest dispatch groups recipient-selected external routes without replacing individual logical messages. | `test_digest_dispatches_are_idempotent_retryable_and_do_not_advance_the_due_window`; `BuildNotificationDigestDispatches` |
| COM-17 | Digest dispatch is bounded, idempotent and recoverable after provider failure. | Digest retry acceptance test plus `test_digest_builder_caps_each_dispatch_at_twenty_routes`; `ProcessNotificationDigests` |
| COM-18 | Email delivery consumes Accounts-owned verified email through an explicit scalar contract and does not copy account identity ownership. | `VerifiedNotificationEmailQuery`; `EmailDeliveryChannel`; `test_email_routes_require_verified_accounts_and_recheck_verification_before_send`; Architecture V3 Verification |
| COM-19 | Delivery workers recheck endpoint/policy state, respect attempt budgets and remain safe under duplicate workers. | `ProcessNotificationDeliveries`/`ProcessNotificationDigests` row locks, pending recovery and attempt budgets; worker reauthorization acceptance test; existing retry/idempotency V3 tests |
| COM-20 | Discord, Telegram, Web Push and Email provider failures cannot mutate source-domain truth. | Communications workers only update Communications delivery/endpoint/outbox operational state; Architecture V3 Verification; source-specific integration regression suite |
| COM-21 | Action URLs are safe application-relative handoffs and provider payloads are bounded/sanitized. | `NotificationDeliveryService::validateIntent`; Discord/Telegram/Web Push channel bounds; `test_action_urls_diagnostics_and_launch_checks_are_privacy_safe_and_channel_aware` |
| COM-22 | Platform diagnostics expose bounded privacy-safe delivery aggregates without provider secrets or unnecessary message content. | `PlatformAdministrationQuery`; diagnostics acceptance test asserts fingerprint/type/channel state without recipient ID, body or raw error |
| COM-23 | Every supported notification type/channel/status has localized user-facing labels including `account.security`. | `communications-recipient-delivery-labels.ts`, core localization messages, Notification Center type/channel/status/health label mappings, frontend localization/quality checks |
| COM-24 | Existing account-security, Alliance announcement, Event, Gift Code, Intelligence, King Perk and Officer Brief source integrations use the normalized intent contract. | Accounts security, Alliance announcement, Gift Code, Intelligence and Officer Brief V3 integration tests; Event/King Perk source actions; Architecture V3 Verification/search |
| COM-25 | Production checks cover scheduler/worker readiness plus Web Push and mail configuration when enabled. | `routes/console.php` schedules immediate/digest build/digest delivery every minute; `ProductionLaunchReadiness` notification checks; launch-readiness acceptance assertions |
| COM-26 | Capability catalogue, gap analysis, architecture docs, frontend map, user journeys and delivery ledger match implemented behavior. | Final documentation reconciliation in this candidate plus required CI, Architecture V3, frontend, Visual Regression, Intelligence, King Perks, CodeQL and Dependency Review gates |

The matrix is considered closed only when the containing PR commit carrying this evidence and the reconciled documentation passes every required release gate.