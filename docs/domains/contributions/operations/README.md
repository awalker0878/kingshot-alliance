# Contributions operations profile

[← Contributions domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary operational boundary:** contribution/report persistence, Event-derived reconciliation, correction/reversal integrity, and scheduled-report source data

## 1. Operational purpose and runtime shape

Contributions owns synchronous contribution/reporting state and Event reconciliation semantics. Recurring report coordination is operated by Notifications through `contributions:queue-reports`; Contributions supplies authoritative source/provenance data rather than a separate queue worker.

## 2. Persistent state and ownership

Durable state includes contribution records, evidence/provenance, correction/reversal relationships and report schedule/source data owned by Contributions. Event attendance facts are consumed from Events; scheduled delivery/run coordination is owned by Notifications.

## 3. Configuration and runtime dependencies

Primary dependency is PostgreSQL. Scheduled report production also depends indirectly on scheduler continuity and the shared outbox/Notifications path. No Contributions-specific external service is required for core persistence.

## 4. Normal flow and background processing

Contribution mutations are synchronous and preserve provenance. Event reconciliation derives/reconciles contribution facts from accepted Event state. Due report schedules are discovered by the recurring Notifications/Contributions command and materialized with deterministic due-time/report-version identity.

## 5. Health, observability and diagnostics

Inspect contribution status, correction/reversal links, source/provenance fields, Event IDs/attendance source state, report schedule due time, and downstream report-run/outbox state. Use request/trace/audit correlation for manager mutations.

## 6. Failure modes and diagnosis

Typical issues are invalid source Event state, duplicate/replayed reconciliation attempts, inconsistent correction/reversal chain, overdue report schedules, missing expected report run/outbox request, or PostgreSQL failure. Diagnose source facts before editing derived/report state.

## 7. Recovery, replay and reconciliation

Re-run supported Event reconciliation or the shared bounded report-queue command only after source state is correct. Existing logical report runs are reused by deterministic identity. Never repair by deleting/recreating contribution history or fabricating report provenance.

## 8. Backup, restore, migration and rollback

Contributions state is PostgreSQL-backed. Shared restore recovers Contributions together with Events/Notifications/outbox state. After restore verify representative contribution provenance/corrections and any due report schedule/run linkage before declaring recovery.

## 9. Capacity, query and performance boundaries

Reporting/export queries must remain tenant-bounded and explainable. Scheduled report catch-up uses the shared default limit of 50. Repository fixtures/query assertions are regression gates, not production report-volume capacity claims.

## 10. External-service degradation

Core Contributions has no direct external dependency. Downstream delivery/notification degradation belongs to Notifications/Integrations; Contributions source state should remain durable while that path recovers.

## 11. Safe operator actions and stop conditions

Safe actions are validating Event/provenance state, rerunning supported reconciliation, and using the shared scheduled-delivery recovery path. Stop if a repair would overwrite history, cross tenants, destroy correction/reversal evidence, or mark downstream delivery successful manually.

## 12. Evidence, focused runbooks and related documentation

Retain contribution/report schedule/run IDs, source Event IDs, due/report-version identity, request/trace IDs, before/after status counts and release SHA. No focused P3 Contributions runbook is required; scheduled operations are covered by [Notifications scheduled delivery](../../notifications/operations/scheduled-delivery.md). See [background processing](../../../operations/background-processing.md) and the [Contributions security profile](../security/README.md).
