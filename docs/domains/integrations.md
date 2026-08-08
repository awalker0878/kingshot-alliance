# Integrations

[← Domain documentation](README.md)

## Purpose

Integrations provides the current external machine-to-machine contract for an alliance. It has two surfaces:

- a bounded, read-only HTTP API authenticated by alliance API credentials; and
- outbound HTTPS webhooks generated from published tenant outbox events.

Integrations owns API credentials, webhook subscriptions, webhook delivery state, signing, retry behavior, and endpoint-safety policy. It does not own the alliance, event, contribution, or other domain facts exposed through those contracts.

The API is deliberately small. There is no supported write API, OAuth flow, long-lived user token, Discord-specific contract, or game-data ingestion interface in the current implementation.

## Management and authorization

Integration administration is available under **Alliance → Integrations**.

The management surface requires:

- authenticated session;
- current session validation;
- verified email;
- active-alliance context; and
- `alliance.manage`.

Creating or revoking API credentials and webhook subscriptions additionally requires recent password confirmation. Creation endpoints are throttled to 10 requests per minute.

Alliance platform settings may disable API access or webhooks independently, and plan entitlements may limit the number of credentials/subscriptions. Management actions are alliance-scoped, audited, and emitted through the transactional outbox.

Secrets are shown only at creation time. Operators should copy them directly into the consuming system's secret store rather than chat, tickets, source control, or documentation.

# HTTP API contract

## Base path and version

All currently supported API endpoints are under:

`/api/v1`

The `v1` path is the API contract version. The current API is JSON over HTTPS in hosted environments and is read-only.

## Authentication

Send the issued credential in the HTTP `Authorization` header:

```text
Authorization: Bearer ks_live_<prefix>.<secret>
```

The supported management flow issues tokens with:

- a 12-character lowercase hexadecimal public prefix; and
- a 64-character lowercase hexadecimal secret.

Only the prefix and SHA-256 verifier are persisted. The plaintext token is returned once when the credential is created.

Credentials may have an optional expiry and may be revoked at any time. Authentication rejects a malformed token, unknown prefix, invalid secret, expired credential, revoked credential, or credential missing the endpoint's required scope.

The credential resolves exactly one alliance. API access is denied if that alliance is not in the active lifecycle state.

## Supported scopes

The supported credential-issuance scopes are:

| Scope | Grants |
| --- | --- |
| `alliance:read` | `GET /api/v1/alliance` |
| `events:read` | `GET /api/v1/events` |
| `contributions:read` | `GET /api/v1/contributions` |

A credential may carry one or more of these scopes. The supported creation flow does not issue a wildcard scope.

## Rate limiting

The API limiter allows 60 requests per minute. Its key is the API credential ID when that request attribute is available, otherwise the source IP address.

Clients should treat HTTP `429` as a temporary rate-limit response and back off rather than retrying aggressively.

## Common response envelope

Successful endpoints return JSON with a top-level `data` field:

```json
{
  "data": {}
}
```

Collection endpoints return `data` as an array. The current collection endpoints are bounded to 250 rows and do **not** expose pagination parameters. Consumers must not assume the API represents unlimited history.

## Error semantics

The current authentication boundary uses these primary HTTP statuses:

| Status | Meaning |
| --- | --- |
| `200` | Request succeeded. |
| `401` | Credential is missing/malformed/invalid/expired/revoked or lacks the required scope. |
| `403` | Credential is valid but its alliance is not available for API access. |
| `429` | API rate limit exceeded. |
| `5xx` | Unexpected server/runtime failure; retry only with bounded backoff. |

Do not distinguish account/credential state by parsing error text. Treat the status code and the credential's management state as authoritative.

## `GET /api/v1/alliance`

**Required scope:** `alliance:read`

Returns the alliance bound to the credential.

`data` fields:

| Field | Description |
| --- | --- |
| `id` | Alliance ULID as a string. |
| `name` | Alliance name. |
| `slug` | Public alliance slug. |
| `kingdom` | Kingdom/reference value; may be null. |
| `language` | Alliance language code/value. |
| `timezone` | Alliance time-zone identifier. |

The endpoint never accepts an alliance identifier from the caller; tenant identity comes from the credential.

## `GET /api/v1/events`

