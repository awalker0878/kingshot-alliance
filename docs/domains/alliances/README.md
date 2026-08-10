# Alliances domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Alliances`  
**Primary authorization boundary:** active-Alliance context; `alliance.manage` for Alliance settings

## 1. Purpose and ownership

Alliances owns the Alliance aggregate, Alliance creation, Alliance activation/switching, request-scoped active-Alliance context, and Alliance-level settings/composition surfaces that belong to the tenant itself.

A User is global; Alliance access is not. A single User may belong to multiple Alliances, but every Alliance-scoped request must resolve one explicit active Alliance and an active Memberships-owned membership for that Alliance.

## 2. Scope

### In scope

- Alliance aggregate and core Alliance identity/settings;
- Alliance creation;
- activation/switching of the current Alliance;
- request-scoped `AllianceContext`/tenant snapshot;
- canonical Alliance→Kingdom association as an Alliance setting; and
- lifecycle state consumption supplied by Platform for normal tenant access.

### Out of scope

- User authentication, owned by Identity;
- membership/invitation lifecycle, owned by Memberships;
- roles/permissions, owned by Authorization;
- cross-tenant lifecycle administration, owned by Platform;
- content/public-page authored records, owned by Content; and
- Kingdom roster/player/transfer/intelligence persistence, owned by Kingdoms.

## 3. Domain model

### Alliance

`Alliance` is the tenant and authorization principal for Alliance-scoped application behavior. Tenant-owned business records use `alliance_id` and must not infer tenant identity from a shared global User/Kingdom/player record.

### Active Alliance context

Authenticated Users select one Alliance as active. The selected Alliance ID is stored in the session and revalidated on every route protected by `alliance.context`.

Successful resolution attaches:

- `alliance_id`;
- `alliance_membership_id`; and
- a serializable tenant-context snapshot

to the request.

### Alliance→Kingdom association

The canonical Alliance stores `kingdom_id` rather than legacy free-form Kingdom persistence. Changing an Alliance's Kingdom is an Alliance setting under `alliance.manage`; Kingdoms consumes that relationship but does not own the Alliance aggregate.

## 4. Core invariants

1. Every Alliance-scoped request resolves one explicit active Alliance.
2. Active Alliance context requires an active Memberships-owned membership in that same Alliance.
3. A global User identity never authorizes tenant access by itself.
4. Alliance-owned records are scoped by `alliance_id`.
5. Missing, stale, suspended, or inconsistent active-Alliance context fails closed.
6. In-memory request Alliance context is cleared after the request.
7. Long-running work carries explicit tenant identity/snapshot rather than relying on request-global state.
8. Alliance creation is transactional and leaves the creator with a usable active Owner membership/role set through supported Memberships/Authorization contracts.
9. Alliance→Kingdom mutation remains an Alliance-setting operation; a shared/global Kingdom reference is never an authorization principal.

## 5. Lifecycles and workflows

### Create Alliance

Alliance creation is transactional. The accepted workflow creates the Alliance in active lifecycle state, creates an active membership for the creator, provisions built-in Alliance roles, assigns Owner to the creator, provisions Platform defaults, records audit evidence, and creates the durable outbox state required by the workflow.

The creator enters the Alliance as an active Owner rather than through a separate invitation flow.

### Select/switch active Alliance

The authenticated User chooses an Alliance. The selected ID is stored in session and revalidated on every Alliance-context route.

The request fails closed when:

- no Alliance is selected;
- the Alliance does not exist or is not active;
- the User's membership is missing or non-active; or
- the stored context is stale/inconsistent.

A failed revalidation clears the saved selection where appropriate rather than preserving invalid tenant state.

### Change Alliance settings

Alliance-owned settings use `alliance.manage` and recent password confirmation where the route is classified as security-sensitive.

### Change Alliance Kingdom

An archived Kingdom cannot be newly selected. The mutation uses active Alliance context, recent password confirmation, transaction/locking, audit, and internal durable event evidence. Kingdoms workflows that captured prior Kingdom context may fail closed after later Alliance-Kingdom drift rather than being silently retargeted.

## 6. Authorization and tenancy

Alliances establishes tenant context; Authorization decides whether the active membership has a required permission.

