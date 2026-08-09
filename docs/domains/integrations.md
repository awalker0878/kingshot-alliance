# Integrations

[← Domain documentation](README.md)

## Purpose

Integrations provides the current external machine-to-machine contract for an alliance:

- a bounded, read-only HTTP API authenticated by alliance API credentials; and
- outbound signed HTTPS webhooks generated from externally contracted tenant outbox events.

Integrations owns credentials, subscriptions, delivery state, signing, retries and endpoint-safety policy. It does not own the alliance/event/contribution facts carried through those contracts.

The current implementation has no write API, OAuth flow, long-lived user token, Discord-specific contract or game-data ingestion interface.

## Management and authorization

Alliance → Integrations requires authenticated/verified active-Alliance context plus `alliance.manage`. Creating/revoking API credentials or webhook subscriptions additionally requires recent password confirmation. Creation endpoints are throttled to 10 requests per minute.

Alliance platform settings may disable API access or webhooks, and plan entitlements may limit credential/subscription count. Management actions are tenant-scoped, audited and recorded through the transactional outbox.

Secrets are shown only at creation. Store them in the consuming system's secret store; never copy them into source control, chat, tickets or documentation.

# HTTP API contract

## Base path, authentication and scopes

All supported endpoints are under `/api/v1` and use JSON over HTTPS in hosted environments. Send the issued bearer credential in `Authorization`.

Issued credentials use `ks_live_<12 lowercase hex prefix>.<64 lowercase hex secret>`. Only the prefix and SHA-256 verifier are persisted; the plaintext token is shown once.

Unknown, malformed, expired or revoked credentials and credentials missing the endpoint scope return `401`. A valid credential whose alliance cannot use API access returns `403`. API requests are limited to 60/minute, keyed by credential when available and source IP otherwise.

Supported issuance scopes remain:

| Scope | Grants |
| --- | --- |
| `alliance:read` | `GET /api/v1/alliance` |
| `events:read` | `GET /api/v1/events` |
| `contributions:read` | `GET /api/v1/contributions` |

The supported creation flow does not issue wildcard scopes.

## Response envelope and bounds

Successful endpoints return a top-level `data` field. Collection endpoints return `data` as an array, are bounded to 250 rows and expose no pagination contract. Consumers must not infer unlimited history.

Primary statuses are `200`, `401`, `403`, `429` and unexpected `5xx`. Clients should use bounded backoff for temporary failures/rate limits rather than parsing authentication text for hidden account state.

## `GET /api/v1/alliance`

Requires `alliance:read`. It returns the credential-bound alliance with `id`, `name`, `slug`, derived `kingdom`, `language` and `timezone`. Tenant identity comes from the credential; the caller never supplies an alliance ID.

The `kingdom` field is representation compatibility backed by the first-class Kingdom relation. It does not expose roster/player/snapshot/import/intelligence data.

## `GET /api/v1/events`

Requires `events:read`. Returns up to 250 event occurrences ordered by start time and beginning at `now() - 1 day`. Rows expose occurrence ID/start/end/status plus owning event title/timezone. Event joins include alliance identity so cross-tenant combinations are excluded.

## `GET /api/v1/contributions`

Requires `contributions:read`. Returns up to 250 approved contribution records ordered by `recorded_at` descending. Rows expose record ID, membership ID, value, effective period, recorded time, category/unit, data class and calculation version. Pending/reversed records are excluded.

## API compatibility

The `/api/v1` path, scopes, endpoint semantics and documented fields are the current compatibility boundary. Consumers should ignore unknown additive fields. Removing/renaming/changing meaning or authentication under the same contract requires an explicit API-contract revision.

`KINGDOMS-001` deliberately adds no public roster/snapshot/intelligence endpoint or API scope. A future Kingdoms API requires a separately approved integration contract.

# Webhook contract

## Subscription model

When webhooks are enabled and plan capacity permits, an alliance manager may create a subscription containing a name, HTTPS endpoint, one to 20 event selectors, encrypted signing secret, active/revoked state and creator attribution.

The signing secret is 32 random bytes represented as hexadecimal, encrypted at rest, hidden from serialization and shown once at creation.

## Event selection and external-exposure boundary

