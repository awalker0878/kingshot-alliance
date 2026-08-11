# Alliance tenant context security review

[← Alliances security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Alliances  
**Capability:** Alliance tenant context  
**Code owner:** `app/Domain/Alliances`

## 1. Scope and security objective

Protect active-Alliance selection, request-time revalidation, `AllianceContext`, and `TenantContextSnapshot` so every tenant-scoped action has one explicit currently valid Alliance and no stale/global identity can silently cross tenant boundaries.

## 2. Assets and sensitive data

Assets are selected Alliance session state, resolved Alliance/membership IDs, request-scoped context, serializable tenant snapshots, and tenant identity propagated to jobs/cache/storage/export/log/outbox boundaries.

The IDs are not secrets, but incorrect propagation is security critical because it can mis-scope private feature data.

## 3. Trust boundaries

- Authenticated browser/session → `ResolveAllianceContext`.
- `ResolveAllianceContext` → Alliances + Memberships persistence.
- Resolved request context → Authorization and feature-domain queries.
- Request → queued/export/cache/storage/log work through an explicit snapshot.
- External API → separate Integrations credential-derived tenant context; it does not use interactive session selection.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| User selects an Alliance without active membership | Unauthorized tenant entry | Selection/resolution requires active Memberships-owned membership. |
| Stale/suspended/removed membership remains in session | Continued unauthorized access | Every protected request revalidates Alliance lifecycle and membership status; invalid saved state fails closed/clears. |
| Cross-Alliance business ID substitution | Read/write another tenant's state | Feature IDs are re-resolved under resolved `alliance_id`; queries begin tenant-scoped. |
| Global User/Kingdom/game identity treated as tenant access | Authorization bypass | Explicit invariant that shared/global references never substitute for tenant context. |
| Job/cache/storage/export omits tenant | Cross-tenant collision/disclosure | `TenantContextSnapshot` and tenant-prefixed helper contracts carry explicit tenant identity. |
| Unsafe tenant path composition | Storage/export boundary escape | Tenant path helpers reject unsafe segments and use controlled tenant prefixes. |
| Process-global mutable context leaks across concurrent work | Cross-request tenant contamination | Request-scoped context is initialized/cleared per request; long-running work uses serialized explicit snapshots. |

## 5. Authorization, tenancy and privacy

Tenant context answers **which Alliance** only. Authorization still checks the required permission against the active membership. Password confirmation/MFA may strengthen privileged operations but cannot create membership or permission.

Tenant-private feature state remains in the owning domain. Logging/diagnostics should record safe tenant/membership identifiers and correlation, not private payloads.

## 6. Integrity, replay and concurrency

Switching Alliance affects subsequent requests only; already-created jobs retain the snapshot captured when created. Concurrent requests do not share a mutable singleton tenant identity.

No-selection and invalid-selection behavior is explicit rather than falling back to a prior/default tenant. Repeated resolution is safe and does not create business state.

## 7. Secret and data lifecycle

Session selection is authenticated session state, not a portable API token. It is revalidated rather than trusted indefinitely and is cleared when invalid.

No password, MFA, invitation, API, or webhook secret is stored in the tenant snapshot. Snapshots carry only identity/context required to scope later work.

## 8. Abuse limits and failure behavior

Missing, inactive, or unauthorized context fails closed. Cross-tenant identifiers fail when re-resolved. Tenant-resolution errors are distinguished from authentication and permission denial so operators do not weaken controls while debugging.

No recovery path permits manual tenant override or database editing to bypass membership/lifecycle validation.

## 9. Verification and evidence

Required verification includes multi-Alliance switching, no-selection behavior, inactive/missing membership clearing, inactive Alliance denial, cross-Alliance route/query isolation, tenant propagation to queued/export/cache/storage work, request cleanup, and role/permission separation.

Historical security source: [Phase 1 threat model](../../../../security/phase-1-threat-model.md). Current shared controls: [Security baseline](../../../../security/security-baseline.md).

## 10. Residual risks and external controls

Application helpers cannot prove every external storage/cache/log processor or future job correctly uses the snapshot; architecture tests and change review must prevent bypass contracts. Production Redis/storage/logging isolation is runtime evidence, not established by this document.

Cross-tenant Platform administration deliberately bypasses interactive Alliance context but requires its separate Platform grant/assurance model; it must never reuse tenant context as its authority.