The normal Alliance-view boundary requires authenticated, verified Identity plus active Alliance context and active membership. Alliance-setting mutations use `alliance.manage` as defined by Authorization.

Platform administration is a separate cross-tenant model and does not establish authority by switching `AllianceContext`.

## 7. Cross-domain contracts

### Consumes

- **Identity** — authenticated/verified global User.
- **Memberships** — active membership needed to establish tenant context.
- **Authorization** — permission evaluation such as `alliance.view`/`alliance.manage`.
- **Platform** — Alliance lifecycle state, plan/default provisioning, cross-tenant lifecycle controls.
- **Audit** — attributable change evidence.
- **Kingdoms** — canonical active/archived Kingdom reference for Alliance association.

### Exposes

- `Alliance` tenant identity;
- active-Alliance context and serializable tenant snapshot;
- Alliance core settings/Kingdom association; and
- supported Alliance creation/settings workflows.

Other domains must consume these contracts rather than reaching through Alliance persistence to redefine tenant behavior.

## 8. Persistence and data ownership

Alliances owns the Alliance aggregate and Alliance-owned core settings/relationships such as `kingdom_id`.

It does not own Content relationships merely because content belongs to an Alliance; Content owns its own persistence. Likewise, Memberships, Authorization, Events, Recruitment, Contributions, Integrations, and Kingdoms own their tenant records beneath `alliance_id`.

## 9. Events, outbox and integrations

Alliance creation/settings changes create audit/outbox evidence where the business transition requires durable publication.

`alliance.kingdom_updated` is an internal durable event and remains excluded from generic external webhook fan-out until an explicitly approved external contract says otherwise.

## 10. HTTP, UI and API surfaces

Alliances owns first-party Alliance creation, selection/switching, and Alliance-setting surfaces.

The read-only external Alliance API representation is exposed through Integrations and derives its tenant from the API credential; the caller does not submit arbitrary `alliance_id`.

## 11. Background processing

Normal active-Alliance resolution is request-scoped and does not depend on a background worker. Long-running jobs must carry explicit tenant identity rather than trying to reuse request context.

## 12. Failure, idempotency and concurrency

- No selected Alliance produces a conflict/selection-required response.
- Missing/non-active membership clears invalid saved selection and denies tenant access.
- Non-active Alliance clears invalid selection and denies tenant access.
- Alliance creation/settings workflows use transactional behavior where multiple domain records must remain consistent.
- Kingdom-setting mutation uses locking and fails closed against invalid/archived references.

## 13. Security and privacy

Alliance tenant context is a security boundary, not a UI preference. Submitted object IDs in feature domains must be re-resolved under this tenant rather than trusting the ID alone.

Tenant identity must flow through jobs, cache keys, exports, storage paths, logs, and integration work. A shared global reference never grants cross-tenant access.

## 14. Observability and operations

Operators should distinguish identity/authentication failures from tenant-context failures, membership-state failures, Alliance lifecycle state, and permission denial.

See [Identity](../identity/README.md), [Memberships](../memberships/README.md), [Authorization](../authorization/README.md), [Platform](../platform/README.md), and [Observability](../../operations/observability.md).

## 15. Testing and architecture enforcement

Tests should protect:

- multi-Alliance User switching;
- context revalidation/clearing;
- cross-Alliance read/write/cache/export/queue/route-binding isolation;
- transactional Alliance creation;
- Alliance-setting authorization;
- Alliance→Kingdom association boundaries; and
- the architectural rule that tenant-owned feature persistence remains in its owning domain.

## 16. Explicit non-capabilities

Alliances does not:

- authenticate Users;
- own membership/invitation persistence;
- own role/permission persistence;
- perform cross-tenant platform administration;
- own feature-domain business records; or
- treat Kingdom/KingdomPlayer/KingdomAlliance references as authorization principals.

## 17. Capability documents

No separate Alliances capability files are required at present.

## 18. Related documentation

- [Identity domain](../identity/README.md)
- [Memberships domain](../memberships/README.md)
- [Authorization domain](../authorization/README.md)
- [Audit domain](../audit/README.md)
- [Platform domain](../platform/README.md)
- [Kingdoms domain](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Alliances/README.md`](../../../app/Domain/Alliances/README.md)
