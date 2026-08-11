# Integrations webhook delivery operations

[← Integrations operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Integrations  
**Capability:** Signed outbound webhook delivery  
**Code owner:** `app/Domain/Integrations`

## 1. Scope, prerequisites and safety boundary

Use this runbook when webhook deliveries are backlogged, retrying or permanently failing. Identify the affected subscription/delivery/source outbox IDs, release SHA, scheduler/Horizon state and external dependency symptoms before acting.

Never disable endpoint-safety/signature controls, expose signing secrets, or mark a delivery successful by direct database mutation.

## 2. Runtime and persistent state

Externally eligible outbox messages materialize one durable delivery per subscription/source message. Pending due deliveries are dispatched by `integrations:queue-webhooks --limit=100` to `DeliverWebhookJob` on Redis queue `integrations`.

Delivery rows track status, attempts, `available_at`, response code and bounded error/response diagnostics. Logical delivery identity is stable across safe pending retries.

## 3. Healthy operating flow

1. Producer transaction commits safe outbox intent.
2. `outbox:publish` publishes the source message.
3. Integrations filters external eligibility and same-Alliance subscription match.
4. Delivery row is created/reused.
5. Scheduler dispatches due pending delivery to `integrations`.
6. Horizon worker signs the exact payload and sends HTTPS request.
7. Success marks delivery delivered; retryable failure advances durable retry state; exhausted job budget becomes failed.

## 4. Signals and diagnostics

Check, in order:

- scheduler process and `php artisan schedule:list`;
- Horizon and `integrations` queue depth/failed jobs;
- source outbox publication state;
- subscription status/event selector;
- delivery `status`, `attempts`, `available_at`, response code and `last_error`;
- DNS/egress/endpoint availability and certificate/HTTPS behavior; and
- recent failed-delivery launch-check count.

Use request/trace IDs when the issue originates from a configuration/management request; use delivery/source IDs for background correlation.

## 5. Failure modes and triage

- Source outbox unpublished: diagnose Platform outbox first.
- Pending delivery not queued: scheduler/Redis/Horizon or queue-dispatch failure.
- Transport/DNS/egress exception: fix network/dependency controls; do not broaden endpoint policy.
- Non-success HTTP response: confirm recipient state/contract before retrying.
- Signature complaint: verify recipient uses timestamp + exact raw body and current secret; do not log the secret.
- Permanently failed delivery: current automatic recovery stops here; retain evidence and escalate.

## 6. Recovery, replay and reconciliation

For due pending deliveries after dependency recovery, run:

`php artisan integrations:queue-webhooks --limit=100`

The durable delivery row is reused, so this is the supported catch-up path. Repeat only while a known due backlog remains and queue/receiver capacity is acceptable.

Do not reset a `failed` delivery to pending as routine recovery. A future approved replay process must preserve logical identity/audit evidence and account for possible external side effects.

## 7. Capacity and dependency degradation

Production Horizon defaults reserve a dedicated Integrations supervisor. Queue process counts may be tuned only with measured workload/capacity evidence. The scheduler sweep defaults to 100.

Receiver latency/failure can create backlog even when internal health is green. Monitor queue depth, due-pending age, failure rate and egress/DNS dependency health. Repository staging cannot prove recipient capacity or production firewall/DNS behavior.

## 8. Backup, migration and rollback

Webhook subscription/delivery state is PostgreSQL-backed and queue state is transient Redis work reconstructed from durable pending rows. After database restore, do not assume external receivers forgot previously delivered events; reconcile durable state with incident timing before any replay.

Application rollback follows the shared immutable-image runbook. Database rollback that changes delivery/subscription schemas requires explicit compatibility/data-loss review.

## 9. Stop conditions and prohibited operator actions

Stop and escalate when failures indicate unresolved SSRF/egress policy, a recipient cannot safely tolerate duplicate delivery, a permanently failed row would need manual replay, payload/signature semantics appear inconsistent, or repair would require changing `delivered`/`failed` state directly, deleting source outbox evidence, or exposing secrets.

## 10. Validation and evidence to retain

After recovery verify scheduler/Horizon health, queue backlog decreases, due pending rows progress, successful rows record expected response state, and no duplicate logical delivery row was created.

Retain release SHA, subscription/delivery/source IDs, attempts and timestamps, response code/error class, queue depth before/after, bounded command parameters, dependency incident/change ID and validation result. Never retain signing secrets or unrestricted payloads.
