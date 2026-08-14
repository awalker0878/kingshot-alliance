# Authorization operations profile

[← Authorization domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Authorization  
**Code owner:** `app/Domain/Authorization`  
**Primary operational boundary:** Alliance/Kingdom-scoped permission state, hierarchy enforcement, R5 leadership safety, and Kingdom assignment recovery

## 1. Operational purpose and runtime shape

Authorization is synchronous database-backed contextual authorization. It owns permission vocabulary, Alliance specialist roles, Kingdom role templates/assignments, and supported assignment/removal behavior. It has no dedicated scheduler or queue worker.

## 2. Persistent state and ownership

Durable state includes Alliance specialist roles/mappings/assignments plus `kingdom_roles`, `kingdom_role_permissions`, and `kingdom_role_assignments` scoped to the exact Kingdom. Membership lifecycle belongs to Memberships; Platform authority remains separate from tenant RBAC.

## 3. Configuration and runtime dependencies

Authorization depends primarily on PostgreSQL plus active tenant context from Alliances. No environment variable should be used to dynamically invent permissions/roles outside the accepted code contract.

## 4. Normal flow and background processing

Requests resolve tenant context and active membership, then evaluate required permission/hierarchy before supported assignment/removal mutations. There is no asynchronous RBAC convergence process.

## 5. Health, observability and diagnostics

Inspect active Alliance/membership, assigned roles, role permissions, target hierarchy, built-in role identity and relevant audit events. Use request/trace correlation for unexpected authorization outcomes.

## 6. Failure modes and diagnosis

Common failures are missing permission, inactive membership, stale Player roster identity, cross-Alliance role/member IDs, cross-Kingdom role assignment, hierarchy denial, attempts to deactivate R5, attempts to remove the final Kingdom Admin, or inconsistent state caused by unsupported direct data changes.

## 7. Recovery, replay and reconciliation

Re-run the supported role assignment/removal only after correcting the underlying authorization/tenant state. Do not bypass R5 leadership or hierarchy checks by direct SQL. If role data is inconsistent, stop and repair through a reviewed migration/forward fix rather than guessing intended privileges.

## 8. Backup, restore, migration and rollback

Authorization state is PostgreSQL-backed. Shared backup/restore restores role and assignment state together with Memberships/Alliances. After recovery, verify one active R5 per populated Alliance, representative rank/specialist permission checks, Kingdom Admin assignments, and cross-Kingdom denial before declaring access control healthy.

## 9. Capacity, query and performance boundaries

RBAC lookups must remain tenant-bounded. The permission vocabulary and built-in roles are intentionally finite. Repository tests enforce behavior but are not a production membership-count capacity benchmark.

## 10. External-service degradation

There is no Authorization-owned external service. PostgreSQL failure causes authorization to fail closed; tenant/session issues surface through Alliances/Identity dependencies.

## 11. Safe operator actions and stop conditions

Safe actions are verifying tenant/membership/role rows, restoring database health and using supported manager flows. Stop if a proposed repair would create an R5 leadership gap, remove the final Kingdom Admin without Platform recovery intent, cross Alliance/Kingdom boundaries, convert Platform status into Event authority, or require editing audit evidence.

## 12. Evidence, focused runbooks and related documentation

Retain request/trace IDs, Alliance/membership/role IDs, before/after role state, denial reason and release SHA. No focused P3 Authorization runbook is required. See [incident response](../../../operations/runbooks/incident-response.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Authorization security profile](../security/README.md).
