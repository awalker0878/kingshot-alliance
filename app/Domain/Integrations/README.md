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

Internal outbox publication does not automatically create a public webhook contract; current Kingdoms events remain explicitly excluded.

## Dependencies

- `Alliances`, `Events`, `Contributions` — business data represented read-only.
- `Platform` — lifecycle, availability/entitlement controls, queue/outbox infrastructure.
- `Authorization` / `Identity` — first-party integration management authorization/assurance.
- producer domains — event-specific payload semantics.

## Canonical documentation

- [`docs/domains/integrations/`](../../../docs/domains/integrations/README.md)
