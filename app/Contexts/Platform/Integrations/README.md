# Integrations domain

## Purpose

Owns Alliance-bound API credentials/contracts plus outbound signed webhook subscriptions, delivery state, endpoint policy, retries, secret rotation, and integration-specific machine-access boundaries.

## Owned code

Runtime code in this module owns API credential authentication/scopes, `/api/v1` adapter contracts, external-actor pairing/receipts, webhook subscriptions/signing/delivery records, endpoint validation, retry jobs, and the dedicated `integrations` queue behavior.

## Public contracts

- fixed read scopes for Alliance, Events, and Contributions API reads;
- Alliance-bound credential authentication;
- one-time, revocable external-actor identity links and idempotent action receipts;
- versioned OpenAPI and webhook JSON Schema contracts;
- stable signed webhook envelope/signature behavior for explicitly externally eligible Alliance and global events;
- bounded/idempotent retry/delivery diagnostics.

Internal outbox publication does not automatically create a public webhook contract. Webhook fan-out is allowlisted by `WebhookEventCatalog`; wildcard subscriptions mean all catalogued public events, never all internal outbox messages.

The management surface receives the catalogue from the same contract used by validation and fan-out. Do not duplicate selector lists in a controller or frontend module. Public additions must have an owner-context outbox producer, explicit Alliance/global scope, stable documented payload fields and behavior coverage before they enter the catalogue. Fanout validates required fields and scope before queueing any delivery.

Endpoint verification creates one targeted `integration.test` delivery through the normal signing job without publishing an outbox message. Manual recovery changes only an exhausted delivery back to pending, preserves its immutable payload and identity, requires an active owning subscription, and records the manager action in the audit trail. Rotation replaces the encrypted signing secret immediately, shows the replacement once and preserves past delivery evidence.

External pairing stores keyed hashes rather than raw provider user IDs. A claim is bound to an Alliance, Player, provider and scoped API credential. Actor writes resolve that link server-side and reserve an action receipt before calling a cross-context workflow; the workflow calls the Operations owner action and never accepts a client-supplied Player ID. Revocation and scope checks are revalidated for every write.

## Dependencies

- `Alliances`, `Events`, `Contributions` — business data represented by bounded reads.
- `Operations/Participation` — owner of Event response, registration, capacity and waitlist rules called through the external participation workflow.
- `Platform` — lifecycle, availability/entitlement controls, queue/outbox infrastructure.
- `Authorization` / `Identity` — first-party integration management authorization/assurance.
- producer domains — event-specific payload semantics.

## Canonical documentation

- [`docs/domains/integrations/`](../../../docs/domains/integrations/README.md)
