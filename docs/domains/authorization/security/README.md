# Authorization security profile

[← Authorization domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Authorization  
**Code owner:** `app/Domain/Authorization`  
**Primary security boundary:** exact-context permission evaluation from Alliance rank/specialist roles or explicit Kingdom role assignments, independent of authentication assurance and Platform grants

## 1. Security purpose and scope

Authorization defines and evaluates contextual least privilege for Alliance and Kingdom operations. Its security objective is to prevent cross-Alliance, cross-Kingdom, rank/role, and scope-confusion privilege escalation while preserving a stable permission contract for feature domains.

This profile covers fixed permission vocabulary, R1–R5-derived Alliance permissions, additive specialist roles, Kingdom-scoped roles/assignments, role assignment/removal, exact-target evaluation, and separation from Platform administration.

## 2. Assets and sensitive data

Security-relevant state includes role definitions, permission grants, membership-role assignments, and effective permission decisions. These are authorization-control data rather than public profile data.

Role and permission names are not secrets, but unauthorized mutation or cross-tenant association can grant access to private feature data and privileged operations.

## 3. Actors, authentication and authorization

Permission evaluation requires a global authenticated User only as the starting identity. Alliance permission requires an active Memberships-owned membership in the target Alliance and is the union of rank-derived permission plus additive specialist roles. Kingdom permission requires an explicit assignment whose role belongs to the exact target Kingdom and grants the requested permission.

Alliance specialist-role administration requires `roles.manage`. Kingdom-role administration requires `kingdom.roles.manage`, except that Platform administrators may bootstrap/recover Kingdom assignments without becoming implicit Kingdom Event administrators. Sensitive HTTP mutations also use the applicable Identity verification/recent-password boundary. MFA/password confirmation strengthens assurance but never substitutes for the permission decision.

## 4. Tenant and privacy boundaries

Alliance specialist roles and membership-role assignments are Alliance scoped. Kingdom roles and assignments are Kingdom scoped with a composite `(role, kingdom)` persistence constraint. A role or assignment in one context grants nothing in another.

Global User, neutral Kingdom/game identity, Rally coordinator assignment, contact data, or display-name leadership never confers permission. Alliance R5/R4 authority does not confer Kingdom Event authority.

## 5. Trust boundaries and data flows

Material flows are authenticated tenant request → active membership → Authorization permission evaluation, and privileged role-management request → same-Alliance membership/role re-resolution → persistence → Audit/outbox evidence.

Feature domains consume stable permission keys and remain responsible for their own object-level/tenant query boundaries.

## 6. Threats, abuse cases and controls

Primary threats are cross-Alliance role injection, cross-Kingdom assignment substitution, scope-confusion between Player/Alliance/Kingdom permissions, stale roster-to-membership identity, hard-coded rank/role authorization, inactive membership privilege, removing the active R5 leader, removing the final Kingdom Admin, and accidentally treating Platform grants as Event authority.

Controls include composite Alliance/Kingdom relationships, active-membership and current-roster checks, exact target/scope matching, permission-based policies, R5 leadership guards, final-Kingdom-Admin safety, same-context identifier re-resolution, and architecture/tests that reject rank/role-name shortcuts.

## 7. Integrity, concurrency and idempotency

Assigning an already-present role is a no-op. Legitimate assign/remove/reassign cycles use distinct durable event identity where required. R5 deactivation is rejected if it would violate active-R5 leadership safety.

Role state is effective synchronously; no background worker may temporarily grant or revoke permission by inference.

## 8. Secrets and credential handling

Authorization owns no passwords, MFA secrets, bearer tokens, API credentials, or signing material. It consumes assurance outcomes from Identity and stores only authorization-control state.

Audit/logging of role changes must avoid copying secret material from surrounding requests or feature payloads.

## 9. Destructive operations, retention and deletion

Role removal and membership-role cleanup are privileged state changes but not general data-destruction workflows. Membership leave/removal strips role assignments through supported contracts so dormant privilege does not silently return on reactivation.

Platform lifecycle may orchestrate tenant/account cleanup while preserving Authorization ownership and R5 leadership/safety semantics where applicable.

## 10. Auditability, observability and evidence

Role assignment/removal is attributable and uses Audit/outbox evidence where required. Diagnose authorization failures from tenant, membership status, assigned roles, and effective permission rather than UI role labels.

Tests protect rank/specialist permission union, Kingdom role bundles, cross-Alliance/cross-Kingdom isolation, Player identity binding, inactive/stale context rejection, R5/final-Kingdom-Admin safety, and feature use of permissions/policies.

## 11. Residual risks and explicit non-capabilities

If custom role templates or editable permission vocabularies are introduced later, the current profile is insufficient; that feature would require its own focused review covering permission-diff persistence, self-lockout, approval/audit, and privilege-escalation abuse.

Authorization does not authenticate Users, own Membership lifecycle, grant Platform administration, support arbitrary custom roles, or infer permission from game/workflow identity.

## 12. Focused reviews and related documentation

EVENTS-002 P1 is covered by this living profile plus the executable scoped-authorization tests; any future custom Kingdom-role editor or permission-bundle editor requires a focused review.

- [Authorization domain contract](../README.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Alliances security profile](../../alliances/security/README.md)
- [Identity security profile](../../identity/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
