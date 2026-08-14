# Authorization domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract
**Status:** Current — EVENTS-002 P1
**Code owner:** `app/Domain/Authorization`
**Primary authorization boundary:** exact-context permission evaluation from Alliance rank/specialist roles or explicit Kingdom role assignments

## 1. Purpose and ownership

Authorization owns the fixed permission vocabulary, Alliance rank-derived permission policy, additive Alliance specialist roles, Kingdom-scoped role templates/assignments, and permission evaluation services.

Memberships owns the authoritative R1–R5 rank value. Platform owns platform-administrator grants. Authorization never treats those concepts as interchangeable.

## 2. Scope

### In scope

- fixed `PermissionKey` vocabulary;
- baseline Alliance permissions derived from `AllianceMembership.rank`;
- additive Alliance specialist roles and assignments;
- Kingdom Admin / Kingdom Event Coordinator / Kingdom Viewer roles and exact-Kingdom assignments;
- Alliance, Kingdom, and role-assignment authorization services;
- audited/durable role-assignment changes; and

### Out of scope

- authentication, MFA and session assurance, owned by Identity;
- membership/invitation lifecycle and authoritative rank value, owned by Memberships;
- active Alliance context, owned by Alliances;
- neutral Kingdom/player identity, owned by Kingdoms;
- platform-administrator lifecycle, owned by Platform; and
- arbitrary custom permission vocabulary or custom role-template editing.

## 3. Domain model

### Alliance rank and specialist roles

Every active Alliance membership has one rank: R1, R2, R3, R4, or R5. R5 is the single Alliance owner/leader; R4 is officer; R3/R2/R1 are member ranks. Rank contributes baseline permissions and hierarchy.

`DefaultAllianceRole` contains only additive specialist responsibilities: Recruiter, Event Coordinator, and Content Manager. Specialist roles never change hierarchy.

### Scoped Event permissions

Event permissions are scoped by operational context:

```text
events.player.view
events.player.create
events.player.manage
events.alliance.view
events.alliance.create
events.alliance.manage
events.kingdom.view
events.kingdom.create
events.kingdom.manage
events.types.manage
```

### Kingdom roles

Kingdom roles are instantiated per Kingdom and use the global permission catalogue:

| Role | Permission bundle |
| --- | --- |
| Kingdom Admin | `events.kingdom.view/create/manage`, `kingdom.roles.manage` |
| Kingdom Event Coordinator | `events.kingdom.view/create/manage` |
| Kingdom Viewer | `events.kingdom.view` |

See [Kingdom-scoped roles](kingdom-scoped-roles.md).

## 4. Core invariants

1. Alliance permission requires an active membership in the exact target Alliance.
2. Effective Alliance permission is rank-derived permission plus assigned specialist-role permission.
3. Specialist roles never alter R1–R5 hierarchy.
4. Exactly one active R5 is enforced per Alliance by Memberships persistence/lifecycle controls.
5. Alliance rank/role authority never grants `events.kingdom.*`, `events.types.manage`, or `kingdom.roles.manage`.
6. Kingdom permission requires an explicit assignment whose role belongs to the exact target Kingdom.
7. A Kingdom role from one Kingdom cannot be attached through another Kingdom context.
8. Platform administrators may bootstrap/recover Kingdom assignments but do not automatically receive Kingdom Event authority.
9. Controllers/features authorize by permission and exact target, never rank/role-name shortcuts.
10. Assignment/removal changes are attributable and auditable.

## 5. Lifecycles and workflows

### Alliance permission evaluation

`AllianceAuthorization` resolves the actor's active target-Alliance membership, checks `AllianceRankPermissions`, then checks additive specialist roles.

### Alliance specialist role assignment

R5-authorized role administration uses `roles.manage`. The membership and role are re-resolved within the same Alliance. Duplicate assignment is a no-op.

### Kingdom role provisioning and assignment

`KingdomRoleProvisioner` materializes the three system roles for one Kingdom and synchronizes their exact permission bundles. `AssignKingdomRole` serializes mutation on the target Kingdom, permits bootstrap by an active Platform administrator, otherwise requires `kingdom.roles.manage`, and is idempotent for an existing assignment.

### Kingdom role removal

`RemoveKingdomRole` repeats authorization inside the target-Kingdom lock, verifies exact assignment scope, and prevents a non-Platform actor from removing the final Kingdom Admin.

## 6. Authorization and tenancy

Alliance and Kingdom are separate authorization contexts. R5/R4 can have broad authority inside their Alliance but receive no Kingdom Event authority unless separately assigned a Kingdom role.