**Required scope:** `events:read`

Returns up to 250 event occurrences for the credential's alliance, ordered by occurrence start time. The query includes occurrences whose start is at least `now() - 1 day`, so it includes a small recent window as well as upcoming data rather than being a complete historical feed.

Each row currently exposes:

| Field | Description |
| --- | --- |
| `id` | Event occurrence ULID. |
| `starts_at` | Persisted occurrence start timestamp. |
| `ends_at` | Persisted occurrence end timestamp. |
| `status` | Occurrence status. |
| `title` | Owning event title. |
| `timezone` | Owning event time zone. |

The endpoint joins occurrences to their owning event using both event ID and alliance ID. Cross-alliance occurrence/event combinations are therefore excluded by the query boundary.

## `GET /api/v1/contributions`

**Required scope:** `contributions:read`

Returns up to 250 **approved** contribution records for the credential's alliance, ordered by `recorded_at` descending.

Each row currently exposes:

| Field | Description |
| --- | --- |
| `id` | Contribution record ULID. |
| `membership_id` | Alliance membership associated with the record. |
| `value` | Recorded contribution value. |
| `period_start` | Effective period start. |
| `period_end` | Effective period end. |
| `recorded_at` | Record timestamp. |
| `category` | Contribution category name. |
| `unit` | Category unit. |
| `data_class` | Recorded fact / calculated metric / subjective assessment classification. |
| `calculation_version` | Calculation version when applicable. |

Pending and reversed records are not returned by this endpoint.

## API compatibility

The current compatibility boundary is the `/api/v1` path, required scopes, endpoint semantics, and documented fields above. Consumers should ignore unknown additional JSON fields rather than failing closed on additive fields.

A removal, rename, meaning change, authentication change, or other incompatible API contract change must not be silently introduced under the same documented contract. It requires an explicit API-contract revision and corresponding tests/documentation before implementation.

# Webhook contract

## Subscription model

An alliance manager may create an HTTPS webhook subscription when webhooks are enabled for the alliance and plan capacity permits it.

A subscription contains:

- name;
- HTTPS endpoint URL;
- one to 20 selected event names;
- encrypted signing secret;
- active/revoked state; and
- creator attribution.

The signing secret is generated as 32 random bytes represented as hexadecimal, stored with an encrypted cast, hidden from normal model serialization, and shown once at creation.

## Event selection

A subscription may list exact event names matching the current event-name format (`a-z`, digits, `.`, `_`, `-`) or the wildcard `*`.

A published tenant outbox message is eligible when:

- it carries an `alliance_id`;
- the subscription belongs to that alliance;
- the subscription is active and not revoked; and
- its event list contains either the exact outbox `event_type` or `*`.

There is no centralized public webhook event-name catalog in the current runtime. Event names originate from durable domain outbox events. Examples currently emitted by the platform include `alliance.created`, invitation and membership lifecycle events, `event.reminder.requested`, `contribution.report.requested`, and integration-management events.

Consumers that depend on a specific event should subscribe to the exact documented event type. Use `*` only when the receiver is designed to safely ignore event types it does not understand.

## Webhook envelope

Each delivery body uses this stable top-level envelope:

```json
{
  "id": "<source-outbox-message-id>",
  "event": "<event-type>",
  "occurred_at": "<event-occurrence-timestamp>",
  "alliance_id": "<alliance-id>",
  "data": {}
}
```

Field meanings:

| Field | Meaning |
| --- | --- |
| `id` | Source transactional-outbox message ID. This is not the webhook delivery ID. |
| `event` | Source outbox `event_type`. |
| `occurred_at` | Timestamp carried by the source event. |
| `alliance_id` | Owning alliance. |
| `data` | Event-specific payload copied from the source outbox message. |

Payloads larger than 256 KiB after JSON encoding are refused and recorded as failed rather than queued for network delivery.

The stable contract is the envelope and signing process. The current envelope does not include a separate webhook schema-version field. Event-specific `data` is owned by the event producer; consumers should ignore unknown additive keys and must not assume every event has the same `data` shape.

Any incompatible change to the envelope or signature calculation requires an explicit webhook-contract revision before implementation.

## HTTP request

Webhook deliveries are JSON `POST` requests with:

