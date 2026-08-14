# Outbound webhooks

[← Integrations domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Integrations

## 1. Purpose

Defines outbound signed HTTPS webhook subscriptions, delivery state, event eligibility, retry behavior, and endpoint-safety boundaries.

## 2. Scope and non-scope

In scope:

- Alliance-owned webhook subscriptions;
- event selector matching plus explicit external-eligibility filtering;
- stable envelope/signature behavior;
- persisted delivery/attempt state;
- bounded payload/error handling;
- retry scheduling and queue recovery; and
- endpoint validation.

Out of scope:

- generic internal outbox publication;
- inbound webhooks;
- public exposure of every internal event;
- public Kingdoms event families; and
- infrastructure-level egress policy ownership.

## 3. Model and state

A subscription belongs to one Alliance and includes its endpoint, selected event types, protected signing material, active/revoked state, and creator attribution.

A delivery records the source outbox message, subscription, attempt/retry state, response code, bounded error information, and delivery timestamps. Logical delivery identity is subscription plus source message.

## 4. Invariants

1. Webhook fan-out requires both selector match and explicit external event eligibility.
2. Wildcard subscription never bypasses external-eligibility filtering.
3. A subscription is tenant bound to the source Alliance.
4. Signing material is protected at rest and never emitted through routine serialization/logging.
5. The signed input is the timestamp plus exact JSON body; receivers must verify the raw body.
6. Delivery identity is idempotent per subscription/source message.
7. Payloads above the implemented safety bound fail before transport rather than being silently truncated/sent.
8. Current `kingdoms.*` events remain excluded from generic external fan-out.
9. Application endpoint validation does not replace production network/egress controls.

## 5. Workflows

### Create subscription

An authorized manager creates an HTTPS subscription, selects allowed events, and stores the one-time signing material through an appropriate secret-management process.

### Fan out source event

After Platform publishes a tenant outbox event, Integrations considers active subscriptions belonging to that Alliance. It applies selector matching and the explicit public-event eligibility boundary before creating delivery work.

### Deliver

The integration worker sends the stable JSON envelope with delivery/event/timestamp/signature headers and bounded connection/total timeouts.

Any successful HTTP status in the accepted success range completes the delivery. Transport or non-success responses enter persisted retry state.

### Retry/recover

Retries run through the isolated `integrations` queue with bounded attempts/backoff. A periodic recovery command requeues due pending work after worker/process interruption.

## 6. Authorization, tenancy and privacy

First-party subscription administration requires active Alliance context, `alliance.manage`, and recent password confirmation for create/revoke operations.

Fan-out considers only the source event's Alliance and that Alliance's active subscriptions. Event payloads must contain only the externally approved data for that event type.

## 7. Persistence and query semantics

Integrations owns subscription, protected signing material, delivery, attempt, response/error, and retry state. Platform owns the generic source outbox record; producer domains own event semantics/data.

Management diagnostics expose bounded status information without exposing protected signing material.

## 8. Events, integrations and background processing

Webhook delivery is downstream of the shared Platform outbox publisher and runs on the dedicated `integrations` queue.

The current recovery command periodically queues due deliveries. Internal outbox event existence is insufficient to make an event public.

## 9. Failure, idempotency and concurrency

- Duplicate fan-out for the same subscription/source message reuses the logical delivery.
- Job uniqueness prevents routine duplicate concurrent delivery work for the same delivery.
- Retry exhaustion persists failed state rather than spinning indefinitely.
- Revoked subscriptions do not receive new deliveries.
- Endpoint validation is repeated at the appropriate boundary before delivery.
- Oversize payloads fail before transport.

## 10. Operations and observability

Operators should distinguish source outbox publication, event eligibility, subscription matching, delivery queueing, endpoint validation, transport response, retry state, and exhaustion.

Use shared queue/outbox/observability guidance for worker diagnosis. Do not expose signing material to make troubleshooting easier.

## 11. Tests and validation

Tests should cover:

- tenant/source subscription matching;
- wildcard plus external-eligibility behavior;
- stable envelope/signature verification behavior;
- protected signing-material handling;
- payload bounds;
- endpoint safety checks;
- idempotent delivery identity;
- retry/recovery/exhaustion; and
- explicit Kingdoms/public-event exclusions.

## 12. Related documentation

- [Integrations domain](README.md)
- [Read-only API](api.md)
- [Platform transactional outbox](../platform/transactional-outbox.md)
- [Background processing](../../operations/background-processing.md)
- [Security baseline](../../security/security-baseline.md)
