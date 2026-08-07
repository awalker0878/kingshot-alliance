# Integrations domain

Phase 6 activates the `Integrations` domain for tenant-scoped machine access and outbound delivery.

It owns API credentials, API credential authentication, webhook subscriptions, delivery records, signing, retry jobs, endpoint policy, and the read-only `/api/v1` foundation. API credentials are scoped to exactly one alliance and store only a SHA-256 secret verifier after the one-time token response. Webhook signing secrets are encrypted at rest because the application must recover them to create HMAC signatures.

Integration work runs on the dedicated `integrations` queue. Outbox fan-out is idempotent by subscription/message pair, synchronous payloads are bounded to 256 KiB, retries use bounded backoff, and exhausted deliveries become failed records rather than looping forever.

This domain does not own alliance authorization, plans, quotas, tenant lifecycle, audit storage, or the transactional outbox. Those remain in their canonical domains.
