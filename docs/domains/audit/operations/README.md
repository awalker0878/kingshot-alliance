# Audit operations profile

[← Audit domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Audit  
**Code owner:** `app/Domain/Audit`  
**Primary operational boundary:** append-oriented privileged/business evidence with tenant, actor, subject and request/trace correlation

## 1. Operational purpose and runtime shape

Audit records durable evidence synchronously with supported application actions. It does not own a scheduler, queue worker, or external transport.

## 2. Persistent state and ownership

Primary state is persistent audit events with bounded metadata, Alliance/actor/subject context and request/trace identifiers where available. Audit is evidence, not business state and not the transactional outbox.

## 3. Configuration and runtime dependencies

Audit depends on PostgreSQL and request correlation supplied by the shared HTTP middleware. Shared logging configuration affects investigation but is not the source of durable audit truth.

## 4. Normal flow and background processing

Supported privileged/business mutations call the Audit contract after authorization and within the intended transaction boundary. There is no background replay process that reconstructs missing audit events later.

## 5. Health, observability and diagnostics

Diagnose with event name, Alliance/actor/subject IDs, request ID, trace ID, timestamps and the originating business record. Use shared JSON logs for surrounding failure context.

## 6. Failure modes and diagnosis

Treat inability to record required audit evidence as a mutation-integrity problem where the owning workflow requires atomic evidence. Distinguish absent optional context from an actually missing required audit event.

## 7. Recovery, replay and reconciliation

Do not fabricate historical audit events from memory or manually edit evidence. Recover the underlying database/runtime cause. If a business transaction rolled back, no synthetic audit repair is needed; if an accepted workflow explicitly committed without required evidence, escalate as an application defect.

## 8. Backup, restore, migration and rollback

Audit state is PostgreSQL-backed and recovered with the shared database procedure. After restore, verify representative recent audit rows and correlation fields. Schema rollback must preserve referential/evidence integrity and follows reviewed migration semantics rather than ad hoc deletion.

## 9. Capacity, query and performance boundaries

Audit is append-oriented and can grow continuously. Production must monitor database/storage growth and query/index behavior. Repository fixtures do not establish retention capacity or production query SLOs.

## 10. External-service degradation

Audit has no direct external dependency. PostgreSQL unavailability prevents durable recording; external log/telemetry loss must not be mistaken for loss of persistent audit rows.

## 11. Safe operator actions and stop conditions

Safe actions are restoring PostgreSQL, correlating by immutable IDs, and retaining relevant rows during an incident. Stop if diagnosis would require deleting/replacing evidence, exposing sensitive metadata broadly, or treating Audit as a repair mechanism for business state.

## 12. Evidence, focused runbooks and related documentation

Retain event IDs/names, request/trace IDs, affected business identifiers, timestamps and release SHA. No focused P3 Audit runbook is required. See [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Audit security profile](../security/README.md).
