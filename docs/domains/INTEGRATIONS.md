# Integrations

## API credentials

Alliance owners/leaders with `alliance.manage` may issue API credentials after recent password confirmation. A token is returned once as `ks_live_<prefix>.<secret>`. The database stores the public prefix and SHA-256 verifier, never the plaintext token. Credentials support bounded read-only scopes: alliance, events, and contributions. Authentication resolves exactly one alliance and rejects suspended, closed, or deleted tenants.

Credential use is rate-limited, expiry-aware, revocable, and records a coarse last-used timestamp. Creation/revocation is audited and emitted through the transactional outbox.

## Webhooks

Alliance managers may create HTTPS webhook subscriptions up to the plan limit. Each subscription stores an encrypted signing secret, event allow-list, endpoint, state, and creator. The secret is shown once on creation.

The outbox publisher fans matching tenant events into `webhook_deliveries`. Delivery creation is idempotent on subscription plus outbox message. Payloads above 256 KiB are refused rather than queued. Jobs run on the isolated `integrations` queue with a finite retry budget and exponential-style backoff.

Each request includes:

- `X-Kingshot-Delivery`
- `X-Kingshot-Event`
- `X-Kingshot-Timestamp`
- `X-Kingshot-Signature: sha256=<HMAC>`

The HMAC input is `<timestamp>.<exact JSON body>` using the subscription signing secret. Consumers should reject stale timestamps, compare signatures in constant time, and deduplicate by delivery ID.

## Endpoint safety

Application validation requires HTTPS and rejects localhost, `.local`, and literal private/reserved network addresses. This reduces accidental SSRF exposure but is not the only production control: DNS rebinding and host-resolution changes are handled by deployment egress policy. Production infrastructure must deny link-local, metadata, private service, cluster-management, and other non-public destinations from application/worker egress except explicitly approved dependencies.

## Delivery retention and recovery

Successful and permanently failed delivery bodies/errors are redacted after 30 days. Subscription metadata remains available for management. `integrations:queue-webhooks` requeues due pending records after worker restarts; `ShouldBeUnique` plus delivery idempotency prevents routine duplicate queue work. Delivery logs record attempts, HTTP response code, timestamps, and bounded error excerpts.

## API foundation

Phase 6 exposes read-only `/api/v1/alliance`, `/api/v1/events`, and `/api/v1/contributions` endpoints to demonstrate scope enforcement and provide a stable integration foundation. This phase does not introduce broad write APIs.
