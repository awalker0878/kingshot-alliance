# Phase 6 threat model

## Assets and trust boundaries

Phase 6 introduces a cross-tenant administrator boundary, machine credentials, webhook egress, lifecycle/destructive operations, tenant-complete exports, and account deletion. These controls have a larger blast radius than normal alliance administration and therefore do not share the alliance role boundary.

## Platform administrator compromise

Risk: a compromised platform administrator could inspect or change many tenants.

Controls:

- platform administrator grants are separate from alliance permissions;
- verified email and confirmed MFA are mandatory;
- privileged web routes require recent password confirmation;
- grant/revocation and platform mutations are audited;
- Horizon is restricted to the same platform-admin/MFA population;
- no support-impersonation capability exists in Phase 6;
- lifecycle mutations require reasons and use explicit state transitions.

Operationally, platform administrator membership should remain small and reviewed periodically.

## Tenant lifecycle abuse

Risk: accidental or malicious suspension/deletion causes service or data loss.

Controls: inactive alliances fail tenant-context/API authentication; logical deletion requires closure first; deletion is reversible until retention expiry; legal holds block deletion; lifecycle events are audited/outboxed; export is available before destructive operations.

## API credential theft

Risk: leaked credentials allow tenant data reads.

Controls: credentials are tenant-scoped, read-only scoped, one-time reveal, hash-stored, expiry-aware, revocable, rate-limited, and invalid when the tenant is inactive. Plaintext credentials cannot be recovered from storage. Logs/UI must never serialize `secret_hash` or returned plaintext after issuance.

## Webhook SSRF and egress abuse

Risk: a tenant points a webhook at metadata/private/management endpoints or drives retry amplification.

Application controls require HTTPS; reject localhost, `.local`, and literal private/reserved addresses; bound payload size; bound HTTP/connect timeouts; cap subscription count; isolate integrations workers; use finite retries/backoff; and record failures.

DNS rebinding cannot be solved reliably by string validation alone. Production egress policy is mandatory defense in depth: application/worker networks must deny cloud metadata, link-local, RFC1918/ULA, cluster management, database/cache, and other internal destinations except explicit dependencies. Redirect behavior should remain constrained by the HTTP client/platform egress layer.

## Webhook authenticity and replay

Outbound webhooks are HMAC-SHA256 signed over timestamp plus exact JSON body. Consumers should enforce a short timestamp tolerance, use constant-time signature comparison, and deduplicate by `X-Kingshot-Delivery`. Signing secrets are encrypted at rest and shown to the alliance once at creation.

## Retry storms and queue starvation

Risk: failing destinations consume worker capacity and affect core application work.

Controls: integration work uses a dedicated queue/supervisor; retry count/backoff are finite; recovery scan is bounded; webhook delivery materialization is idempotent; payload/error retention is bounded. Operators should reduce integration concurrency before allowing retries to affect core queues.

## Tenant export leakage

Risk: cross-tenant or secret leakage through platform export.

Controls: export is platform-admin/password-confirmed only; every exported table is filtered by `alliance_id`; known secret/verifier columns are redacted; export metadata includes checksum, row count, requester, and audit table counts. The alliance root record is the requested tenant only. Export has a synchronous size bound.

## Account deletion and legal holds

Risk: deleting records that must be retained, or retaining direct identifiers unnecessarily.

Controls: seven-day cooling-off; ownership/admin blockers; legal-hold check; account anonymization instead of destructive removal of audit/business history; token revocation; explicit processed/blocked state. Legal-hold placement/release is audited.

## Encryption key recovery

Webhook signing secrets and existing MFA material use application encrypted casts. Loss of the application encryption key makes restored encrypted values unusable. Production backup/recovery therefore includes secret/configuration recovery under separate access control.

## Out of scope / rejected capability

Support impersonation is not implemented. The implementation plan says it may exist only if approved; no approval exists. Future approval would require a separate design for reason/ticket, target scope, time limit, reauthentication, user/operator visibility, session watermarking, read/write restrictions, complete audit, and emergency revocation before any runtime code is added.
