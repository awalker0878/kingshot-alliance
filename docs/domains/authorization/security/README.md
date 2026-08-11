# Authorization security profile

[← Authorization domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Authorization  
**Code owner:** `app/Domain/Authorization`  
**Primary security boundary:** permission evaluation against an active membership in the target Alliance, independent of authentication assurance and Platform grants

## 1. Security purpose and scope

Authorization defines and evaluates Alliance-scoped least privilege. Its security objective is to prevent cross-tenant or role-based privilege escalation while preserving a stable permission contract for feature domains.

This profile covers fixed permission vocabulary, built-in roles, role assignment/removal, effective permission union, hierarchy rank used by Memberships safety, and separation from Platform administration.

## 2. Assets and sensitive data

Security-relevant state includes role definitions, permission grants, membership-role assignments, and effective permission decisions. These are authorization-control data rather than public profile data.

Role and permission names are not secrets, but unauthorized mutation or cross-tenant association can grant access to private feature data and privileged operations.

## 3. Actors, authentication and authorization

Permission evaluation requires a global authenticated User only as the starting identity. Access is granted only through an active Memberships-owned membership in the target Alliance and one or more assigned roles that grant the requested permission.

Role administration requires `roles.manage`; sensitive HTTP mutations also use the applicable Identity verification/recent-password boundary. MFA/password confirmation strengthens assurance but never substitutes for the permission decision.

## 4. Tenant and privacy boundaries

Roles and membership-role assignments are Alliance scoped. A role in one Alliance grants nothing in another, and submitted membership/role identifiers are re-resolved under the active tenant.

Global User, Kingdom/game identity, Rally coordinator assignment, contact data, or display-name leadership never confers Alliance permission.

## 5. Trust boundaries and data flows

Material flows are authenticated tenant request → active membership → Authorization permission evaluation, and privileged role-management request → same-Alliance membership/role re-resolution → persistence → Audit/outbox evidence.

Feature domains consume stable permission keys and remain responsible for their own object-level/tenant query boundaries.

## 6. Threats, abuse cases and controls

Primary threats are cross-Alliance role injection, hard-coded role-name authorization, stale/inactive membership privilege, assigning roles to inactive users, removing the final active Owner, peer/owner escalation, and accidentally treating Platform grants or workflow responsibility as Alliance RBAC.

Controls include composite tenant relationships, active-membership checks, permission-based policies, effective rank/last-Owner guards, same-Alliance identifier re-resolution, and architecture/tests that reject role-name shortcuts.

## 7. Integrity, concurrency and idempotency

Assigning an already-present role is a no-op. Legitimate assign/remove/reassign cycles use distinct durable event identity where required. Owner-role removal is rejected if it would violate last-active-Owner safety.

Role state is effective synchronously; no background worker may temporarily grant or revoke permission by inference.

## 8. Secrets and credential handling

Authorization owns no passwords, MFA secrets, bearer tokens, API credentials, or signing material. It consumes assurance outcomes from Identity and stores only authorization-control state.

Audit/logging of role changes must avoid copying secret material from surrounding requests or feature payloads.

## 9. Destructive operations, retention and deletion

Role removal and membership-role cleanup are privileged state changes but not general data-destruction workflows. Membership leave/removal strips role assignments through supported contracts so dormant privilege does not silently return on reactivation.

Platform lifecycle may orchestrate tenant/account cleanup while preserving Authorization ownership and last-Owner/safety semantics where applicable.

## 10. Auditability, observability and evidence

Role assignment/removal is attributable and uses Audit/outbox evidence where required. Diagnose authorization failures from tenant, membership status, assigned roles, and effective permission rather than UI role labels.

Tests protect built-in role matrix, permission union, cross-tenant isolation, inactive-member rejection, owner safety, and feature use of permissions/policies.

## 11. Residual risks and explicit non-capabilities

If custom role templates or editable permission vocabularies are introduced later, the current profile is insufficient; that feature would require its own focused review covering permission-diff persistence, self-lockout, approval/audit, and privilege-escalation abuse.

Authorization does not authenticate Users, own Membership lifecycle, grant Platform administration, support arbitrary custom roles, or infer permission from game/workflow identity.

## 12. Focused reviews and related documentation

No focused living Authorization security review is required for the current fixed-role model.

- [Authorization domain contract](../README.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Alliances security profile](../../alliances/security/README.md)
- [Identity security profile](../../identity/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
