# Integrations operations profile

[← Integrations domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Integrations  
**Code owner:** `app/Domain/Integrations`  
**Primary operational boundary:** Alliance-bound read API plus durable signed webhook delivery through outbox, Redis/Horizon and external HTTP/DNS/egress

## 1. Operational purpose and runtime shape

Integrations has two distinct runtime paths: synchronous read-only API requests authenticated by Alliance-bound credentials, and asynchronous webhook delivery. Webhook work is materialized from externally eligible outbox events, persisted as delivery rows, queued to Redis/Horizon on `integrations`, and sent to validated HTTPS endpoints.

## 2. Persistent state and ownership

Durable PostgreSQL state includes API credential metadata/verifiers, webhook subscriptions/signing material, delivery status/attempt/availability/response diagnostics and source outbox identity. Producer business data remains owned by the source domain.

## 3. Configuration and runtime dependencies

Dependencies include PostgreSQL, Redis/Horizon, scheduler continuity, HTTPS/DNS/network egress, endpoint availability and protected signing/credential secrets. The shared runtime config validates Redis/queue posture but cannot prove production egress policy or recipient behavior.

## 4. Normal flow and background processing

API requests derive Alliance/scope from the credential and read bounded producer-domain views synchronously. Webhook delivery uses `outbox:publish`, delivery materialization, `integrations:queue-webhooks --limit=100`, and `DeliverWebhookJob` on the `integrations` queue. Pending due deliveries are swept each minute.

## 5. Health, observability and diagnostics

Inspect Horizon `integrations` queue depth, scheduler state, pending due delivery count, delivery attempts/`available_at`, response code, bounded error text, subscription status, source outbox message and recent permanently failed count. API failures use request/trace IDs plus safe credential metadata/status/scope.

## 6. Failure modes and diagnosis

API failures include unknown/revoked/expired credential, insufficient scope, inactive Alliance or dependency failure. Webhook failures include Redis/Horizon outage, scheduler stoppage, endpoint/DNS/egress error, non-success HTTP response, payload/signature issue, revoked subscription or permanently failed delivery.

## 7. Recovery, replay and reconciliation

Restore dependencies first. API requests are retried by clients after the cause is fixed. Pending webhooks can be safely re-queued with the bounded scheduler command because durable delivery identity is reused. The current application does not provide a generic operator replay for permanently failed deliveries; retain evidence and escalate rather than resetting status casually.

## 8. Backup, restore, migration and rollback

Integrations database state is PostgreSQL-backed, while external recipients may already have received payloads and cannot be rolled back by database restore. After restore verify credential/subscription lifecycle and reconcile pending/delivered/failed delivery state without assuming external side effects were undone.

## 9. Capacity, query and performance boundaries

API collections are bounded by contract. Webhook processing uses a dedicated Horizon supervisor and bounded scheduler sweep. Production queue/egress throughput and receiver capacity require external load evidence; increasing workers/batch size is an operational capacity decision.

## 10. External-service degradation

Webhook delivery is specifically expected to experience downstream/DNS/egress degradation and records retryable durable failure state. API consumers are external too, but server-side read availability depends primarily on internal runtime health. Do not weaken SSRF/egress controls to recover delivery.

## 11. Safe operator actions and stop conditions

Safe actions are restore Redis/Horizon/scheduler/egress, inspect durable delivery rows, rerun the pending-delivery sweep and validate progress. Stop if recovery requires exposing secrets, overriding endpoint safety, fabricating delivered state, replaying a permanently failed delivery without approval/idempotency review, or broadening event eligibility.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, safe subscription/delivery/source-message IDs, queue/backlog counts, attempts/timestamps, response code/error class, request/trace IDs and incident/change ID. See [Webhook delivery](webhook-delivery.md), [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), and the [Integrations security profile](../security/README.md).
