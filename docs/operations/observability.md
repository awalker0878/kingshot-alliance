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