Selectors may be exact event names matching `[a-z0-9._-]` conventions or `*`. A source event is eligible only when:

- it has an `alliance_id`;
- the subscription belongs to that alliance;
- the subscription is active/not revoked;
- the selector contains the exact event or `*`; **and**
- the event type is part of the externally eligible integration boundary.

The runtime does not maintain a centralized public event catalog. Existing durable domain events can therefore exist without becoming external webhook contracts.

`KINGDOMS-001` makes that distinction explicit: `alliance.kingdom_updated` and every `kingdoms.*` outbox event are internal-only and are rejected by webhook fan-out even for wildcard or guessed exact selectors. The outbox still provides durable internal evidence; exposing Kingdoms events externally requires a future approved integration-contract change with documented event payloads and tests.

Other externally eligible tenant events continue to use the generic selector behavior. Receivers using `*` must safely ignore event types they do not understand.

## Stable envelope

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

`id` is the source outbox message, not the delivery record. `data` is event-specific and owned by its producer. Payloads exceeding 256 KiB after encoding are refused and persisted as failed rather than sent.

The stable contract is the envelope and signing process. Event-specific payloads do not share one schema, and incompatible envelope/signature changes require an explicit contract revision.

## HTTP request and signature

Delivery is JSON `POST` with `Content-Type: application/json`, `Accept: application/json`, `User-Agent: Kingshot-Alliance-Webhook/1.0`, 3-second connect timeout and 10-second total timeout.

Headers include:

```text
X-Kingshot-Delivery: <delivery-id>
X-Kingshot-Event: <event-type>
X-Kingshot-Timestamp: <unix-seconds>
X-Kingshot-Signature: sha256=<hex-hmac>
```

The signature is `HMAC-SHA256(signing_secret, <timestamp>.<exact-json-body>)`. Receivers should verify the raw-body signature with constant-time comparison, reject stale timestamps according to their own window and deduplicate by `X-Kingshot-Delivery`.

Do not parse/reserialize JSON before signature verification.

## Success, retry and recovery

Any `2xx` is success. Non-2xx/transport failures retry through the isolated `integrations` queue for up to five attempts with current backoff approximately 60 seconds, 5 minutes, 30 minutes and 2 hours for later attempts.

The job is unique per delivery ID for 24 hours. Persisted delivery idempotency uses subscription + source message. `integrations:queue-webhooks --limit=100` runs every minute to recover due pending deliveries after restarts.

After retry exhaustion the delivery is failed with bounded error text; successful deliveries retain response status and delivered time.

## Endpoint safety / SSRF boundary

Application validation requires HTTPS and rejects localhost/`.localhost`, `.local` and covered literal private/reserved IPv4/IPv6 destinations. The endpoint is revalidated immediately before delivery.

This is not a complete DNS-rebinding defense. Production egress policy must independently prevent webhook workers from reaching link-local, metadata, private service, cluster-management, control-plane and other non-public networks except approved dependencies.

## Retention and diagnostics

Management exposes recent delivery status, attempts, response code, bounded error, last-attempt and delivery timestamps. Successful/permanently failed delivery payload/error details are subject to platform retention; the current documented target is 30 days.

Do not depend on response bodies or retained webhook payloads as an application data store.

## Revocation and compromise

Revoking a credential causes subsequent API authentication failure. Revoking a subscription prevents future delivery attempts from being treated as active. If a token/signing secret is exposed, revoke and replace it; secrets are intentionally not recoverable from the management UI.

## Tenant-isolation invariants

- API clients never choose their alliance; the credential establishes tenant context.
- API queries filter by credential alliance.
- Integration management re-resolves credential/subscription IDs under the active Alliance.
- Webhook fan-out considers only active subscriptions for the source alliance and only externally eligible event types.
- Delivery idempotency includes subscription + source message.
- Alliance suspension/closure/deletion blocks normal API access even when credentials remain stored.

See [Identity, tenancy and membership](identity-tenancy-and-membership.md), [Platform scale and administration](platform-scale-and-administration.md), [Kingdoms](kingdoms.md), the [security baseline](../security/security-baseline.md), and [production launch approval](../product/production-launch-approval.md).
