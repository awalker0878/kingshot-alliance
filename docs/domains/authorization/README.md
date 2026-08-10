# Authorization domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Authorization`  
**Primary authorization boundary:** policy/permission evaluation against an active membership in the target Alliance

## 1. Purpose and ownership

Authorization owns Alliance roles, permission keys, role assignment/removal, and permission evaluation. Authorization is permission based, not controller-name or leadership-label based.

`PermissionKey` is authoritative for the permission vocabulary. `DefaultAllianceRole` is authoritative for built-in role templates. A membership may hold multiple roles; effective permissions are the union of permissions from its assigned roles.

## 2. Scope

### In scope

- fixed Alliance permission vocabulary;
- built-in Alliance role templates;
- role assignment/removal;
- permission evaluation for active memberships;
- effective role ranking used by Memberships administration safety; and
- last-active-Owner role-removal protection in coordination with Memberships.

### Out of scope

- global authentication/MFA, owned by Identity;
- membership/invitation lifecycle, owned by Memberships;
- active Alliance context, owned by Alliances;
- platform-administrator grants, owned by Platform; and
- arbitrary custom role-template/permission-vocabulary editing, which is not currently supported.

## 3. Domain model

### Permission vocabulary

The current permission vocabulary is:

| Permission | Capability |
| --- | --- |
| `alliance.view` | View Alliance member areas, including the Alliance roster. |
| `alliance.manage` | Manage Alliance settings and integration administration surfaces. |
| `membership.manage` | Manage Alliance membership status. |
| `roles.manage` | Assign and remove Alliance roles. |
| `invitations.manage` | Create, revoke, and resend membership invitations. |
| `content.manage` | Manage Alliance content and public-presence content. |
| `events.manage` | Manage Events and Rally configuration. |
| `recruitment.manage` | Manage Recruitment workflows. |
| `contributions.manage` | Manage Contribution records, reporting, exports, and report schedules. |
| `kingdoms.manage` | Manage Kingdoms roster/observation/transfer/intelligence workflows authorized for managers. |

`app/Domain/Authorization/Enums/PermissionKey.php` is authoritative if this table drifts.

### Built-in role templates

The built-in templates currently resolve as follows:

| Role | View | Alliance | Membership | Roles | Invitations | Content | Events | Recruitment | Contributions | Kingdoms |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Leader | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Officer | ✓ | — | ✓ | — | ✓ | — | ✓ | — | — | ✓ |
| Member | ✓ | — | — | — | — | — | — | — | — | — |
| Recruiter | ✓ | — | — | — | ✓ | — | — | ✓ | — | — |
| Event Coordinator | ✓ | — | — | — | — | — | ✓ | — | — | — |
| Content Manager | ✓ | — | — | — | — | ✓ | — | — | — | — |

The abbreviated columns map to `alliance.view`, `alliance.manage`, `membership.manage`, `roles.manage`, `invitations.manage`, `content.manage`, `events.manage`, `recruitment.manage`, `contributions.manage`, and `kingdoms.manage`.

Only Owner currently includes `roles.manage`. `kingdoms.manage` is granted by default to Owner, Leader, and Officer; specialist roles and Member do not receive it by default.

### Effective role rank

Membership administration uses this effective rank model:

| Role | Rank |
| --- | ---: |
| Owner | 100 |
| Leader | 80 |
| Officer | 60 |
| Recruiter / Event Coordinator / Content Manager | 40 |
| Member | 10 |

## 4. Core invariants

1. Permission is granted only when the User has an **active membership in the target Alliance** and at least one assigned role grants the requested permission.
2. Permission evaluation is tenant-specific; a role in one Alliance grants nothing in another.
3. A membership may hold multiple roles; effective permissions are the union.
4. Controllers/features authorize by permission/policy, not by hard-coded role-name shortcuts.
5. Role assignment/removal re-resolves both membership and role under the active Alliance.
6. Only active memberships can receive a role.
7. Assigning an already-present role is a no-op rather than duplicate state.
8. Role changes are audited and emit durable events where required.
9. Owner-role removal is subject to last-active-Owner safety.
10. Platform-administrator access is not an Alliance permission/role.

## 5. Lifecycles and workflows

### Evaluate permission

