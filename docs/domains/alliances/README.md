# Alliances domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Alliances`  
**Primary authorization boundary:** active-Alliance context; `alliance.manage` for Alliance settings

## 1. Purpose and ownership

Alliances owns the Alliance tenant aggregate, Alliance creation/settings, interactive active-Alliance selection/context, and the canonical Alliance→Kingdom association.

A User is global. Alliance access is tenant scoped and requires explicit active Alliance context plus active Memberships-owned membership.

## 2. Scope

In scope: Alliance identity/settings, creation, active selection/context, tenant snapshot, and `kingdom_id` association.

Out of scope: Identity authentication, Memberships lifecycle, Authorization roles/permissions, Platform cross-tenant lifecycle administration, and feature-domain persistence.

## 3. Domain model

`Alliance` is the tenant principal for Alliance-scoped business data. Tenant-owned feature records carry `alliance_id` in their owning domains.

Interactive tenant resolution is a separate material capability documented in [Alliance tenant context](tenant-context.md).

## 4. Core invariants

1. Alliance-scoped work uses one explicit tenant.
2. Global User or global Kingdoms reference identity never grants tenant access.
3. Alliance creation is transactional and leaves the creator with a usable active Owner membership/role set through supported cross-domain contracts.
4. Alliance settings remain Alliance-owned even when referenced by other domains.
5. `kingdom_id` is an Alliance setting; Kingdoms consumes it but does not own the Alliance aggregate.

## 5. Lifecycles and workflows

Alliance creation provisions the Alliance, creator membership/Owner role, Platform defaults, and required audit/outbox evidence transactionally.

Alliance settings mutations use the active tenant, `alliance.manage`, and required Identity assurance. Changing Kingdom rejects archived targets and preserves historical Kingdoms workflow context rather than silently retargeting it after later drift.

Tenant selection/revalidation is documented in [Alliance tenant context](tenant-context.md).

## 6. Authorization and tenancy

Alliances establishes tenant context; Authorization decides whether the active membership has the required permission. Platform administration is a separate cross-tenant authority model.

## 7. Cross-domain contracts

Consumes Identity authenticated/verified User, Memberships active membership, Authorization permissions, Platform lifecycle/defaults, Audit/outbox evidence, and Kingdoms reference identity.

Exposes Alliance tenant identity, Alliance settings, active tenant context/snapshot, and supported Alliance creation/settings workflows.

## 8. Persistence and data ownership

Alliances owns the Alliance aggregate and core settings/relationships. Feature-domain records remain owned by Content, Events, Recruitment, Contributions, Integrations, Kingdoms, Memberships, Authorization, and other owning domains even when Alliance scoped.

## 9. Events, outbox and integrations

Material Alliance mutations may record audit/outbox evidence. `alliance.kingdom_updated` is internal and is not automatically externally webhook eligible.

## 10. HTTP, UI and API surfaces

Alliances owns first-party Alliance creation, selection/switching, overview, and settings surfaces. The external read-only Alliance representation is Integrations-owned and derives tenant identity from its machine credential.

## 11. Background processing

Alliance selection/context is request driven. Long-running work carries explicit tenant identity rather than process-global request state.

## 12. Failure, idempotency and concurrency

Creation/settings transitions use transactions/locking where multiple records or sensitive relationships must remain consistent. Invalid/stale tenant context fails closed as specified in [Alliance tenant context](tenant-context.md).

## 13. Security and privacy

Alliance tenancy is a security boundary. Submitted feature IDs are re-resolved beneath the active Alliance; shared/global references never bypass tenant isolation.

## 14. Observability and operations

Diagnose authentication, tenant-context, membership-state, Alliance-lifecycle, and permission failures separately. See [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests protect Alliance creation, settings, tenant isolation, Kingdom association boundaries, and [tenant-context](tenant-context.md) selection/revalidation behavior.

## 16. Explicit non-capabilities

Alliances does not authenticate Users, own membership/role persistence, perform cross-tenant Platform administration, own feature-domain state, or treat Kingdom/game identity as authorization.

## 17. Capability documents

- [Alliance tenant context](tenant-context.md) — selection, request resolution, tenant snapshot, propagation, and fail-closed semantics.

## 18. Related documentation

- [Memberships](../memberships/README.md)
- [Authorization](../authorization/README.md)
- [Identity](../identity/README.md)
- [Platform](../platform/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Alliances/README.md`](../../../app/Domain/Alliances/README.md)
