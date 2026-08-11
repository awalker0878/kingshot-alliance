# Authorization operations profile

[← Authorization domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Authorization  
**Code owner:** `app/Domain/Authorization`  
**Primary operational boundary:** tenant-scoped role/permission state, hierarchy enforcement, and last-Owner safety

## 1. Operational purpose and runtime shape

Authorization is synchronous database-backed RBAC. It owns permission vocabulary, role templates and membership-role assignment/removal behavior. It has no dedicated scheduler or queue worker.

## 2. Persistent state and ownership

Durable state includes roles, role-permission mappings and membership-role assignments scoped to the owning Alliance. Membership lifecycle belongs to Memberships; Platform authority remains separate from tenant RBAC.

## 3. Configuration and runtime dependencies

Authorization depends primarily on PostgreSQL plus active tenant context from Alliances. No environment variable should be used to dynamically invent permissions/roles outside the accepted code contract.

## 4. Normal flow and background processing

Requests resolve tenant context and active membership, then evaluate required permission/hierarchy before supported assignment/removal mutations. There is no asynchronous RBAC convergence process.

## 5. Health, observability and diagnostics

Inspect active Alliance/membership, assigned roles, role permissions, target hierarchy, built-in role identity and relevant audit events. Use request/trace correlation for unexpected authorization outcomes.

## 6. Failure modes and diagnosis

Common failures are missing permission, inactive membership, cross-Alliance role/member IDs, hierarchy denial, attempts to remove the last Owner, or inconsistent role state caused by unsupported direct data changes.

## 7. Recovery, replay and reconciliation

Re-run the supported role assignment/removal only after correcting the underlying authorization/tenant state. Do not bypass last-Owner or hierarchy checks by direct SQL. If role data is inconsistent, stop and repair through a reviewed migration/forward fix rather than guessing intended privileges.

## 8. Backup, restore, migration and rollback

Authorization state is PostgreSQL-backed. Shared backup/restore restores role and assignment state together with Memberships/Alliances. After recovery, verify representative Owner/member assignments and permission checks before declaring access control healthy.

## 9. Capacity, query and performance boundaries

RBAC lookups must remain tenant-bounded. The permission vocabulary and built-in roles are intentionally finite. Repository tests enforce behavior but are not a production membership-count capacity benchmark.

## 10. External-service degradation

There is no Authorization-owned external service. PostgreSQL failure causes authorization to fail closed; tenant/session issues surface through Alliances/Identity dependencies.

## 11. Safe operator actions and stop conditions

Safe actions are verifying tenant/membership/role rows, restoring database health and using supported manager flows. Stop if a proposed repair would create a last-Owner gap, cross tenants, elevate Platform authority, or require editing audit evidence.

## 12. Evidence, focused runbooks and related documentation

Retain request/trace IDs, Alliance/membership/role IDs, before/after role state, denial reason and release SHA. No focused P3 Authorization runbook is required. See [incident response](../../../operations/runbooks/incident-response.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Authorization security profile](../security/README.md).
