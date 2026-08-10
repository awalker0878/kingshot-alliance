# Integrations domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Integrations`  
**Primary authorization boundary:** active Alliance context + `alliance.manage` for management; issued credential scopes for external API

## 1. Purpose and ownership

Integrations owns the current external machine-to-machine contract for an Alliance:

- a bounded read-only HTTP API authenticated by Alliance API credentials; and
- outbound signed HTTPS webhooks generated from externally contracted tenant outbox events.

Integrations owns credentials, subscriptions, delivery state, signing, retries, endpoint-safety policy, and the integration-specific authorization boundary. It does not own the Alliance, Event, Contribution, or Kingdoms facts carried through those contracts.

## 2. Scope

### In scope

- Alliance-bound API credentials and fixed read scopes;
- `/api/v1` read-only endpoints for Alliance, Events, and Contributions;
- webhook subscription creation/revocation;
- externally eligible event filtering;
- stable webhook envelope/signature behavior;
- retry, delivery diagnostics, payload limits, and endpoint validation; and
- plan/feature availability checks supplied by Platform.

### Out of scope

- write APIs;
- OAuth;
- long-lived user tokens;
- Discord-specific integration contracts;
- automated game-data ingestion;
- public Kingdoms roster/snapshot/intelligence/transfer/diplomacy API contracts; and
- treating every internal outbox event as automatically externally eligible.

## 3. Domain model

### API credentials

Issued credentials use:

```text
ks_live_<12 lowercase hex prefix>.<64 lowercase hex secret>
```

Only the prefix and SHA-256 verifier are persisted. The plaintext token is shown once.

Supported issuance scopes are:

| Scope | Grants |
| --- | --- |
| `alliance:read` | `GET /api/v1/alliance` |
| `events:read` | `GET /api/v1/events` |
| `contributions:read` | `GET /api/v1/contributions` |

The supported creation flow does not issue wildcard API scopes.

### Webhook subscriptions

A subscription contains Alliance ownership, name, HTTPS endpoint, one to 20 event selectors, encrypted signing secret, active/revoked state, and creator attribution.

The signing secret is 32 random bytes represented as hexadecimal, encrypted at rest, hidden from serialization, and shown once at creation.

### Delivery state

Webhook delivery state records source outbox message, subscription, attempt/retry state, response code, bounded error text, and delivery timestamps. Persisted idempotency uses subscription + source message.

## 4. Core invariants

1. API clients never choose their Alliance; the credential establishes tenant context.
2. API credentials are read-only and fixed-scope.
3. Unknown, malformed, expired, revoked, or insufficient-scope credentials fail authentication/authorization without leaking hidden tenant state.
4. API collection endpoints are bounded to 250 rows and expose no implicit unlimited-history contract.
5. Webhook fan-out requires both selector match and external event eligibility.
6. Internal outbox publication does not by itself create a public webhook contract.
7. `alliance.kingdom_updated` and all `kingdoms.*` events remain excluded from external webhook fan-out until separately approved.
8. Webhook payloads over 256 KiB are refused and persisted as failed rather than sent.
9. Delivery signatures cover `<timestamp>.<exact-json-body>` using HMAC-SHA256.
10. Endpoint application validation is not a substitute for production egress controls.
11. Secrets are recoverable only at creation; compromise requires revoke-and-replace.

## 5. Lifecycles and workflows

### Manage integrations

Alliance → Integrations requires authenticated/verified active-Alliance context plus `alliance.manage`. Creating/revoking credentials or subscriptions additionally requires recent password confirmation. Creation endpoints are throttled to 10 requests per minute.

Alliance platform settings may disable APIs/webhooks, and plan entitlements may limit credential/subscription count.

### HTTP API

All supported endpoints are under `/api/v1` and use JSON over HTTPS in hosted environments.

API requests are limited to **60/minute**, keyed by credential when available and source IP otherwise.

Successful endpoints use a top-level `data` field.

#### `GET /api/v1/alliance`

Requires `alliance:read`. Returns credential-bound Alliance data including `id`, `name`, `slug`, derived `kingdom`, `language`, and `timezone`.

The `kingdom` field is representation compatibility backed by the first-class Kingdom relation and does not expose roster/player/snapshot/import/intelligence state.

#### `GET /api/v1/events`

Requires `events:read`. Returns up to 250 Event occurrences ordered by start time, beginning at `now() - 1 day`, including occurrence timing/status plus owning Event title/time zone.

#### `GET /api/v1/contributions`

Requires `contributions:read`. Returns up to 250 approved Contribution records ordered by `recorded_at` descending. Pending/reversed records are excluded.

### Webhook subscription

When enabled and within plan capacity, a manager creates the subscription, stores the one-time signing secret externally, and selects exact events or `*`.

A source event is eligible only when:

- it has an `alliance_id`;
- the subscription belongs to that Alliance;
- the subscription is active/not revoked;
- the selector includes the exact event or `*`; and
- the event type is externally eligible.

### Stable webhook envelope

Externally eligible deliveries use:

```json
{
  "id": "<source-outbox-message-id>",
  "event": "<event-type>",
  "occurred_at": "<event-occurrence-timestamp>",
  "alliance_id": "<alliance-id>",
  "data": {}
}
```

`data` remains event-specific and producer-owned.

### HTTP delivery/signing

Webhook delivery is JSON `POST` with:

- `Content-Type: application/json`;
- `Accept: application/json`;
- `User-Agent: Kingshot-Alliance-Webhook/1.0`;
- 3-second connect timeout; and
- 10-second total timeout.

Headers include:

