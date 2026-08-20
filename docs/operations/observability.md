# Observability

Status: Current

The application uses structured request/runtime correlation and health endpoints to make failures diagnosable without exposing sensitive data.

## Correlation

Requests receive a request identifier and W3C `traceparent` correlation header. Logs and asynchronous work should preserve stable correlation/actor/scope fields where useful.

## Logging

Prefer structured fields over interpolated prose. Do not log passwords, access tokens, signing secrets, application keys, MFA secrets/recovery codes, private message bodies or unnecessary personal data.

Useful bounded fields include route name, request ID, trace ID, context/capability, action, actor identifiers appropriate to the event, concrete scope and outcome. Avoid uncontrolled high-cardinality labels such as raw arbitrary URL paths.

## Metrics and health

Liveness and readiness are separate. Alerts should correspond to a named owner and runbook. Readiness responses should expose aggregate health rather than database/cache credentials or topology details.

## Queues

Monitor queue depth, failed jobs, retry rates and outbox publication lag separately for core, communications/integration and maintenance work where operationally partitioned.

Webhook diagnostics retain bounded response codes, attempts and safe error summaries. Broadcast outcome events retain channel/status/attempt/retryability only. Signing-secret rotation is audited and immediate; operators must update consumers from the one-time replacement before relying on subsequent deliveries.

The Citadel diagnostic projection lists at most 25 recent failures per outbox, webhook, notification and queue-job source. It displays a 16-character SHA-256 error fingerprint instead of exception or response text. Operators can search one validated request UUID or 32-character trace ID and receive at most 50 ordered audit records without audit metadata. This surface is for correlation and recovery; protected server logs remain the source for exception detail.

Operator outbox release is restricted to failed unpublished messages, rate-limited by the route, password-confirmed, audited and idempotency-preserving. Automatic outbox work stops at the configured maximum attempts instead of retrying poison messages forever. See [ADR-0008](../architecture/adr/0008-bounded-operator-recovery.md).

## Production asset budgets

`npm run check:performance-budgets` reads the Vite manifest after a production build and fails CI when any budget in `/performance-budgets.json` is exceeded. The current raw-byte ceilings are:

| Surface | Budget | Purpose |
| --- | ---: | --- |
| Initial JavaScript graph | 225 KiB | Bound framework, boot and localization code required before a page is selected. |
| Application entry | 20 KiB | Prevent feature code from leaking into boot logic. |
| Largest lazy page chunk | 72 KiB | Keep a single workflow from becoming an unbounded download. |
| Largest stylesheet | 100 KiB | Bound global and page-specific CSS output. |

Budgets are ceilings, not targets. Reduce or split the responsible code when a gate fails. A budget increase requires a reviewed reason, before/after measurements and an update to this table in the same pull request.

`php artisan app:launch-check --json` remains the actionable runtime gate for production configuration, administrator redundancy/MFA, Alliance defaults, overdue outbox messages, failed jobs and recent exhausted webhook deliveries. Runtime thresholds live in `config/operations.php`; alerts should link to the incident, deployment, or recovery runbook that owns the response.
