# Read-only API security review

[← Integrations security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Integrations  
**Capability:** Alliance-bound read-only API  
**Code owner:** `app/Domain/Integrations`

## 1. Scope and security objective

Protect the `/api/v1` machine read boundary so one opaque credential can read only the bounded approved representation for its owning Alliance and fixed scopes. No API credential may become a tenant selector, write authority, or unrestricted export token.

## 2. Assets and sensitive data

Assets include credential lookup/verifier/status/scope/expiry state, one-time issued plaintext credential material, request rate identity, and the Alliance/Event/Contribution representations returned by approved scopes.

The plaintext bearer credential is secret. Returned business data may be tenant private and remains owned by Alliances/Events/Contributions.

## 3. Trust boundaries

- Privileged Alliance manager → credential issuance/revocation.
- External machine client → HTTPS API request with bearer credential.
- Credential verifier → credential-derived Alliance context.
- Integrations API → producer-domain tenant-scoped read query.
- API response → external recipient beyond application control.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Plaintext credential recoverable from DB/log | Long-lived tenant compromise | Persistence keeps only required non-secret lookup/verifier state; routine logs never contain bearer value. |
| Client supplies another tenant ID | Cross-tenant disclosure | Tenant is derived from credential; arbitrary tenant selection is absent. |
| Scope escalation/wildcard write authority | Broader data/control access | Fixed read scopes only: `alliance:read`, `events:read`, `contributions:read`; no write/wildcard scope. |
| Revoked/expired credential remains active | Unauthorized persistence | Lifecycle/expiry/verifier checks on each request. |
| Account/Alliance inactive but API still works | Access after lifecycle restriction | Alliance/API availability/entitlement checked before read. |
| Enumeration via auth failures | Tenant discovery | Unknown/malformed/revoked/insufficient credentials fail without revealing hidden tenant state. |
| Unbounded export/history | Data exfiltration/resource abuse | Collection endpoints bounded to 250 rows and approved data states. |
| Hidden Contribution/Kingdoms state exposed | Privacy/contract expansion | Contributions read excludes pending/reversed; no accepted Kingdoms scope. |

## 5. Authorization, tenancy and privacy

Credential possession is the machine authentication/authorization boundary for the fixed scopes. All queries begin from the credential's Alliance and never trust client tenant identifiers.

Credential management remains first-party active-Alliance + `alliance.manage` + recent password confirmation. External API access does not imply browser membership or Platform authority.

## 6. Integrity, replay and concurrency

API reads are idempotent from the application's perspective. Bearer credentials are replayable until revoked/expired, so the risk is bounded through fixed scopes, rate limits, revocation, and tenant derivation rather than pretending each request is one-time.

Concurrent reads cannot alter producer state. Revocation affects subsequent authentication attempts; compromised credentials are replaced, never recovered from storage.

## 7. Secret and data lifecycle

Plaintext credential material is shown only through the controlled issuance flow and is not recoverable from normal persistence. Logs, audit, traces, errors, support output, exports, and documentation exclude it.

Revocation/expiry ends future use. Credential metadata/status may remain as bounded administrative/audit evidence according to Integrations/Platform retention rules.

## 8. Abuse limits and failure behavior

Named API/credential-creation rate limits and row bounds limit brute-force/resource abuse. Invalid credential yields authentication failure; insufficient scope yields authorization failure; disabled/inactive lifecycle state fails closed.

The API does not fall back to a browser's active tenant or grant write operations when a requested read is unavailable.

## 9. Verification and evidence

Tests cover verifier/non-recoverable secret handling, revocation/expiry, fixed scopes, credential-derived tenancy, inactive/disabled Alliance behavior, rate limits, row bounds, Contributions state filtering, and absence of Kingdoms/write scopes.

Shared policy: [Security baseline](../../../../security/security-baseline.md). Historical source: [Phase 6 threat model](../../../../security/phase-6-threat-model.md).

## 10. Residual risks and external controls

Bearer credentials can be copied by an authorized external recipient; application security therefore depends on HTTPS, client secret storage, revocation practice, and bounded scopes. Repository code cannot prove recipient storage or downstream retention.

No OAuth/delegation, write API, per-resource dynamic scope editor, public Kingdoms API, or unbounded historical export is accepted in the current contract.