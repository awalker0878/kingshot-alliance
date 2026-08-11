# Alliances interfaces

[← Alliances domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Alliances  
**Code owner:** `app/Domain/Alliances`  
**Primary boundary:** Alliance lifecycle and active-tenant context consumed by first-party feature domains  
**P4 inventory decision:** Profile only

## 1. Boundary purpose and ownership

Alliances owns the platform Alliance aggregate, Alliance creation/activation/overview behavior, and the request-scoped active-Alliance context used by tenant-owned feature domains. It defines **which Alliance** a first-party request is operating within; Memberships and Authorization separately determine whether the user belongs to and may act in that tenant.

This profile does not claim ownership of public Alliance presentation, which is rendered through Content, or of the machine API representation, which is Integrations-owned.

## 2. Surface inventory

Material first-party surfaces include:

- authenticated dashboard and Alliance creation/activation flows in `routes/web.php`;
- active-Alliance overview under the `alliance.context` middleware boundary;
- Alliance→Kingdom setting integration consumed by Kingdoms workflows; and
- `ResolveAllianceContext` / `AllianceContext` as the supported tenant-context contract consumed throughout tenant domains.

Public `GET /alliances/{slug}` is a Content presentation surface backed by Alliance public identity. `GET /api/v1/alliance` is an Integrations machine contract representing selected Alliance facts.

## 3. Callers, authorization and tenancy

Creating or activating an Alliance requires authenticated, verified application identity according to the owning HTTP route and action rules. Normal tenant work requires an active membership and a resolvable active Alliance context.

`alliance.context` derives the tenant from the authenticated user's supported active-Alliance state; feature-domain callers must not accept an arbitrary tenant identifier as authority. Global neutral references such as Kingdoms identities never establish Alliance authorization.

## 4. Input and validation contracts

Alliance create/update/context actions validate user-supplied identity/settings through owning actions/controllers. Route/model identifiers are constrained and re-resolved rather than trusted as proof of tenancy.

Consumers of `AllianceContext` receive resolved domain objects/context snapshots rather than constructing tenant state from request parameters. Changes to the context-selection mechanism are cross-domain compatibility changes because all tenant-owned domains depend on it.

## 5. Output and disclosure contracts

First-party Alliance overview/dashboard payloads expose active-tenant facts appropriate to the authenticated member. Public presentation exposes only the subset intentionally selected by Content/public-profile behavior.

The external Integrations API may serialize Alliance id, name, slug, Kingdom number, language, and timezone under the `alliance:read` machine scope. That response schema is Integrations-owned and does not make all Alliance columns public.

## 6. Internal actions, queries and services

Supported internal contracts include Alliance creation/activation actions and `AllianceContext`/tenant snapshot resolution consumed by Memberships, Authorization, Content, Events, Rallies, Recruitment, Contributions, Integrations, Kingdoms, Notifications, and Platform orchestration where applicable.

Feature domains must consume tenant identity/context through these supported contracts instead of reaching across domain persistence to infer authorization.

## 7. Events, outbox and cross-domain consumers

Material Alliance lifecycle/settings changes may record audit/outbox evidence. Producer meaning remains Alliances-owned; Platform owns durable outbox publication.

`alliance.kingdom_updated` is an internal event family consumed as repository evidence/coordination but is explicitly excluded by Integrations from external webhook fan-out. Recording an Alliance event therefore never implies public webhook compatibility.

## 8. Commands, jobs and scheduled work

Alliances has no domain-specific CLI command or scheduler worker in the current runtime. Tenant context is primarily request driven.

Platform usage/lifecycle jobs may inspect or mutate Alliance lifecycle through Platform-owned orchestration, but those commands do not transfer Alliance semantic ownership to Platform.

## 9. Files, imports, exports and external dependencies

Alliances owns no direct file import/export format in P4. Platform provides a privileged cross-tenant Alliance JSON export under Platform administration, while Content/Events/Contributions/Kingdoms own their own files and exports.

Runtime context depends on Identity/session state, Memberships, Authorization, PostgreSQL, and applicable session/cache infrastructure. Dependency recovery behavior is documented in [Alliances operations](../operations/README.md).

## 10. Failure, idempotency, versioning and compatibility

Missing/inactive membership, missing Alliance selection, inaccessible Alliance state, or stale context fails closed rather than selecting another tenant. Tenant-context compatibility is repository-wide: changing its request attributes/snapshot semantics requires coordinated domain/test updates.

Alliance creation/activation retries follow the owning actions' persistence/idempotency rules; callers must not fabricate active context by directly editing session/request attributes.

## 11. Explicit non-capabilities

Alliances does not:

- authenticate users;
- own membership or role/permission state;
- expose a standalone public write API;
- make arbitrary Alliance persistence externally readable;
- make `alliance.kingdom_updated` an external webhook; or
- permit neutral Kingdom/player/game-Alliance identity to establish tenant access.

## 12. Focused contracts, evidence and related documentation

No new focused P4 interface contract is required. The current domain contract fully covers the Alliance aggregate/context boundary at contract level.

Related documentation:

- [Alliances domain contract](../README.md)
- [Alliances security](../security/README.md)
- [Alliances operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
