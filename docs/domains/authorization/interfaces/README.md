# Authorization interfaces

[← Authorization domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Authorization  
**Code owner:** `app/Domain/Authorization`  
**Primary boundary:** Internal Alliance permission/rank evaluation and role-assignment contracts consumed by first-party domains  
**P4 inventory decision:** Profile only

## 1. Boundary purpose and ownership

Authorization owns Alliance role/permission vocabulary, effective-role hierarchy, permission evaluation, and supported role-assignment/removal actions. Its principal P4 boundary is internal even though Memberships exposes first-party HTTP routes that adapt role-management requests into Authorization-owned actions.

Authorization determines **may this authenticated member perform this Alliance operation?** It does not authenticate the User or establish active tenant context.

## 2. Surface inventory

Material surfaces are:

- `AllianceAuthorization` and permission/rank lookup consumed by feature domains;
- supported membership-role assignment/removal actions;
- permission enums/value contracts used by policy-aware callers; and
- Memberships-mediated first-party routes for role assignment/removal.

There is no direct public/external Authorization API and no caller-supplied permission evaluator exposed over HTTP.

## 3. Callers, authorization and tenancy

Feature domains call Authorization only after Identity/Alliances/Memberships establish an authenticated active-Alliance context. Permission evaluation is always Alliance scoped.

Membership role mutations additionally enforce management permission, effective hierarchy, target tenancy, and last-Owner/safety rules coordinated with Memberships. A role identifier from another tenant is never authority to cross tenant boundaries.

## 4. Input and validation contracts

Permission checks take a User/Alliance and the stable owning permission key. Role assignment/removal contracts take tenant-resolved membership/role context rather than arbitrary global IDs.

Permission vocabulary is compatibility-relevant across domains because controllers/actions/tests refer to stable permission keys. New/renamed keys require coordinated consumer updates rather than silent aliasing.

## 5. Output and disclosure contracts

Permission evaluation returns authorization outcomes/effective role information needed by first-party code. It does not disclose all role definitions or another Alliance's assignments.

First-party UI may receive capability booleans or role data intentionally prepared by the owning workspace. Those presentation payloads remain tenant safe and are not a public machine API.

## 6. Internal actions, queries and services

Supported internal contracts include:

- `AllianceAuthorization` permission evaluation;
- role/rank lookup required by Memberships hierarchy checks;
- role assignment/removal actions; and
- stable permission enums consumed by feature-domain controllers/actions.

Callers must use these contracts rather than querying Authorization persistence to invent custom permission semantics.

## 7. Events, outbox and cross-domain consumers

Material role changes may create Audit/Platform-outbox evidence. Authorization owns the semantic meaning of role/permission changes; Platform owns outbox durability.

Internal authorization evidence does not create an external role-management webhook or API contract automatically.

## 8. Commands, jobs and scheduled work

Authorization has no domain-specific command, job, or scheduler workflow in the current runtime. Permission evaluation and role mutation are request/action driven.

Platform administrator grants are Platform-owned and deliberately separate from Alliance Authorization.

## 9. Files, imports, exports and external dependencies

Authorization has no current direct import/export or media contract. It depends on Identity, Alliances, Memberships, and PostgreSQL-backed role/assignment state.

Operational integrity and recovery are documented in [Authorization operations](../operations/README.md).

## 10. Failure, idempotency, versioning and compatibility

Missing permission, wrong tenant, hierarchy violations, invalid target role/membership, or last-Owner risk fail closed. Callers must not retry by bypassing supported actions with direct persistence edits.

Permission-key names and role semantics are internal cross-domain compatibility contracts. Changes require synchronized domain docs/tests/callers.

## 11. Explicit non-capabilities

Authorization does not:

- authenticate Users or establish sessions;
- select active Alliance context;
- own Membership lifecycle;
- grant Platform-administrator authority;
- expose a public/external RBAC API; or
- allow feature domains to define hidden ad hoc permissions outside the accepted vocabulary.

## 12. Focused contracts, evidence and related documentation

No new focused P4 interface contract is required. The internal evaluation/role-mutation boundary is coherent in this profile plus the canonical Authorization and Memberships contracts.

Related documentation:

- [Authorization domain contract](../README.md)
- [Authorization security](../security/README.md)
- [Authorization operations](../operations/README.md)
- [Memberships](../../memberships/README.md)
- [Membership invitations](../../memberships/invitations.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
