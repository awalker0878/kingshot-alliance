# ADR 0004 — Redis queues and transactional outbox

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0 design; Phase 1 implementation

## Context

Notifications, reminders, exports, integrations, and audit side effects must be reliable and safe to retry without holding user requests open.

## Decision

Use Laravel queues on Redis, operated through Horizon. Business state and an outbox record are committed in one PostgreSQL transaction. A dispatcher publishes pending outbox records to idempotent jobs. Jobs carry alliance context, correlation identifiers, a stable idempotency key, retry policy, and dead-letter visibility.

## Consequences

The design avoids dual-write loss and supports replay, but adds outbox lifecycle and idempotency responsibilities.

## Validation

Integration tests will simulate transaction rollback, duplicate delivery, worker failure, retry, and delayed publication.
