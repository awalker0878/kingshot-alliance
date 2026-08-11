# Alliances operations profile

[← Alliances domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Alliances  
**Code owner:** `app/Domain/Alliances`  
**Primary operational boundary:** Alliance lifecycle plus request-time active-Alliance resolution and tenant-context propagation

## 1. Operational purpose and runtime shape

Alliances is synchronous request-path state. It owns Alliance lifecycle/settings and resolves the active Alliance for tenant-scoped requests. It adds no dedicated scheduler command or queue worker.

## 2. Persistent state and ownership

Primary durable state is the Alliance record and lifecycle/configuration fields owned by Alliances. Membership state is owned by Memberships; permission state by Authorization. Selected active-Alliance state is session state and must be revalidated against current durable Alliance/membership state on protected requests.

## 3. Configuration and runtime dependencies

Required shared dependencies are PostgreSQL and the configured session/cache runtime. Hosted deployments use Redis for sessions/cache. Tenant-context propagation to queued/export/cache/storage work uses explicit snapshots/helpers rather than process-global state.

## 4. Normal flow and background processing

A protected request resolves the selected Alliance, verifies it is active and the User still has active membership, then exposes request-scoped tenant context to downstream authorization/domain queries. There is no scheduled Alliances catch-up command.

## 5. Health, observability and diagnostics

Use request/trace IDs, audit events for Alliance lifecycle/settings changes, session selection state, Alliance lifecycle state, and Memberships state. Shared readiness verifies PostgreSQL/cache availability but not semantic tenant selection.

## 6. Failure modes and diagnosis

Common failures are missing selection, stale session selection, suspended/closed Alliance, removed/inactive membership, cross-Alliance identifier rejection, or unavailable PostgreSQL/Redis. Distinguish tenant-resolution denial from permission denial before changing roles or data.

## 7. Recovery, replay and reconciliation

Clear/reselect the active Alliance through supported UI/session flow after restoring dependency health. Repeated context resolution is safe and creates no business state. Do not repair stale context by editing Alliance/membership IDs in session storage or database rows.

## 8. Backup, restore, migration and rollback

Alliance durable state is PostgreSQL-backed and follows the shared [backup/restore](../../../operations/runbooks/backup-restore.md) and [rollback](../../../operations/runbooks/rollback.md) procedures. After restore, verify Alliance lifecycle/settings and representative active-member tenant resolution before declaring recovery.

## 9. Capacity, query and performance boundaries

Tenant resolution should remain bounded request-time lookup work. Avoid introducing per-feature scans or process-global tenant caches. Repository tests are regression evidence, not a production tenant-count capacity claim.

## 10. External-service degradation

There is no Alliances-owned external service. Redis/session loss can sign users out or invalidate selection; PostgreSQL loss makes tenant resolution fail closed. Downstream storage/integrations remain owned by their domains.

## 11. Safe operator actions and stop conditions

Safe actions are restoring PostgreSQL/Redis, verifying lifecycle/membership state, and having the user reselect context. Stop and escalate if recovery would require bypassing membership, forcing an inactive Alliance active, crossing tenants, or rewriting audit evidence.

## 12. Evidence, focused runbooks and related documentation

Retain request/trace IDs, affected Alliance/membership IDs, lifecycle state, dependency health and release SHA for incidents. No focused P3 Alliances runbook is required. See [observability](../../../operations/observability.md), [incident response](../../../operations/runbooks/incident-response.md), and the [Alliances security profile](../security/README.md).
