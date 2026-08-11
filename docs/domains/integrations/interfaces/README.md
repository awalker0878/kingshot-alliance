# Integrations interfaces

[← Integrations domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Integrations  
**Code owner:** `app/Domain/Integrations`  
**Primary boundary:** Alliance-bound external read API credentials and outbound signed webhook delivery  
**P4 inventory decision:** Focused contracts reused — `../api.md`, `../webhooks.md`

## 1. Boundary purpose and ownership

Integrations owns the repository's accepted external machine-to-machine contracts: a bounded read-only `/api/v1` API and outbound signed HTTPS webhooks. It also owns first-party management of API credentials/webhook subscriptions and webhook delivery state.

Alliances, Events, Contributions, and other producer domains retain semantic ownership of represented facts/events. Platform owns transactional-outbox durability; Integrations decides whether an internal published event is eligible for external webhook fan-out.

## 2. Surface inventory

First-party management in `routes/integrations.php` includes:

- `GET /alliance/integrations`;
- API credential create/revoke; and
- webhook subscription create/revoke.

Sensitive create/revoke mutations require recent password confirmation; create routes are throttled at 10/minute.

External machine reads in `routes/api.php` are:

- `GET /api/v1/alliance` → `alliance:read`;
- `GET /api/v1/events` → `events:read`;
- `GET /api/v1/contributions` → `contributions:read`.

Outbound webhook delivery is an HTTPS POST contract rather than an inbound route.

## 3. Callers, authorization and tenancy

First-party integration management requires authenticated, verified active-Alliance context plus `alliance.manage` and the applicable password-confirmation boundary.

External API callers authenticate with an Alliance-bound bearer credential. The credential determines tenant identity; the API does not accept caller-selected Alliance tenancy. The API limiter permits 60 requests/minute keyed by API credential identity where available.

Webhook subscriptions are Alliance bound, capacity/entitlement checked, endpoint-policy checked, and limited to 1–20 event selectors.

## 4. Input and validation contracts

API credentials permit exactly these scopes:

- `alliance:read`;
- `events:read`;
- `contributions:read`.

The issued bearer format is one-time `ks_live_<12 lowercase hex>.<64 lowercase hex>`; persistence stores the SHA-256 verifier rather than plaintext secret. Expiry, when supplied, must be future.

Webhook event selectors are `*` or lowercase names matching `[a-z0-9._-]{3,120}`. URLs must pass `WebhookEndpointPolicy` at creation and again immediately before delivery.

Full machine-input contracts are in [Read-only API](../api.md) and [Outbound webhooks](../webhooks.md).

## 5. Output and disclosure contracts

The API response envelope is `{"data": ...}`. `/alliance` returns selected Alliance public/operational fields; `/events` and `/contributions` are bounded to 250 rows and expose selected source-domain fields only. See [Read-only API](../api.md).

Webhook payload envelope is:

```json
{
  "id": "<outbox message id>",
  "event": "<event type>",
  "occurred_at": "<source occurrence time>",
  "alliance_id": "<source Alliance>",
  "data": {}
}
```

Payloads exceeding 256 KiB are not delivered and are persisted as failed delivery state. Signature/header details are defined in [Outbound webhooks](../webhooks.md).

## 6. Internal actions, queries and services

Supported internal Integrations contracts include API credential create/revoke/authentication, webhook subscription create/revoke, endpoint policy, `QueueWebhookDeliveries`, `DeliverWebhook`, and the bounded Alliance/Event/Contribution API projection controller.

Producer domains do not call external endpoints directly. Platform's `OutboxPublished` event is the supported source for webhook fan-out.

## 7. Events, outbox and cross-domain consumers

`AppServiceProvider` routes every `OutboxPublished` event through `QueueWebhookDeliveries` after Platform publication. Integrations returns immediately when the event lacks Alliance identity or is not externally contracted.

The explicit external exclusion is mandatory:

- `alliance.kingdom_updated`; and
- every event whose type starts with `kingdoms.`.

Wildcard subscriptions do not bypass this exclusion. Internal publication therefore remains broader than the public webhook contract.

## 8. Commands, jobs and scheduled work

`integrations:queue-webhooks {--limit=100}` recovers/queues due webhook deliveries; the scheduler invokes `--limit=100` every minute with one-server/overlap protection.

New eligible delivery rows dispatch `DeliverWebhookJob` on the `integrations` queue. Delivery is idempotently identified by `webhook:<subscription-id>:<outbox-message-id>`.

## 9. Files, imports, exports and external dependencies

Integrations has no file import/export contract. Its material external dependency is outbound HTTPS/DNS/egress for webhooks; Redis/Horizon backs queued delivery and PostgreSQL persists credentials/subscriptions/delivery state.

The API is synchronous over PostgreSQL/source-domain state. External dependency degradation/recovery is detailed in [Integrations operations](../operations/README.md) and [Webhook delivery runbook](../operations/webhook-delivery.md).

## 10. Failure, idempotency, versioning and compatibility

Invalid credential format/verifier/expiry/revocation/scope returns 401; inactive Alliance access returns 403. API is versioned under `/api/v1`.

Webhook successful 2xx marks delivery Delivered. Non-success/transport errors return delivery to Pending with backoff of 60, 300, 1800, then 7200 seconds; inactive subscription/missing payload/oversized payload are terminal Failed conditions under the current action rules.

Webhook signature input is HMAC-SHA256 over `<unix timestamp>.<exact JSON body>`. Required headers include `X-Kingshot-Delivery`, `X-Kingshot-Event`, `X-Kingshot-Timestamp`, and `X-Kingshot-Signature: sha256=<hex>`. See [Outbound webhooks](../webhooks.md) for the accepted compatibility contract.

## 11. Explicit non-capabilities

Integrations does not provide:

- write API endpoints;
- OAuth or delegated-user machine tokens;
- public Kingdoms API scopes/routes;
- Kingdoms webhook exposure;
- automatic externalization of every outbox event;
- inbound webhook mutation endpoints; or
- direct producer-domain ownership of external delivery/retry state.

## 12. Focused contracts, evidence and related documentation

P4 reuses two accepted capability contracts:

- [Read-only API](../api.md)
- [Outbound webhooks](../webhooks.md)

Related documentation:

- [Integrations domain](../README.md)
- [Integrations security](../security/README.md)
- [Integrations operations](../operations/README.md)
- [Webhook delivery runbook](../operations/webhook-delivery.md)
- [Platform transactional outbox](../../platform/transactional-outbox.md)
- [Kingdoms](../../kingdoms/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
