# Observability

[← Operations documentation](README.md)

## Purpose

This guide describes the observability signals that exist in the current runtime and how operators should use them. It distinguishes implemented repository behavior from deployment-specific monitoring and from broader architecture intentions in [ADR 0006](../adr/0006-observability-and-correlation.md).

Do not claim a production alert, dashboard, telemetry exporter, or support process exists merely because the application exposes the underlying signal.

## Current signal inventory

| Signal | Current implementation | Operator use |
| --- | --- | --- |
| Liveness | `GET /up` | Proves the web/application process can answer the framework health route. It does not prove PostgreSQL/Redis readiness. |
| Readiness | `GET /health/ready` | Checks PostgreSQL with `select 1` and performs a write/read/delete cache probe. Returns `200` with `status=ready` or `503` with `status=not_ready`. |
| Request ID | `X-Request-ID` UUID | Correlate a client-visible response with application logs and audit events. Invalid/missing incoming IDs are replaced with a generated UUID. |
| Trace context | W3C `traceparent` | Correlate a request with an upstream trace ID. A valid incoming trace ID is preserved while the application creates a new parent/span ID; otherwise a new trace context is generated. |
| HTTP request logs | `http.request.completed` / `http.request.failed` | Observe route, method, duration, response status for completed requests, and exception class for failures. |
| Audit events | Persistent audit records | Correlate privileged/business changes by alliance, actor, subject, request ID, and trace ID. |
| Horizon | Laravel Horizon worker/queue view | Inspect worker state, queue backlogs, throughput/failures for Redis-backed queues. Access is restricted to active platform administrators with verified email and confirmed MFA. |
| Launch health | `php artisan app:launch-check --json` | Snapshot repository-controlled launch signals: config validity, platform-admin redundancy/MFA, tenant defaults, overdue outbox, failed jobs, and recent webhook failures. |
| Deployment identity | Immutable image digest + OCI version/revision + container env | Identify the exact deployed artifact. `bin/deploy` verifies all runtime roles use the expected image ID/version/release SHA. |
| Pulse | Package present but hosted recording disabled | Do not rely on Pulse for current hosted operations. `PULSE_ENABLED` must remain false until schema/access policy is introduced. |

## Liveness versus readiness

Use the endpoints for different questions.

### `GET /up`

`/up` is liveness. A successful response means the application HTTP stack is alive enough to answer the framework health route.

Do **not** use `/up` alone as a deployment or traffic-readiness gate.

### `GET /health/ready`

`/health/ready` is dependency readiness. It currently verifies:

- PostgreSQL can execute `select 1`; and
- the configured cache can write, read, and delete a short-lived probe key.

The response contains the current request ID:

```json
{
  "status": "ready",
  "request_id": "<uuid>"
}
```

A dependency failure returns HTTP `503` with `status` set to `not_ready`. The underlying exception is reported through the application exception/logging path.

Readiness currently does **not** actively test S3/media access, SMTP delivery, webhook egress, DNS, backup services, external certificates, or downstream webhook endpoints. Those require separate deployment-level monitoring/evidence.

## Request correlation

Every HTTP request passes through `AssignRequestContext`.

### Request IDs

- An incoming `X-Request-ID` is accepted only when it is a valid UUID.
- Missing or invalid values are replaced with a generated UUID.
- The value is added to Laravel log context as `request_id`.
- The response returns the effective value in `X-Request-ID`.

When a user reports an application error, the response `X-Request-ID` is the preferred first correlation key.

### W3C trace context

- A valid version-00 W3C `traceparent` is accepted when it contains non-zero trace and parent IDs.
- The existing trace ID and flags are preserved.
- The application creates a new parent/span ID for the request.
- Missing/invalid input creates a new sampled (`01`) trace context.
- The response returns the effective `traceparent` value.
- `trace_id` is added to Laravel log context and persisted on audit events created during the request.

The repository does not currently configure an OpenTelemetry exporter. The trace headers/IDs are therefore correlation primitives that deployment telemetry may integrate with; they are not proof that distributed traces are being exported anywhere.

## HTTP request logs

The container-oriented logging baseline is JSON to standard error. The reference hosted configuration uses:

```text
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info
```

`RecordRequestMetrics` emits:

- `http.request.completed` with request method, named route (or `unmatched`), duration in milliseconds, and HTTP status; or
- `http.request.failed` with method, route, duration, and exception class before the exception is rethrown.

`request_id` and `trace_id` are inherited from the logging context established earlier in the middleware pipeline.

### Current correlation boundary

ADR 0006 describes a broader target in which logs include release SHA and tenant identifiers. The current implementation does **not** globally inject `RELEASE_SHA`, `alliance_id`, or actor identity into every application log record.

Current reliable sources are:

- request/trace IDs in HTTP logs;
- request/trace/alliance/actor/subject context in persistent audit events where an audited action is recorded;
- immutable image/container metadata for release SHA and version; and
- domain-specific durable rows for outbox/webhook/reminder/report processing.

Do not tell an incident responder to query a global `release_sha` or `alliance_id` JSON-log field unless the deployed logging pipeline has added that enrichment outside the repository or the application has subsequently implemented it.

## Audit correlation

`AuditRecorder` persists, when available:

- `alliance_id`;
- `actor_user_id`;
- event name;
- subject type/ID;
- bounded event-specific metadata;
- `request_id`; and
- `trace_id`.

Audit records are the durable attribution source for privileged/business mutations. They are not a substitute for performance/availability logs, and operators should not copy sensitive audit payloads into tickets or public chat channels.

## Queue and scheduler observability

