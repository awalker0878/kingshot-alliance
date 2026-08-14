# Alliances security profile

[← Alliances domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Alliances  
**Code owner:** `app/Domain/Alliances`  
**Primary security boundary:** explicit active-Alliance tenant context plus active membership before any tenant-scoped access

## 1. Security purpose and scope

Alliances establishes the tenant principal and interactive tenant context used by every Alliance-scoped feature. Its security objective is to ensure a global authenticated User can act only inside an explicitly selected Alliance in which they currently hold an active Memberships-owned membership.

This profile covers Alliance creation/settings, tenant selection/revalidation, tenant-context propagation, and the Alliance→Kingdom reference. The detailed tenant-boundary review is [Tenant context security review](tenant-context-security-review.md).

## 2. Assets and sensitive data

Security-relevant assets include Alliance tenant identity/settings, active-Alliance session selection, tenant-context snapshots propagated to jobs/cache/storage/export/log boundaries, and the `kingdom_id` reference.

Alliance settings are tenant-private unless a field is explicitly part of a public presentation contract. Tenant identifiers are not secrets, but incorrect association or propagation can expose another Alliance's private state.

## 3. Actors, authentication and authorization

Authenticated Users may select only Alliances for which they have an active membership. Alliance settings require the owning permission such as `alliance.manage` plus required Identity assurance for privileged mutations.

Authentication alone, a remembered Alliance ID, a global User ID, a Kingdom/game identifier, or a leadership label never grants tenant access.

## 4. Tenant and privacy boundaries

Alliance tenancy is the primary application data-isolation boundary. Tenant-owned feature identifiers are re-resolved beneath the active Alliance rather than trusted from route/form input.

A User may belong to multiple Alliances, but each request has one explicit active tenant. Stale/suspended/removed membership invalidates that tenant context and fails closed.

## 5. Trust boundaries and data flows

Material boundaries are authenticated browser → active-Alliance middleware/context, application → Memberships membership validation, request → tenant-scoped feature domains, and request → asynchronous/cache/storage/export/log contexts through the immutable tenant snapshot.

Alliance creation also crosses into Memberships/Authorization/Platform/Audit/outbox contracts transactionally; each receiving domain retains its own ownership and safety rules.

## 6. Threats, abuse cases and controls

Primary threats are cross-Alliance IDOR, stale tenant context, selecting an Alliance without active membership, tenant identity omitted from asynchronous/storage/cache keys, unsafe tenant path construction, and treating the Alliance→Kingdom reference as authorization.

Controls include active-membership revalidation, explicit tenant identifiers on owned queries, tenant-prefixed helper contracts, unsafe-path rejection, composite tenant constraints where applicable, and domain-level identifier re-resolution.

## 7. Integrity, concurrency and idempotency

Alliance creation is transactional so the tenant, creator membership/R5 rank, defaults, and required evidence cannot partially diverge. Sensitive settings transitions use transactions/locking where required.

Repeated selection/revalidation is safe; invalid context is cleared rather than silently reused. Kingdom association changes preserve historical Kingdoms workflow context rather than rewriting prior records.

## 8. Secrets and credential handling

Alliances owns no password, MFA, API, webhook-signing, or invitation secret. Session/context state must not be treated as a reusable bearer credential outside the authenticated session boundary.

Tenant IDs may appear in logs/audit correlation where appropriate, but unrelated private tenant payloads and secret material from other domains must not be copied into Alliance evidence.

## 9. Destructive operations, retention and deletion

Normal Alliances code does not directly perform cross-tenant destructive lifecycle deletion. Platform owns legal hold/deletion/restoration/retention orchestration and must preserve Alliances ownership rules while doing so.

Alliance settings changes remain attributable and must not destructively rewrite domain-owned historical evidence.

## 10. Auditability, observability and evidence

Alliance creation/settings and other material privileged transitions record attributable Audit/outbox evidence where required. Diagnose authentication, active membership, tenant context, permission, and lifecycle failures separately.

Repository tests cover tenant isolation, active-context revalidation, settings boundaries, and tenant propagation; the shared [Security baseline](../../../security/security-baseline.md) defines cross-domain session/transport/production controls.

## 11. Residual risks and explicit non-capabilities

Application tenant helpers cannot prove every external worker, storage, cache, or logging system is correctly production-isolated; deployment/runtime configuration remains separately evidenced.

Alliances does not authenticate Users, own memberships/roles, grant Platform authority, infer access from Kingdom/game identity, or permit hidden process-global tenant context.

## 12. Focused reviews and related documentation

- [Tenant context security review](tenant-context-security-review.md)
- [Alliance tenant context contract](../tenant-context.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Authorization security profile](../../authorization/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
