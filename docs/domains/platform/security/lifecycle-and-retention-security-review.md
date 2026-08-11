# Platform lifecycle and retention security review

[← Platform security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Platform  
**Capability:** Alliance/account lifecycle, legal hold, retention, anonymization and administrative export  
**Code owner:** `app/Domain/Platform`

## 1. Scope and security objective

Protect cross-tenant lifecycle/data-governance operations so suspend/close/delete/restore, ownership transfer, legal holds, account deletion/anonymization, retention cleanup, and tenant-complete exports can occur only under dedicated Platform authority and cannot bypass domain ownership or preservation constraints.

## 2. Assets and sensitive data

Assets include Platform-admin grants, Alliance lifecycle/retention deadlines, legal holds, ownership targets, account-deletion eligibility/cooling-off state, tenant-owned rows affected by orchestration, export files/evidence/checksums/counts, and retained/pseudonymized historical identifiers.

These operations can expose or destroy data across domains and therefore are high-impact security/privacy controls.

## 3. Trust boundaries

- Verified/MFA-backed Platform administrator + recent password → cross-tenant lifecycle UI/action.
- Platform orchestration → Alliances/Memberships/Authorization/Identity/feature-domain contracts.
- Scheduler/maintenance → retention/account-deletion eligibility.
- Tenant row discovery → administrative export/redaction pipeline.
- Export result → privileged administrator outside normal tenant/member boundary.

Platform authority is separate from `AllianceContext`.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Alliance Owner/role reaches Platform lifecycle | Cross-tenant privilege escalation | Dedicated Platform-admin grant plus verified identity, confirmed MFA and recent password; no Alliance-role substitution. |
| Delete active tenant directly | Irrecoverable/unsafe destruction | Logical deletion requires prior `closed` state and documented transition guards. |
| Delete despite legal hold | Evidence/privacy/legal violation | Active hold blocks applicable destructive processing. |
| Restore after retention deadline | Revive data outside approved lifecycle | Restoration fails closed after deadline. |
| Ownership transfer to wrong/cross-tenant member | Tenant takeover | Target must be active membership in same Alliance. |
| Account deletion bypasses ownership/admin/hold obligations | Lockout/evidence loss | Eligibility checks include Platform-admin, active ownership and legal-hold restrictions plus cooling-off. |
| Export leaks secrets/verifiers | Credential compromise | Known secret/verifier fields redacted; export is tenant-scoped, bounded and attributable. |
| Export silently misses/overincludes tenant data | Incomplete disclosure or cross-tenant leak | Tenant-row discovery by `alliance_id`, schema/version/checksum/count evidence, synchronous safety bound. |
| Retention cleanup rewrites feature semantics | Integrity/history loss | Feature domains retain semantic ownership; Platform coordinates only approved lifecycle rules. |

## 5. Authorization, tenancy and privacy

All lifecycle/export operations require dedicated cross-tenant Platform authority; switching into the target Alliance is not the source of authority. Privileged actions require the Identity assurance documented by the Platform boundary.

Legal hold, redaction, anonymization, and retention are privacy controls. Orchestration must preserve each domain's distinction between removable identifying detail and legitimately retained history/evidence.

## 6. Integrity, replay and concurrency

Sensitive lifecycle rows are locked during transitions. Repeated operations re-evaluate current state/hold/deadline rather than assuming the prior UI state still applies. Delete/restore/transfer/account-delete/export fail without partial unsupported transitions when preconditions are not satisfied.

Outbox/audit evidence is recorded with the accepted transition rather than replaying source changes to recreate evidence.

## 7. Secret and data lifecycle

Administrative export redacts known secret/verifier columns and must not become a route to passwords, MFA/recovery, invitation/API/webhook secrets, or application keys. Export files themselves are sensitive and follow protected storage/download/retention handling.

Account anonymization removes identifying account detail while preserving only pseudonymized/minimal history permitted by owning-domain contracts. Legal holds suspend otherwise eligible destructive processing.

## 8. Abuse limits and failure behavior

Lifecycle state/hold/deadline checks fail closed. Tenant-complete synchronous export enforces the implemented size bound rather than monopolizing request/runtime resources. Cross-tenant ownership targets and ineligible account-deletion requests are rejected.

Operators must not bypass failures with direct SQL deletes, hold removal, or manual row rewrites as a routine recovery path.

## 9. Verification and evidence

Tests cover Platform-admin assurance, lifecycle transition rules, active-tenant/API gating, legal-hold blocking, delete/restore deadlines, same-Alliance ownership transfer, account-deletion eligibility/anonymization, retention behavior, export tenant scope/redaction/bounds/evidence, and Audit/outbox attribution.

Shared policy: [Security baseline](../../../security/security-baseline.md). Historical source: [Phase 6 threat model](../../../security/phase-6-threat-model.md).

## 10. Residual risks and external controls

Repository logic cannot prove real operator identities, approval procedures, export-file custody, backup copies, legal-hold governance, or whether external storage systems retain data after logical deletion. Production runbooks/process evidence must align with this contract.

Deletion/anonymization also cannot guarantee erasure from external recipients or prior authorized exports; those systems require their own retention/governance controls.