- `Content-Type: application/json`;
- `Accept: application/json`;
- `User-Agent: Kingshot-Alliance-Webhook/1.0`;
- 3-second connection timeout; and
- 10-second total request timeout.

The request includes these verification headers:

```text
X-Kingshot-Delivery: <webhook-delivery-id>
X-Kingshot-Event: <event-type>
X-Kingshot-Timestamp: <unix-seconds>
X-Kingshot-Signature: sha256=<hex-hmac>
```

`X-Kingshot-Delivery` is the webhook delivery record ID and is the correct key for receiver-side delivery deduplication. The body `id` identifies the source outbox message.

## Signature verification

The sender computes:

```text
HMAC-SHA256(signing_secret, <timestamp>.<exact-json-body>)
```

and sends the lowercase hexadecimal result as:

```text
X-Kingshot-Signature: sha256=<hex-hmac>
```

A receiver should:

1. read the raw request body exactly as received;
2. read `X-Kingshot-Timestamp`;
3. reject timestamps outside the receiver's accepted freshness window;
4. compute HMAC-SHA256 over `<timestamp>.<raw-body>` using the subscription secret;
5. compare the supplied and computed signatures with a constant-time comparison; and
6. deduplicate processing by `X-Kingshot-Delivery`.

Do not parse and reserialize JSON before verifying the signature; any byte-level body change changes the HMAC input.

## Success and retry behavior

Any HTTP `2xx` response is treated as successful delivery.

A non-2xx response or transport failure is retried through the isolated `integrations` queue. The delivery job has a maximum of five attempts with current backoff intervals of approximately:

1. 60 seconds;
2. 5 minutes;
3. 30 minutes; and
4. 2 hours for later retries.

The job is unique per delivery ID for 24 hours, and the persisted delivery itself is idempotent on subscription plus source outbox message. Routine scheduler/job replay therefore should not create a second logical delivery for the same subscription/event message.

After the retry budget is exhausted, the delivery is marked failed with a bounded error excerpt. Successful deliveries record response status and delivery timestamp.

`integrations:queue-webhooks` runs every minute to recover persisted pending deliveries whose `available_at` is due, including after worker restarts.

## Endpoint safety and SSRF boundary

Application validation requires an HTTPS URL with a valid host and rejects:

- `localhost` and subdomains ending in `.localhost`;
- `.local` hosts; and
- literal private/reserved IPv4 or IPv6 destinations covered by the endpoint policy.

The endpoint is revalidated immediately before delivery.

This application validation is **not** a complete DNS-rebinding defense. Production egress policy must independently prevent webhook workers from reaching link-local, metadata, private service, cluster-management, control-plane, and other non-public networks except explicitly approved dependencies.

## Retention and diagnostics

Webhook management exposes recent delivery status, attempt count, HTTP response code, last error, last-attempt timestamp, and delivered timestamp.

Successful and permanently failed delivery body/error details are subject to the platform's retention enforcement; the current documented retention target is 30 days for delivery bodies/errors. Subscription metadata remains available until its own lifecycle removes it.

Response and error excerpts are bounded. Do not depend on webhook response bodies as an application data store.

## Revocation and secret compromise

Revoking an API credential makes subsequent authentication fail. Revoking a webhook subscription prevents future delivery attempts from being treated as active.

If an API token or webhook signing secret is exposed, revoke it and create a replacement rather than attempting to mutate or recover the existing secret. Because secrets are intentionally one-time display values, the management UI cannot reveal the original secret later.

## Tenant-isolation invariants

- API clients never choose their alliance by request parameter; the credential establishes tenant context.
- API queries explicitly filter by the credential's alliance.
- Integration management re-resolves credential/subscription IDs under the active alliance before mutation.
- Webhook fan-out only considers active subscriptions for the source event's alliance.
- Delivery idempotency includes the subscription and source message.
- Alliance suspension/closure/deletion prevents normal API access even when an otherwise valid credential remains stored.

See [Identity, tenancy, and membership](identity-tenancy-and-membership.md), [Platform scale and administration](platform-scale-and-administration.md), the [security baseline](../security/security-baseline.md), and [production launch approval](../product/production-launch-approval.md) for the surrounding authorization, egress, and launch controls.