`AllianceAuthorization` evaluates the active membership in the target Alliance and the union of permissions from assigned roles.

### Assign role

Role assignment requires `roles.manage`. The target membership and role are resolved within the active Alliance. Only active memberships can receive a role. Duplicate assignment is a no-op.

### Remove role

Role removal requires `roles.manage`, remains Alliance-scoped, and is rejected when it would violate last-active-Owner safety.

### Provision built-in roles

Alliance creation provisions built-in role templates and assigns Owner to the creator through the supported creation workflow.

### Permission use across features

Feature domains consume stable permissions rather than reimplementing authorization. Examples include `content.manage`, `events.manage`, `recruitment.manage`, `contributions.manage`, and `kingdoms.manage`.

## 6. Authorization and tenancy

Authorization itself depends on explicit Alliance context and Memberships-owned active membership.

A global User, Kingdom, KingdomPlayer, game Alliance reference, coordinator assignment, or display-name leadership label never grants a permission.

Recent password confirmation/MFA may be required by a sensitive route, but those identity-assurance controls are additional to—not substitutes for—the permission decision.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — target Alliance/active tenant context.
- **Memberships** — active membership and membership status.
- **Identity** — authenticated User identity used to locate membership; password/MFA assurance is independent.
- **Audit** — role/permission administration evidence.

### Exposes

- `PermissionKey` permission vocabulary;
- `DefaultAllianceRole` built-in templates/ranks;
- `AllianceAuthorization` permission evaluation; and
- supported role assignment/removal actions used by Memberships/Alliance administration.

## 8. Persistence and data ownership

Authorization owns role, permission, membership-role assignment, and permission-evaluation configuration/persistence belonging to the Alliance authorization model.

Membership status remains Memberships-owned. User identity remains Identity-owned. Platform-administrator grants remain Platform-owned.

## 9. Events, outbox and integrations

Role assignment/removal produces attributable audit evidence and unique outbox events where required so legitimate assign/remove/reassign cycles do not collide with prior durable event identity.

Role/permission state is not exposed as an external webhook/API contract merely because an internal event exists.

## 10. HTTP, UI and API surfaces

Role administration is a first-party Alliance management surface protected by `roles.manage` and recent password confirmation for privileged mutation.

Feature UI visibility may reflect permissions, but server-side policy/permission evaluation remains authoritative.

## 11. Background processing

Permission evaluation and role assignment are synchronous. Authorization does not depend on a background worker to make a permission effective.

## 12. Failure, idempotency and concurrency

- Cross-Alliance role/membership IDs fail closed when re-resolved.
- Assigning an existing role is a no-op.
- Removing the final active Owner role fails closed.
- An inactive membership cannot receive a role.
- Permission denial is not bypassed by password confirmation/MFA or coordinator responsibility.

## 13. Security and privacy

Least privilege is defined in terms of stable permissions. Avoid role-name shortcuts in controllers because a leadership-sounding role does not necessarily include every permission.

Platform administration remains deliberately separate from Alliance RBAC.

## 14. Observability and operations

Authorization failures should be diagnosed by active Alliance, membership status, assigned roles, and effective permission—not by assuming a role name implies a capability.

See [Alliances](../alliances/README.md), [Memberships](../memberships/README.md), [Identity](../identity/README.md), and [Security baseline](../../security/security-baseline.md).

## 15. Testing and architecture enforcement

Tests should protect:

- permission-union behavior;
- built-in role matrix;
- cross-Alliance role isolation;
- role assignment/removal authorization;
- inactive-membership rejection;
- Owner safety; and
- feature-domain use of policy/permission checks rather than role-name authorization shortcuts.

## 16. Explicit non-capabilities

Authorization does not currently provide:

- arbitrary custom role templates;
- editing of the permission vocabulary;
- platform-administrator grants;
- global User authentication; or
- authorization derived from Kingdom/game identity, coordinator assignment, or contact data.

## 17. Capability documents

No separate Authorization capability files are required at present.

## 18. Related documentation

- [Alliances domain](../alliances/README.md)
- [Memberships domain](../memberships/README.md)
- [Identity domain](../identity/README.md)
- [Audit domain](../audit/README.md)
- [Platform domain](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Authorization/README.md`](../../../app/Domain/Authorization/README.md)
