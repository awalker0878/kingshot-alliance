# Events, outbox and jobs

Status: Current

## Business facts vs transport

A context owns its persisted business transition. Shared messaging infrastructure owns the mechanism for making durable asynchronous intent available after commit.

`app/Shared/Infrastructure/Messaging/Outbox` is the generic transactional messaging layer. It is not a business context and must not contain game policy.

## Delivery contract

Outbox publication is at-least-once. Jobs and consumers must therefore be safe to retry and use stable idempotency/deduplication identifiers where duplicate effects would be harmful.

## Queue partitions

Hosted queues use Redis/Horizon. Core work, notifications, integrations and maintenance should remain operationally separable so one retry storm cannot consume all worker capacity.

## Scheduling

Console/scheduler registration lives under `routes/console.php`. Scheduled work that can overlap unsafely should use appropriate overlap/single-server controls.

## Ownership examples

- Operations reminder rule/schedule: Operations.
- Reminder delivery attempt: Communications.
- Webhook subscription/delivery administration: Platform Integrations.
- Audit/outbox mechanics: Shared infrastructure.