# Memberships testing and evidence

[← Memberships domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Memberships  
**Code owner:** `app/Domain/Memberships`  
**Primary validation boundary:** Invitation token lifecycle/acceptance, Alliance membership lifecycle, role-adapter safety, and last-Owner coordination  
**P5 evidence decision:** Living suite map with Phase 1 invitation/membership evidence reused

## 1. Critical claims and validation ownership

Memberships validation must prove secure invitation issuance/resend/revocation/acceptance, email-bound tenant-safe acceptance, membership lifecycle/rejoin/leave behavior, active membership as tenant context input, and safe coordination with Authorization hierarchy/last-Owner rules.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. No standalone Memberships `Performance` threshold is accepted.

Feature owns first-party invitation/membership routes; Integration owns transactional invitation/membership/outbox behavior; TenantIsolation protects cross-Alliance identifiers; Unit applies to deterministic lifecycle/token/state logic where isolated tests exist.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Memberships ownership of User↔Alliance relationship state while Identity owns authentication, Alliances owns active tenant context, and Authorization owns role/permission semantics.

Role-management HTTP adapters may live under Memberships, but tests must preserve Authorization as the semantic role owner.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers `invitations.manage`/`membership.manage`, password-confirmed manager mutations, 64-hex bearer token handling, wrong-email/expired/revoked/used token denial, cross-Alliance membership/role substitution and last-Owner safety.

[Memberships security](../security/README.md) and [Membership invitations](../invitations.md) define the token/privacy boundary.

## 5. Feature, interface and integration validation

Feature tests cover invitation landing/acceptance, manager invitation administration, membership status, role adapters and self-leave/rejoin behavior. Integration evidence covers token rotation/replacement, Memberships↔Authorization collaboration, Recruitment accepted-candidate handoff and Audit/outbox evidence.

[Memberships interfaces](../interfaces/README.md) remains the current interface inventory.

## 6. Idempotency, concurrency and asynchronous validation

Phase 1 hardened duplicate role assignment as a no-op, legitimate remove/reassign cycles as distinct transitions, repeated leave/rejoin/leave events without outbox-key collision, serialized invitation replacement, and invitation acceptance concurrency/idempotency.

Possession of a token alone never becomes membership authorization; retries re-evaluate persisted invitation and authenticated-email state.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 1 exit report](../../../product/phase-1-exit-report.md) records the foundational Memberships/invitation/role persistence and protected migration/recovery gate. Current CI continues clean forward migration and backup/restore.

Operational recovery remains in [Memberships operations](../operations/README.md); direct row edits that bypass invitation/membership lifecycle are not accepted recovery evidence.

## 8. Performance, query and capacity evidence

No standalone Memberships query budget or latency SLA is accepted. Plan/member quota enforcement is a correctness/entitlement invariant tested in feature/integration flows, not an invented performance target.

## 9. Accessibility and frontend evidence

Invitation/membership administration formed part of [Phase 1 accessibility review](../../../product/phase-1-accessibility-review.md). Current `npm run check` protects frontend quality but does not replace deployment-specific accessibility validation.

## 10. Historical accepted evidence

Primary evidence is [Phase 1 exit report](../../../product/phase-1-exit-report.md), including validated implementation/evidence head `ca3d5ad851ec88a0ef127817a3fbf670f7a0352c` and its protected workflow identities.

[Phase 4 exit report](../../../product/phase-4-exit-report.md) additionally proves Recruitment→Membership onboarding/invitation handoff without changing Memberships ownership.

## 11. Evidence identity, retention and supersession

Phase 1/4 evidence remains immutable historical context. Living membership testing maps change only with current code/tests.

Future acceptance evidence must record exact SHA/run identities under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Memberships has no public management API, token-possession-only authorization, cross-Alliance role assignment, or Membership-owned permission vocabulary. No standalone performance SLA is claimed.

Related documentation:

- [Memberships domain](../README.md)
- [Membership invitations](../invitations.md)
- [Memberships security](../security/README.md)
- [Memberships operations](../operations/README.md)
- [Memberships interfaces](../interfaces/README.md)
- [Authorization testing](../../authorization/testing/README.md)
- [Recruitment testing](../../recruitment/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
