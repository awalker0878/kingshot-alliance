# Audit interfaces

[← Audit domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Audit  
**Code owner:** `app/Domain/Audit`  
**Primary boundary:** Internal append-oriented audit evidence recording consumed by feature domains  
**P4 inventory decision:** Profile only

## 1. Boundary purpose and ownership

Audit owns the supported in-process contract for recording attributable security/business audit evidence. Its material interface is internal: feature domains call the Audit recording service when a transition requires durable human/system attribution and context.

Audit evidence is not the same contract as Platform transactional-outbox events. The two may be recorded for the same business transition for different purposes.

## 2. Surface inventory

Audit currently exposes no direct public, member, manager, Platform-administrator, or external-machine HTTP route. Its material boundary is the internal audit recorder/model contract consumed by domain actions.

Audit records may later be surfaced through approved administrative queries, but P4 found no standalone external Audit API/export route in the current runtime.

## 3. Callers, authorization and tenancy

Owning feature actions perform their own authorization and tenant checks before recording evidence. Audit accepts the already-authorized actor/subject/Alliance context supplied by the producer and persists attribution; it does not independently grant the producer authority to perform the business mutation.

Tenant-scoped audit records retain Alliance attribution when the source transition belongs to an Alliance. Global/cross-tenant evidence is recorded only where the producer contract legitimately operates outside Alliance tenancy.

## 4. Input and validation contracts

The supported recorder contract takes a stable event/action name, actor where applicable, subject/aggregate, optional Alliance context, and a deliberately minimized metadata payload.

Producers must send audit-safe metadata rather than secrets, credentials, recovery material, arbitrary private persistence snapshots, or unbounded request bodies. Domain-specific security profiles govern which fields are safe to include.

## 5. Output and disclosure contracts

Recording returns/persists audit evidence for later diagnostics/governance. Routine feature callers should treat successful append as evidence creation, not as a transport mechanism or a way to pass data to another domain.

There is no current public schema promising all audit metadata to end users or external integrators.

## 6. Internal actions, queries and services

`AuditRecorder` is the primary supported cross-domain service. Feature domains invoke it from authorized actions rather than directly constructing audit rows.

Audit's persistence/query internals remain private unless a dedicated supported query contract is introduced. Consumers must not couple business behavior to undocumented audit-table shape.

## 7. Events, outbox and cross-domain consumers

Audit records and Platform outbox messages are independent durable artifacts. An audit event is not automatically an outbox event, domain event, webhook, notification, or job trigger.

Where a business action creates both audit and outbox evidence, producer-domain documentation defines the business meaning while Audit owns evidence semantics and Platform owns publication mechanics.

## 8. Commands, jobs and scheduled work

Audit has no current domain-specific command, queue job, or scheduler entry in `routes/console.php`.

Retention/redaction coordination may be invoked by Platform lifecycle/retention orchestration, but that does not create an Audit-owned scheduler surface.

## 9. Files, imports, exports and external dependencies

There is no current direct Audit import/export file contract. Audit persistence depends on PostgreSQL and the request/application attribution context supplied by callers.

Operational retention/recovery boundaries are documented in [Audit operations](../operations/README.md).

## 10. Failure, idempotency, versioning and compatibility

Callers must not treat failure to produce a secondary external effect as permission to rewrite audit history. Append/evidence semantics and any redaction/retention behavior follow the Audit/Platform contracts rather than direct row mutation.

Stable action/event names are compatibility-relevant evidence identifiers. Renaming a material audit event requires coordinated documentation/tests/evidence-consumer review.

## 11. Explicit non-capabilities

Audit does not:

- authorize feature-domain business actions;
- provide a public audit API or anonymous export;
- act as a message bus;
- make every audit event an outbox event or webhook;
- accept secrets/recovery credentials as routine metadata; or
- transfer ownership of producer-domain facts into Audit.

## 12. Focused contracts, evidence and related documentation

No new focused P4 interface contract is required because Audit's material boundary is one coherent internal recorder/evidence contract.

Related documentation:

- [Audit domain contract](../README.md)
- [Audit security](../security/README.md)
- [Audit operations](../operations/README.md)
- [Platform transactional outbox](../../platform/transactional-outbox.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
