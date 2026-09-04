# Communications Recipient Delivery Acceptance Matrix

Status: Selected extension

Canonical product contract: [Communications — Recipient Delivery & Notification Experience](communications-recipient-delivery-expansion.md).

| ID | Acceptance criterion | Evidence |
| --- | --- | --- |
| COM-01 | One source notification creates one logical `NotificationMessage` regardless of selected delivery routes. | Behavior tests |
| COM-02 | Route fan-out creates zero or more `NotificationDelivery` records with stable route idempotency. | Behavior tests |
| COM-03 | Source callers submit scalar/value-object notification intent and never inspect Communications persistence models. | Architecture tests/search |
| COM-04 | Account defaults and Governor overrides resolve deterministically and cannot cross Governor/account boundaries. | Behavior tests |
| COM-05 | Quiet hours defer external delivery without hiding the in-app message. | Behavior tests |
| COM-06 | Urgent quiet-hours bypass is recipient-controlled. | Behavior tests |
| COM-07 | Temporary mute preserves message identity and prevents disallowed external sends. | Behavior tests |
| COM-08 | Multiple named endpoints per external channel are supported and concrete routes reference concrete endpoints. | Schema + behavior tests |
| COM-09 | Endpoint save/test/pause/resume/reverify/delete is scoped, audited and does not expose credentials. | HTTP/behavior tests |
| COM-10 | Endpoint health records successful verification and provider failure without disabling on transient rate limiting. | Behavior tests |
| COM-11 | Web Push supports multiple devices and shares routing/retry/idempotency policy with other external channels. | Behavior tests |
| COM-12 | Unsafe Web Push destinations, stale subscriptions and bounded-payload violations are rejected/handled safely. | Security tests |
| COM-13 | Notification Center reads logical messages with bounded cursor pagination and filters. | Read-model/HTTP tests |
| COM-14 | Mark read/unread/archive/restore and bounded bulk actions recheck current recipient ownership at commit. | Behavior tests |
| COM-15 | Delivery details appear beneath one logical inbox message; fan-out never appears as duplicate inbox notifications. | Frontend/behavior tests |
| COM-16 | Generic digest dispatch groups recipient-selected external routes without replacing individual logical messages. | Behavior tests |
| COM-17 | Digest dispatch is bounded, idempotent and recoverable after provider failure. | Behavior tests |
| COM-18 | Email delivery consumes Accounts-owned verified email through an explicit scalar contract and does not copy account identity ownership. | Architecture/behavior tests |
| COM-19 | Delivery workers recheck endpoint/policy state, respect attempt budgets and remain safe under duplicate workers. | Concurrency tests |
| COM-20 | Discord, Telegram, Web Push and Email provider failures cannot mutate source-domain truth. | Architecture/behavior tests |
| COM-21 | Action URLs are safe application-relative handoffs and provider payloads are bounded/sanitized. | Security tests |
| COM-22 | Platform diagnostics expose bounded privacy-safe delivery aggregates without provider secrets or unnecessary message content. | Read-model tests |
| COM-23 | Every supported notification type/channel/status has localized user-facing labels including `account.security`. | Frontend tests |
| COM-24 | Existing account-security, Alliance announcement, Event, Gift Code, Intelligence, King Perk and Officer Brief source integrations use the normalized intent contract. | Integration tests/search |
| COM-25 | Production checks cover scheduler/worker readiness plus Web Push and mail configuration when enabled. | Operations tests/docs |
| COM-26 | Capability catalogue, gap analysis, architecture docs, frontend map, user journeys and delivery ledger match implemented behavior. | Documentation reconciliation |
