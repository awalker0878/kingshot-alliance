# ADR 0006 — Structured observability and correlation

- **Status:** Accepted
- **Date:** 2026-08-06
- **Related phase:** Phase 0

## Context

Operational support requires a request to be traced across HTTP, logs, queues, and integrations without exposing sensitive data.

## Decision

Emit JSON logs to standard error. Every request receives or validates a UUID request ID and a W3C `traceparent`. Logs include request ID, trace ID, route, status, duration, release SHA, and tenant identifiers when available. Queued work propagates the same context.

Use separate liveness and readiness endpoints. Laravel Pulse and Horizon provide application and queue views; production telemetry may export through OpenTelemetry-compatible infrastructure.

## Consequences

Incidents can be investigated consistently. Developers must maintain context propagation and cardinality controls.

## Validation

Tests assert response correlation headers. Operational smoke tests verify logs, health endpoints, worker metrics, and release metadata.
