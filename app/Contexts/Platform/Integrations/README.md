# Integrations domain

## Purpose

Owns Alliance-bound read-only API credentials/contracts plus outbound signed webhook subscriptions, delivery state, endpoint policy, retries, and integration-specific machine-access boundaries.

## Owned code

Runtime code in this module owns API credential authentication/scopes, `/api/v1` read surfaces, webhook subscriptions/signing/delivery records, endpoint validation, retry jobs, and the dedicated `integrations` queue behavior.

## Public contracts

- fixed read scopes for Alliance, Events, and Contributions API reads;
- Alliance-bound credential authentication;
- stable signed webhook envelope/signature behavior for explicitly externally eligible events; and
- bounded/idempotent retry/delivery diagnostics.

Internal outbox publication does not automatically create a public webhook contract. Webhook fan-out is allowlisted by `WebhookEventCatalog`; wildcard subscriptions mean all catalogued public events, never all internal outbox messages.

The management surface receives the catalogue from the same contract used by validation and fan-out. Do not duplicate selector lists in a controller or frontend module. Public additions must already have an Alliance-scoped outbox producer, stable documented payload fields and behavior coverage before they enter the catalogue.

## Dependencies

- `Alliances`, `Events`, `Contributions` — business data represented read-only.
- `Platform` — lifecycle, availability/entitlement controls, queue/outbox infrastructure.
- `Authorization` / `Identity` — first-party integration management authorization/assurance.
- producer domains — event-specific payload semantics.

## Canonical documentation

- [`docs/domains/integrations/`](../../../docs/domains/integrations/README.md)
