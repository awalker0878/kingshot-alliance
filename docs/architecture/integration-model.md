# Integration model

Status: Current

Integration is divided into **internal context collaboration**, **asynchronous infrastructure**, and **external platform integrations**.

## Internal collaboration

Preferred mechanisms, from tightest to loosest:

1. small synchronous application/query contract when an immediate fact is required;
2. explicit `app/Workflows` orchestration when one user intent spans several write owners;
3. durable outbox/event publication for asynchronous reactions;
4. `app/ReadModels` for read-only composition.

Direct cross-context persistence mutation is not a supported integration mechanism.

## Transactional outbox

Business state and required outbox records are committed atomically. Publication is at-least-once, so downstream consumers and delivery handlers must use stable idempotency/deduplication semantics.

Generic outbox infrastructure belongs under `app/Shared/Infrastructure/Messaging/Outbox`, not inside a business context.

## Communications

The source context owns **why and when** a communication should exist. Communications owns **how delivery is coordinated**, including attempts, retry/idempotency, recipient preferences and channel state.

## External integrations

Platform owns API credential and webhook administration. External access must be scoped/revocable, webhook delivery signed and retryable, and endpoint handling must respect network/SSRF controls. External contracts are not automatically equivalent to internal domain/outbox event representations.