Use [Background processing](background-processing.md) for command ownership and recovery procedures.

For queue-backed work, inspect:

- Horizon process/supervisor state;
- queue depth by queue (`default`, `notifications`, `integrations`, `maintenance`);
- failed-job count;
- job failure exception/log context; and
- the durable business record that generated the work.

For scheduler-owned work, inspect:

- scheduler process state;
- expected command cadence (`php artisan schedule:list`);
- command/log errors; and
- the durable database state that should have advanced.

Do not infer scheduler health from Horizon health or vice versa.

## Outbox observability

The transactional outbox is a critical platform signal.

Inspect:

- count of unpublished messages;
- oldest unpublished `available_at` / `occurred_at` age;
- per-message `attempts` and `last_error`;
- whether `outbox:publish` is running every minute; and
- whether a repeatedly failing consumer is blocking publication.

`app:launch-check` considers an unpublished message overdue after `LAUNCH_OUTBOX_GRACE_MINUTES` (default 15) and compares the count with `LAUNCH_MAXIMUM_OVERDUE_OUTBOX` (default 0).

A growing outbox backlog can affect reminder state, recruitment follow-on processing, and webhook fan-out even when HTTP traffic remains healthy.

## Webhook observability

For integrations, monitor both queue and durable delivery state:

- `integrations` queue depth;
- pending deliveries whose `available_at` is due;
- delivery `attempts`;
- HTTP response code;
- bounded response excerpt / `last_error`;
- delivered/failed state; and
- recent permanently failed-delivery count.

The launch gate looks back `LAUNCH_WEBHOOK_FAILURE_WINDOW_MINUTES` (default 60) and permits at most `LAUNCH_MAXIMUM_RECENT_WEBHOOK_FAILURES` (default 25).

This threshold is intentionally different from an availability SLO. Production should define its own alert thresholds based on expected webhook volume and support commitments.

## Database, Redis, and storage signals

At minimum, production monitoring should cover:

- PostgreSQL availability, connection saturation, transaction/lock pressure, storage growth, backup success, and replication/recovery signals where applicable;
- Redis availability, memory/eviction policy, persistence health, connection saturation, and queue/cache/session impact;
- private object-storage availability, capacity, backup/versioning/recovery controls appropriate to the provider; and
- host/container CPU, memory, filesystem/storage, restart count, and process health for app/web/worker/scheduler roles.

The repository exposes some dependency symptoms through readiness and application failures, but it does not define provider-specific dashboards or alerts.

## Release observability

Every deployed runtime role should be attributable to one immutable release.

Retain:

- source commit SHA;
- image digest;
- image ID;
- OCI application version;
- OCI revision/release SHA;
- deployment/change record identifier; and
- start/end of the stabilization window.

`bin/deploy` verifies the application, web, worker, and scheduler containers use the expected image ID and that their `APP_VERSION`/`RELEASE_SHA` match immutable image metadata.

During the stabilization window, observe at least:

- HTTP error rate;
- latency distribution/trend;
- readiness failures;
- scheduler continuity;
- queue depth/worker failures;
- outbox backlog;
- webhook failures;
- database/Redis health; and
- storage/capacity signals.

If a release-specific signal regresses beyond the approved stop condition, use the [rollback runbook](runbooks/rollback.md).

## Recommended production alert categories

The repository does not prescribe vendor-specific alert rules, but production launch evidence should assign an owner/escalation path for at least:

1. external availability and readiness;
2. elevated HTTP 5xx/error rate;
3. latency degradation;
4. scheduler absent/stalled;
5. Horizon worker absent or queue backlog growth;
6. failed jobs above operational threshold;
7. overdue outbox backlog;
8. webhook failure/backlog growth;
9. PostgreSQL availability/capacity/backup health;
10. Redis availability/memory/persistence health;
11. object-storage availability/capacity/backup health; and
12. container/host resource exhaustion or restart loops.

Alert destinations, paging schedules, personnel names, private endpoints, and provider credentials belong in the approved operational system, not this repository.

## Investigation workflow

For an HTTP incident:

1. Capture timestamp, affected route/workflow, user-visible error, and `X-Request-ID` if available.
2. Search JSON logs by `request_id`; use `trace_id` to group related request context.
3. Check `/up` and `/health/ready` independently.
4. Identify the deployed image digest/version/release SHA from deployment/container metadata.
5. If the action is auditable, correlate the request/trace ID with persistent audit events.
6. If asynchronous follow-on work is involved, inspect the corresponding durable outbox/delivery/report/reminder row and scheduler/Horizon state.
7. Determine whether impact is application, dependency, queue/scheduler, integration egress, or deployment specific.
8. Follow the [incident response runbook](runbooks/incident-response.md) and preserve evidence before destructive cleanup.

## Privacy and cardinality rules

Logs and telemetry must not contain:

- passwords, session values, API credentials, webhook signing secrets, application keys, or provider secrets;
- full sensitive recruitment answers/notes or private content payloads;
- unrestricted request/response bodies;
- unbounded user-controlled strings as metric dimensions; or
- secret-bearing URLs/headers.

Prefer stable low-cardinality dimensions such as named route, status class, queue name, environment, and release identifier. Use request/trace IDs for high-cardinality incident correlation rather than as metric labels.

## Evidence boundary

Repository CI can prove health endpoints, correlation headers, code-level logging behavior, configuration checks, image identity, staging boot, queue configuration, and recovery tooling. It cannot prove that production logs are retained, alerts page the correct people, an external telemetry platform is configured, provider metrics are collected, or on-call coverage exists.

Those remain external production evidence and must stay **pending** until verified in the production environment.