```text
X-Kingshot-Delivery: <delivery-id>
X-Kingshot-Event: <event-type>
X-Kingshot-Timestamp: <unix-seconds>
X-Kingshot-Signature: sha256=<hex-hmac>
```

The signature is `HMAC-SHA256(signing_secret, <timestamp>.<exact-json-body>)`. Receivers should verify the raw body with constant-time comparison, reject stale timestamps according to their policy, and deduplicate using `X-Kingshot-Delivery`.

### Retry and recovery

Any `2xx` is success. Non-2xx/transport failures retry through the isolated `integrations` queue for up to five attempts, with current later backoff approximately 60 seconds, 5 minutes, 30 minutes, and 2 hours.

The job is unique per delivery ID for 24 hours. `integrations:queue-webhooks --limit=100` runs every minute to recover due pending deliveries after restarts.

## 6. Authorization and tenancy

Integration management requires active Alliance context plus `alliance.manage`; sensitive create/revoke operations require recent password confirmation.

External API tenant context is derived exclusively from the credential. API queries are filtered by the credential's Alliance.

Webhook fan-out considers only active subscriptions for the source Alliance. Alliance suspension/closure/deletion blocks normal API access even if credential records remain stored.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — Alliance identity/state and read-only representation.
- **Events** — bounded read-only Event occurrence representation.
- **Contributions** — bounded read-only approved record representation.
- **Platform** — API/webhook availability, entitlements, queue/runtime infrastructure, and tenant lifecycle state.
- **Authorization/Identity** — management permission and recent identity confirmation.
- **Producer domains** — externally eligible event-specific payload semantics.

### Exposes

- credential-authenticated read-only API contracts;
- signed/retried external webhook delivery for explicitly eligible events; and
- management/diagnostic surfaces for credentials, subscriptions, and deliveries.

## 8. Persistence and data ownership

Integrations owns API credential prefix/verifier/status/scope state, webhook subscriptions and encrypted signing secrets, and webhook delivery/attempt state.

It does not own the business records serialized by read endpoints or webhook producers.

Successful/permanently failed delivery payload/error details are subject to platform retention; the documented target is 30 days.

## 9. Events, outbox and integrations

The shared transactional outbox supplies durable source events. Integrations applies an explicit external-exposure boundary before fan-out.

Wildcard subscription does not bypass that eligibility boundary. Receivers using `*` must safely ignore event types they do not understand.

The current runtime does not maintain a centralized public event schema registry. Incompatible envelope/signature changes require an explicit integration-contract revision.

## 10. HTTP, UI and API surfaces

- Alliance management UI: **Alliance → Integrations**.
- API base: `/api/v1`.
- Supported read endpoints: `/alliance`, `/events`, `/contributions`.
- Outbound webhook: signed HTTPS JSON `POST`.

Primary API statuses are `200`, `401`, `403`, `429`, and unexpected `5xx`.

## 11. Background processing

Webhook HTTP delivery runs through the isolated `integrations` Laravel queue. The recovery command `integrations:queue-webhooks --limit=100` runs every minute.

The shared Platform outbox publisher remains the durable bridge from producer transaction to integration fan-out.

## 12. Failure, idempotency and concurrency

- API clients should use bounded backoff for temporary failures/rate limits.
- Delivery idempotency uses subscription + source outbox message.
- Job uniqueness is per delivery ID for 24 hours.
- Retry exhaustion persists failed state with bounded error text.
- Revocation prevents future credential/subscription use.
- Payloads over 256 KiB fail before transport.
- Endpoint validation is repeated immediately before delivery.

## 13. Security and privacy

Secrets are shown only once and must be stored by the consuming system in a secret store, never in repository/chat/ticket/docs content.

Application endpoint validation requires HTTPS and rejects localhost/`.localhost`, `.local`, and covered literal private/reserved IPv4/IPv6 destinations. Production infrastructure must independently block access to link-local, metadata, private service, cluster/control-plane, and other non-public networks except approved dependencies.

Receivers must verify signatures against the exact raw JSON body; parsing/reserializing before verification changes the signed bytes.

## 14. Observability and operations

Management exposes recent delivery status, attempts, response code, bounded error, last-attempt time, and delivery time. Operators should use queue/outbox/observability guidance to distinguish source-event publication, fan-out, queueing, transport failure, and retry exhaustion.

See [Background processing](../../operations/background-processing.md) and [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests should protect:

- credential parsing/hash verification/revocation;
- scope/tenant enforcement and rate bounds;
- endpoint row bounds;
- webhook event-eligibility filtering including wildcard exclusions;
- HMAC signature/envelope behavior;
- payload bounds and SSRF/application endpoint checks;
- retry/idempotency/queue recovery; and
- cross-tenant management and delivery isolation.

## 16. Explicit non-capabilities

Integrations does not currently implement:

- write API endpoints;
- OAuth;
- long-lived user API tokens;
- Discord-specific contracts;
- automated game-data ingestion; or
- public Kingdoms roster/snapshot/intelligence/transfer/diplomacy API or webhook contracts.

## 17. Capability documents

No separate Integrations capability documents are required at present. The API and webhook contracts remain in this root because they share credential/subscription/external-boundary ownership.

## 18. Related documentation

- [Alliances domain](../alliances/README.md)
- [Events domain](../events/README.md)
- [Contributions domain](../contributions/README.md)
- [Platform domain](../platform/README.md)
- [Kingdoms domain](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Background processing](../../operations/background-processing.md)
- [Production launch approval](../../product/production-launch-approval.md)
- [`app/Domain/Integrations/README.md`](../../../app/Domain/Integrations/README.md)
