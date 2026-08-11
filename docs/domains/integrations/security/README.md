# Integrations security profile

[← Integrations domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Integrations  
**Code owner:** `app/Domain/Integrations`  
**Primary security boundary:** Alliance-bound machine credentials and signed outbound delivery across an external network trust boundary

## 1. Security purpose and scope

Integrations protects the application's external machine boundary: bounded read-only API access and outbound signed webhooks. Its objective is to prevent credential/scope expansion, cross-tenant reads, secret leakage, unintended event exposure, endpoint abuse, and unsafe retry amplification.

The two independently risky contracts are reviewed in [API security review](api-security-review.md) and [Webhooks security review](webhooks-security-review.md).

## 2. Assets and sensitive data

Assets include API credential verifier/status/scope state, webhook subscription configuration, protected signing material, webhook delivery/attempt diagnostics, eligible serialized business payloads, and endpoint URLs.

Credential/signing plaintext is secret material. Business payloads may contain tenant-private data even when an external integration is authorized, so payload eligibility/minimization is part of the security boundary.

## 3. Actors, authentication and authorization

First-party creation/revocation/management requires authenticated verified active-Alliance context plus `alliance.manage` and recent password confirmation where applicable.

Machine API access is authenticated by an Alliance-bound credential and constrained by fixed read scopes. Webhook fan-out begins from a source Alliance and an explicitly externally eligible event; subscription ownership never authorizes a source event by itself.

## 4. Tenant and privacy boundaries

Credential/subscription/delivery state is Alliance scoped. API tenant context is derived from the credential rather than a caller-supplied tenant selector. Webhook payloads contain only the approved source-Alliance representation for the eligible event.

Internal outbox events, audit events, or Kingdoms event families are not externally visible merely because they exist internally.

## 5. Trust boundaries and data flows

Material boundaries are privileged browser → credential/subscription management, external client → read-only API, producer transaction/outbox → Integrations event eligibility/fan-out, application → external webhook endpoint, and integrations queue → retry/recovery processing.

Production DNS/routing/egress controls are outside what endpoint validation alone can prove.

## 6. Threats, abuse cases and controls

Threats include credential theft/replay, scope escalation, cross-tenant API substitution, token/secret logging, wildcard event overexposure, webhook signature bypass, endpoint SSRF/internal-network targeting, DNS rebinding, oversized payloads, retry storms, duplicate delivery, and leaking sensitive producer fields.

Controls include verifier-only/controlled credential persistence, fixed scopes, tenant derivation, named rate/row bounds, one-time/protected secret handling, explicit external-event eligibility, signed bounded payloads, endpoint validation, isolated queue capacity, stable delivery identity, bounded retries/backoff, and payload minimization.

## 7. Integrity, concurrency and idempotency

API reads are synchronous and do not create write authority. Webhook delivery is at-least-once; stable delivery identity and attempt state make retries observable and consumers must tolerate duplicate delivery.

Subscription/credential lifecycle is explicit and revocable. Internal producer state is not modified to make an external retry appear successful.

## 8. Secrets and credential handling

Plaintext API credential or webhook signing material is exposed only through the supported creation/rotation boundary where implemented and is never routine serialized state. Persistence stores only the protected form required for verification/signing behavior.

Secrets must not appear in logs, audit metadata, outbox payloads, CI output, exports, exception diagnostics, or documentation. Production values belong in approved secret-management/runtime controls.

## 9. Destructive operations, retention and deletion

Credential/subscription revocation is a privileged security transition and stops future accepted access/delivery according to the capability contract. Delivery attempt history may be retained for bounded diagnostics without retaining unnecessary sensitive payloads.

Alliance/account lifecycle is coordinated by Platform; Integrations remains owner of credential/subscription/delivery cleanup semantics.

## 10. Auditability, observability and evidence

Credential/subscription lifecycle and security-relevant management transitions are attributable. API failures are diagnosed separately by auth/scope/tenant/rate/bounds; webhook failures by source eligibility, subscription state, queue, endpoint, signature/delivery identity, and retry state.

Tests cover credential/scope/tenant behavior, rate/row bounds, event eligibility, signature, endpoint checks, payload bounds, retry/idempotency, and explicit non-capabilities. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Application endpoint validation cannot fully prevent DNS rebinding or routing changes; production egress policy must independently block metadata/private/management networks and provide evidence. External recipients can retain data after authorized delivery.

Integrations does not provide write APIs, OAuth/user tokens, Discord-specific behavior, automated game ingestion, unrestricted wildcard exposure, or public Kingdoms API/webhook contracts.

## 12. Focused reviews and related documentation

- [API security review](api-security-review.md)
- [Webhooks security review](webhooks-security-review.md)
- [Read-only API contract](../api.md)
- [Webhook contract](../webhooks.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 6 threat model](../../../security/phase-6-threat-model.md)