A global User, `Kingdom`, `Player`, game-side title, display name, roster presence, or Platform-administrator status does not itself grant a feature permission.

Identity assurance such as recent-password confirmation or MFA is additive to—not a substitute for—the contextual permission decision.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — target Alliance identity/context.
- **Memberships** — active membership and R1–R5 rank.
- **Kingdoms** — exact target Kingdom identity for Kingdom-scoped authorization.
- **Identity** — authenticated User identity.
- **Platform** — Platform-administrator bootstrap/recovery status and outbox infrastructure.
- **Audit** — attributable authorization-state changes.

### Exposes

- `PermissionKey`;
- `AllianceRankPermissions` and `AllianceAuthorization`;
- `DefaultAllianceRole` specialist templates;
- `DefaultKingdomRole`, `KingdomRoleProvisioner`, and `KingdomAuthorization`;
- supported Alliance/Kingdom role assignment/removal actions.

Events composes these services through its own exact-target `EventAuthorization` facade.

## 8. Persistence and data ownership

Authorization owns `permissions`, Alliance specialist `roles`/`role_permissions`/`membership_roles`, and Kingdom `kingdom_roles`/`kingdom_role_permissions`/`kingdom_role_assignments`.

Memberships owns `alliance_memberships.rank` and membership status. Kingdoms owns the target `kingdoms` rows. Platform owns `platform_administrators`.

The schema defines R1–R5 membership rank and specialist-role authorization directly.

## 9. Events, outbox and integrations

Alliance specialist-role and Kingdom-role changes produce attributable audit/outbox evidence where required. Kingdom role evidence uses `kingdom_id` metadata while leaving the Alliance outbox partition nullable because Kingdom authorization is not Alliance tenancy.

Authorization state is not automatically an external webhook/API contract.

## 10. HTTP, UI and API surfaces

Alliance specialist-role/rank administration remains exposed through Alliance management adapters with recent-password protection for mutation.

EVENTS-002 P1 provides a dedicated Kingdom-role management UI backed by the same server-side authorization actions. The UI may reflect effective permissions, but it never infers or grants authority client-side.

## 11. Background processing

Authorization evaluation and role mutation are synchronous. No queue/scheduler is required for permission convergence.

## 12. Failure, idempotency and concurrency

- cross-Alliance and cross-Kingdom identifiers fail closed;
- duplicate Alliance/Kingdom role assignment is a no-op;
- Kingdom assignment/removal serializes on the target Kingdom row;
- authorization is rechecked inside the Kingdom mutation lock;
- a non-Platform actor cannot remove the final Kingdom Admin;
- inactive Alliance membership cannot satisfy Alliance authorization;
- R5 lifecycle safety is Memberships-owned and remains transactionally protected.

## 13. Security and privacy

Authorization state is security-control data. Least privilege is expressed as stable permissions tied to exact contexts. Platform bootstrap is deliberately separated from Event authority, and Player identity is not inferred here from neutral Kingdom identity.

See [Authorization security](security/README.md) and [Kingdom-scoped roles](kingdom-scoped-roles.md).

## 14. Observability and operations

Diagnose denial from target context, actor membership/Kingdom assignment, rank, specialist roles, and effective permission. Never diagnose by assuming an R-number or leadership-sounding label implies a capability.

See [Authorization operations](operations/README.md).

## 15. Testing and architecture enforcement

Tests protect:

- rank permission bundles and specialist-role separation;
- scoped Event permission vocabulary;
- rank and specialist-role provisioning;
- exact-Kingdom role isolation and composite constraints;
- Platform bootstrap without implicit Event authority;
- Kingdom assignment idempotency and final-admin safety;
- cross-Alliance/cross-Kingdom denial; and
- feature-domain use of permission services rather than rank/role shortcuts.

See [Authorization testing](testing/README.md).

## 16. Explicit non-capabilities

Authorization does not currently provide arbitrary custom Alliance or Kingdom role templates, editable permission vocabulary, automatic Kingdom administrator inference from Alliance R5, Platform-administrator Event authority, or authentication/session assurance.

## 17. Capability documents

- [Kingdom-scoped roles](kingdom-scoped-roles.md)
- [Authorization security](security/README.md)
- [Authorization operations](operations/README.md)
- [Authorization interfaces](interfaces/README.md)
- [Authorization testing](testing/README.md)

## 18. Related documentation

- [Memberships](../memberships/README.md)
- [Alliances](../alliances/README.md)
- [Events](../events/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Identity](../identity/README.md)
- [Platform](../platform/README.md)
- [`app/Domain/Authorization/README.md`](../../../app/Domain/Authorization/README.md)
