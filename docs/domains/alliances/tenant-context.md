# Alliance tenant context

[← Alliances domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Alliances

## 1. Purpose

Defines how an authenticated global User selects and enters exactly one active Alliance tenant context for normal Alliance-scoped application work.

This is a security boundary consumed by every tenant-scoped domain, not merely a UI preference.

## 2. Scope and non-scope

In scope:

- active-Alliance selection and session persistence;
- request-time context resolution/revalidation;
- active membership requirement;
- request-scoped `AllianceContext` and serializable `TenantContextSnapshot`;
- invalid/stale selection clearing; and
- explicit tenant propagation to long-running work.

Out of scope:

- authentication, owned by Identity;
- membership lifecycle, owned by Memberships;
- permission evaluation, owned by Authorization; and
- cross-tenant Platform administration.

## 3. Model and state

The selected Alliance ID is session state. On a route protected by `alliance.context`, the resolver verifies the Alliance and the authenticated User's Memberships-owned membership.

A valid request context exposes:

- `alliance_id`;
- `alliance_membership_id`; and
- a serializable tenant-context snapshot suitable for explicit propagation.

Alliance lifecycle must be active and membership status must be active.

## 4. Invariants

1. One request has at most one active Alliance context.
2. Global User identity never authorizes tenant access by itself.
3. Active membership in the selected Alliance is required.
4. Non-active Alliance or membership fails closed.
5. Saved session selection is revalidated rather than trusted indefinitely.
6. Tenant-owned feature queries begin from explicit `alliance_id` scope.
7. Shared/global references such as Kingdoms identities never substitute for tenant context.
8. Request-scoped context is cleared after request completion.

## 5. Workflows

### Select or switch Alliance

The authenticated User selects an Alliance to which they have active membership. The Alliance ID is stored in the session.

### Resolve protected request

`ResolveAllianceContext` re-resolves the selected Alliance and membership. Successful resolution initializes the request-scoped context/snapshot.

### Invalid context

If no selection exists, the selected Alliance is non-active, or membership is missing/non-active, the request is denied/redirected according to the route contract and invalid saved state is cleared where appropriate.

### Long-running work

Jobs, exports, queued work, cache keys, and other work that outlives the request must carry explicit tenant identity/snapshot. They must not depend on process-global request context.

## 6. Authorization, tenancy and privacy

Tenant context establishes **which Alliance** the request belongs to; it does not decide **what the membership may do**. Authorization still evaluates the required permission against the active membership.

Submitted business object IDs are re-resolved beneath this Alliance. Cross-tenant object IDs fail closed.

Tenant identity must be included where applicable in storage paths, cache keys, exports, jobs, logs, and outbox state.

## 7. Persistence and query semantics

Session state stores the selected Alliance identifier. The Alliance aggregate is Alliances-owned; membership state remains Memberships-owned.

Tenant-scoped queries must filter by the resolved Alliance before exposing or mutating domain-owned state. Loading arbitrary IDs and filtering afterward is not an accepted isolation pattern.

## 8. Events, integrations and background processing

Tenant context itself does not create a public integration event. Domain operations performed under the context may create audit/outbox evidence according to their own contract.

External API requests use Integrations-owned credential-derived tenant context rather than the interactive session selection mechanism.

## 9. Failure, idempotency and concurrency

- Missing selection is a selection-required/conflict condition, not implicit use of another Alliance.
- Stale membership or Alliance lifecycle state invalidates saved context.
- Switching Alliance changes subsequent request scope; existing long-running work retains the explicit context captured when it was created.
- Concurrent requests do not share mutable in-memory tenant context.

## 10. Operations and observability

Diagnose tenant failures separately from authentication and permission failures. Useful dimensions include selected Alliance ID, Alliance lifecycle state, membership ID/status, required permission, and request/trace ID.

Never log secret/private payloads merely to diagnose tenant resolution.

## 11. Tests and validation

Tests should cover:

- multi-Alliance switching;
- no-selection behavior;
- inactive/missing membership denial and saved-state clearing;
- inactive Alliance denial;
- cross-Alliance route binding/query isolation;
- tenant propagation to queued/export/cache work; and
- request-context cleanup.

## 12. Related documentation

- [Alliances domain](README.md)
- [Memberships](../memberships/README.md)
- [Authorization](../authorization/README.md)
- [Identity](../identity/README.md)
- [Platform](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
