# ADR 0005: Transactional outbox and at-least-once delivery

Status: Accepted

## Decision

Persist required outbox records atomically with the owning business transaction and publish asynchronously with at-least-once semantics.

Generic outbox infrastructure lives under `app/Shared/Infrastructure/Messaging/Outbox`.

## Rationale

Publishing directly before commit can expose events for rolled-back state; publishing only after commit without durable intent can lose side effects. The outbox closes that gap.

## Consequences

Consumers, notifications and webhook delivery must be retry-safe and idempotent. Publication attempts require bounded retry/backoff and operational observability.