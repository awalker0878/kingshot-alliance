# Platform operations profile

[← Platform domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Platform  
**Code owner:** `app/Domain/Platform`  
**Primary operational boundary:** shared outbox/runtime governance plus cross-tenant lifecycle, usage, retention, legal-hold and administrative recovery controls

## 1. Operational purpose and runtime shape

Platform owns shared operational control paths that can affect every domain: the transactional outbox, hosted runtime/launch checks, usage snapshots, account deletion processing, retention enforcement, legal holds and cross-tenant lifecycle/export orchestration.

## 2. Persistent state and ownership

Durable PostgreSQL state includes outbox messages, Platform administrator grants/configuration state, usage snapshots, lifecycle/retention deadlines, legal holds, account deletion requests and administrative export/evidence metadata. Feature domains retain semantic ownership of their business rows.

## 3. Configuration and runtime dependencies

Platform depends on PostgreSQL, Redis/cache/queue, scheduler continuity, Horizon for queued consumers, hosted configuration validation, immutable image identity and shared backup/restore tooling. `app:config-check` and `app:launch-check --json` are repository-controlled operational gates.

## 4. Normal flow and background processing

Scheduled work includes `outbox:publish --limit=100` every minute, `platform:process-account-deletions --limit=100` hourly, `platform:capture-usage --limit=2000` hourly and `platform:enforce-retention` daily at 03:45, plus framework queue pruning. Platform lifecycle actions remain explicit high-assurance administrator operations.

## 5. Health, observability and diagnostics

Use readiness/liveness, launch-check output, scheduler/Horizon state, outbox backlog/attempt/error age, failed-job count, lifecycle/request/hold/deadline state, usage snapshot age, retention-target counts, audit evidence and immutable release/image identity.

## 6. Failure modes and diagnosis

Primary failures are scheduler/Redis/PostgreSQL outage, outbox consumer exception/backlog, blocked account deletion, legal hold, lifecycle precondition failure, retention backlog, missing usage snapshot, oversized/ineligible administrative export, or hosted configuration/launch-check failure.

## 7. Recovery, replay and reconciliation

Use durable state as authority. Restore dependencies, rerun the single bounded owning command and verify persisted state advanced. Outbox publication is at-least-once and consumers must be idempotent. Destructive lifecycle/retention paths must re-evaluate holds/deadlines/ownership on every attempt.

## 8. Backup, restore, migration and rollback

Platform state is PostgreSQL-backed, but production recovery also depends on runtime secrets/configuration and feature-domain external storage. The shared backup/restore and rollback runbooks remain authoritative. After restore reconcile outbox, deletion/hold/retention and usage state before enabling aggressive catch-up.

## 9. Capacity, query and performance boundaries

Scheduled limits are bounded (`100`, `2000`, etc.). Launch thresholds are readiness gates, not production SLOs. Outbox/usage/retention tables can grow materially and require database capacity/maintenance evidence. Catch-up batches must account for lock/query/downstream consumer pressure.

## 10. External-service degradation

Platform itself does not make generic external calls for most scheduled work, but downstream outbox consumers may depend on Integrations/Notifications and production database/Redis/storage providers. A healthy Platform source transaction does not prove every consumer/external service completed.

## 11. Safe operator actions and stop conditions

Safe actions are restore dependencies, inspect durable rows, rerun bounded commands and preserve evidence. Stop if recovery would bypass legal holds, ownership/admin assurance, retention state, tenant boundaries, direct-mark outbox success, delete audit evidence, or perform destructive SQL as a shortcut.

## 12. Evidence, focused runbooks and related documentation

Retain release/image identity, launch-check output, command/limit/timestamps, outbox backlog/attempt/error counts, lifecycle/hold/request IDs, usage/retention counts, backup/change/incident IDs and validation outcomes.

Focused P3 runbooks:

- [Transactional outbox](transactional-outbox.md)
- [Lifecycle and retention](lifecycle-retention.md)

See [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Platform security profile](../security/README.md).
