# Transactional outbox

[← Platform domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Platform

## 1. Purpose

Defines the shared transactional-outbox infrastructure used by feature domains to persist durable asynchronous event intent in the same transactional boundary as accepted business changes.

Producer domains own event meaning and safe payload semantics. Platform owns generic outbox persistence/publishing infrastructure.

## 2. Scope and non-scope

In scope:

- outbox message persistence;
- deterministic/idempotency keys where supplied by producers;
- post-commit publication scheduling;
- bounded publisher execution;
- publication/error state;
- retry/recovery; and
- downstream publication hooks consumed by Notifications/Integrations and other approved listeners.

Out of scope:

- authorizing the producer business action;
- generic business-event schema ownership;
- making every internal event a public webhook;
- external webhook HTTP transport; and
- replacing domain audit evidence.

## 3. Model and state

An outbox message records the owning tenant when applicable, event type, safe payload, logical/idempotency identity where required, occurrence timestamps, publication state, and bounded error/retry information.

The outbox represents **durable intent to publish after the business transaction**, not proof that an external receiver consumed the event.

## 4. Invariants

1. A business event requiring durable publication is persisted atomically with the accepted state transition.
2. Producer domains own event names and safe payload semantics.
3. Platform outbox infrastructure does not grant authorization or own producer business state.
4. Tenant identity is explicit for tenant-scoped messages.
5. Secrets/private narrative fields are excluded from generic payloads unless explicitly approved.
6. Idempotent producer retries do not create duplicate logical messages when no new business transition occurred.
7. Publisher execution is at-least-once; consumers must remain idempotent.
8. Internal outbox publication does not automatically make an event externally webhook eligible.
9. Audit records and outbox messages remain distinct evidence/coordination concerns.

## 5. Workflows

### Record

The owning domain validates/authorizes the business mutation and, in the same transaction, uses the supported outbox recorder to persist the durable event intent with safe payload and required deterministic identity.

### Publish

The shared publisher claims bounded unpublished messages, publishes them to in-process/application listeners according to the supported implementation, records successful publication time, and records bounded failure state for retryable errors.

### Retry/recovery

After worker/scheduler interruption, the bounded publisher can be rerun. Persisted unpublished state is the source of recovery; operators do not replay the originating business mutation solely to recreate an event.

### Downstream fan-out

Notifications may use publication hooks to advance durable reminder state. Integrations may independently consider externally eligible tenant events for webhook fan-out. Those domains retain their own delivery state and eligibility rules.

## 6. Authorization, tenancy and privacy

The outbox does not authorize anything. The producer domain authorizes first and supplies tenant-safe payload data.

Tenant-scoped messages carry `alliance_id` or the explicit tenant context required by the event contract. Cross-tenant payload construction is a producer defect.

## 7. Persistence and query semantics

Platform owns generic outbox rows and publisher state. Producer domains own any IDs/state referenced in payloads.

Publisher queries are bounded and designed for repeatable catch-up. Consumers use stable message/logical identity to prevent duplicate side effects under at-least-once publication.

## 8. Events, integrations and background processing

The outbox is itself asynchronous infrastructure. The shared publisher runs through the scheduler/queue model documented in repository operations.

External webhooks are a separate Integrations capability with explicit event eligibility, subscriptions, signing, retries, and delivery persistence.

## 9. Failure, idempotency and concurrency

- Transaction rollback removes both the business mutation and its newly recorded outbox intent.
- Publisher failure leaves recoverable unpublished/error state.
- Repeated publisher execution must not corrupt publication state.
- Consumers remain idempotent because publication is at-least-once.
- Deterministic producer identities are reused where the producer's contract defines one logical event per operation.
- Operators repair publisher/consumer failures rather than manually editing source business state.

## 10. Operations and observability

Operators should inspect unpublished count, age/backlog, publication/error state, bounded error detail, event type, tenant identity, and request/trace correlation where available.

Use shared background-processing/observability/runbooks for scheduler, worker, PostgreSQL, Redis, and deployment recovery.

## 11. Tests and validation

Tests should cover:

- atomic business-state + outbox recording;
- rollback behavior;
- deterministic identity where applicable;
- repeated publisher execution;
- at-least-once consumer safety;
- tenant propagation;
- safe payload exclusions; and
- the architecture boundary separating producer semantics, Audit, Notifications, and Integrations.

## 12. Related documentation

- [Platform domain](README.md)
- [Alliance lifecycle and retention](lifecycle-and-retention.md)
- [Audit](../audit/README.md)
- [Notifications](../notifications/README.md)
- [Integrations webhooks](../integrations/webhooks.md)
- [Background processing](../../operations/background-processing.md)
- [Observability](../../operations/observability.md)
