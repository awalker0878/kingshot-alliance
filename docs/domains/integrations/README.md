# Integrations domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Integrations`  
**Primary authorization boundary:** active Alliance + `alliance.manage` for management; credential scopes for external API

## 1. Purpose and ownership

Integrations owns the current external machine-to-machine boundary for an Alliance: bounded read-only HTTP API credentials/contracts and outbound signed webhook subscriptions/delivery.

It owns integration credentials/subscriptions/delivery state and external-boundary rules, not the Alliance/Event/Contribution facts represented through those contracts.

## 2. Scope

In scope: API credentials/fixed scopes/read endpoints, webhook subscription/signing/delivery/retries, external-event eligibility, endpoint safety, and integration availability/entitlement checks.

Out of scope: write APIs, OAuth/user tokens, Discord-specific contracts, automated game ingestion, and public Kingdoms API/webhook contracts.

## 3. Domain model

Integrations has two independently observable machine contracts:

- [Read-only API](api.md) — Alliance-bound credentials, fixed scopes, tenant-derived reads, rate/row bounds.
- [Outbound webhooks](webhooks.md) — subscriptions, event eligibility, signing, delivery/retries, endpoint safety.

## 4. Core invariants

1. Machine credentials/subscriptions are Alliance bound.
2. Plaintext secret material is not exposed through routine persistence/serialization/logging.
3. API scopes are fixed/read-only.
4. Webhook wildcard matching never bypasses explicit external-event eligibility.
5. Internal outbox publication does not itself create a public contract.
6. Current Kingdoms event/API families remain externally excluded unless separately approved.
7. Platform lifecycle/availability may disable normal integration access without transferring Integrations persistence ownership.

## 5. Lifecycles and workflows

Managers create/revoke API credentials and webhook subscriptions under Alliance management authorization and required Identity assurance.

API request lifecycle is defined in [api.md](api.md). Webhook fan-out, delivery, retry, and recovery are defined in [webhooks.md](webhooks.md).

## 6. Authorization and tenancy

First-party management requires active Alliance context plus `alliance.manage`; sensitive create/revoke operations require recent password confirmation. API tenant context is derived from the credential. Webhook fan-out is source-Alliance scoped.

## 7. Cross-domain contracts

Consumes Alliances, Events, Contributions, Platform controls/outbox infrastructure, Authorization/Identity management assurance, and producer-owned safe event semantics.

Exposes the [read-only API](api.md) and [signed webhook](webhooks.md) contracts. It does not transfer ownership of serialized business state.

## 8. Persistence and data ownership

Integrations owns API credential verifier/status/scope state, webhook subscriptions/protected signing state, and webhook delivery/attempt state. Producer/source business data remains in owning domains.

## 9. Events, outbox and integrations

Platform outbox publication is the durable source for eligible webhook fan-out, but public exposure is explicitly filtered by Integrations. API reads are synchronous and separate from webhook delivery.

## 10. HTTP, UI and API surfaces

Alliance → Integrations is the first-party management surface. Machine boundaries are the `/api/v1` read contract and outbound HTTPS webhook POST contract documented in the capability files.

## 11. Background processing

Webhook delivery uses the isolated integrations queue plus bounded recovery scheduling. The read API is synchronous.

## 12. Failure, idempotency and concurrency

Credential/scope/tenant failures, webhook delivery identity/retry/exhaustion, payload bounds, and endpoint checks are specified in [api.md](api.md) and [webhooks.md](webhooks.md).

## 13. Security and privacy

Integration secrets are controlled one-time/protected material. Endpoint validation and payload minimization are application controls; production egress controls remain independently required.

## 14. Observability and operations

Diagnose API auth/scope/lifecycle separately from webhook source-event/fan-out/queue/transport/retry state. See [Background processing](../../operations/background-processing.md) and [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests protect API credential/scope/tenant/rate/bounds and webhook eligibility/signature/payload/endpoint/retry/idempotency behavior plus explicit external non-capabilities.

## 16. Explicit non-capabilities

No write API, OAuth, long-lived user token, Discord-specific integration, automated game ingestion, or public Kingdoms machine contract is currently accepted.

## 17. Capability documents

- [Read-only API](api.md)
- [Outbound webhooks](webhooks.md)

## 18. Related documentation

- [Alliances](../alliances/README.md)
- [Events](../events/README.md)
- [Contributions](../contributions/README.md)
- [Platform](../platform/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Integrations/README.md`](../../../app/Domain/Integrations/README.md)
