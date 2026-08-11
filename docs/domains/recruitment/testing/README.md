# Recruitment testing and evidence

[← Recruitment domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Recruitment  
**Code owner:** `app/Domain/Recruitment`  
**Primary validation boundary:** Public/invitation-only intake, private candidate workflow, Membership onboarding handoff, tenant isolation, and retention/anonymization  
**P5 evidence decision:** Living suite map with Phase 4 accessibility/migration/security evidence reused

## 1. Critical claims and validation ownership

Recruitment validation must prove public/private disclosure separation, open/invitation-only/closed intake behavior, application token validation, dynamic question/answer integrity, tenant-private candidate/reviewer/note/tag/communication state, duplicate/merge/decision/onboarding workflows, Membership handoff and retention/anonymization eligibility.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. No standalone Recruitment `Performance` threshold or applicant-scoring evidence is accepted.

Feature evidence owns public/private routes; Integration owns workflow/history/onboarding/outbox/retention behavior; TenantIsolation protects candidate/private data; Unit applies to deterministic state/token/retention logic where isolated tests exist.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Recruitment ownership of candidate/application workflow while Memberships owns membership/invitation persistence and Content only consumes safe Recruitment availability for public presentation.

It also protects the absence of automatic scoring/ranking and public candidate API/export contracts.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers anonymous application rate/validation boundaries, invitation-only 64-hex token state, `recruitment.manage`, recent password confirmation, candidate/reviewer/note/tag object re-resolution, cross-Alliance denial and public non-disclosure of the private pipeline.

[Recruitment security](../security/README.md) and [Application intake](../application-intake.md) define the sensitive input/private-data boundary.

## 5. Feature, interface and integration validation

Feature tests cover public application form/submission and private recruiter workspaces. Integration evidence covers stage history, reviewers/notes/tags/communications, duplicate merge, decision/onboarding, Membership invitation handoff, `MarkRecruitmentCandidateJoined` outbox consumption and retention/anonymization.

[Recruitment interfaces](../interfaces/README.md) remains the current interface map.

## 6. Idempotency, concurrency and asynchronous validation

Application token use, candidate conversion/onboarding and Membership handoff must remain duplicate-safe under persisted state. `OutboxPublished` consumption rechecks candidate/membership state before marking joined.

Retention/anonymization is eligibility-driven and safely rerunnable; restoring older state requires retention reconciliation rather than preserving ineligible personal data indefinitely.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 4 exit report](../../../product/phase-4-exit-report.md) records Recruitment migration rollback/reapply and protected acceptance evidence. Current CI continues clean forward migration and database backup/restore.

Recovery and retention behavior is documented in [Recruitment operations](../operations/README.md) and [Retention/anonymization operations](../operations/retention-and-anonymization.md).

## 8. Performance, query and capacity evidence

Recruitment has bounded public throttling and scheduler batch limits but no accepted general query/latency/throughput SLA. Pipeline metrics/aging are correctness/reporting behavior rather than a performance score.

## 9. Accessibility and frontend evidence

[Phase 4 accessibility review](../../../product/phase-4-accessibility.md) and source guards cover public application and recruiter pipeline/detail/settings/decision/onboarding surfaces.

Current `npm run check` protects frontend quality but does not replace deployment-specific accessibility validation.

## 10. Historical accepted evidence

Primary historical evidence is [Phase 4 exit report](../../../product/phase-4-exit-report.md). Technical head `27c6822593d7d54bddbc360dcea1a104ba5dadba` passed DR `31205805866`, CodeQL `31205806726`, CI `31205805622`; acceptance-record head `d35ba746f405a4b41c310a406c41ce5c27a70091` passed DR `31206163527`, CodeQL `31206164215`, CI `31206163505`.

## 11. Evidence identity, retention and supersession

Phase 4 SHA/run identities and historical counts remain immutable. Current Recruitment validation follows current code/tests and this profile.

Future acceptance evidence must preserve exact SHA/protected-run identity under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Recruitment has no public candidate-management/export API, automatic applicant scoring/ranking, cross-Alliance candidate merge/access, or guarantee that mail transport success equals workflow completion. No standalone performance SLA is claimed.

Related documentation:

- [Recruitment domain](../README.md)
- [Application intake](../application-intake.md)
- [Recruitment security](../security/README.md)
- [Recruitment operations](../operations/README.md)
- [Recruitment interfaces](../interfaces/README.md)
- [Memberships testing](../../memberships/testing/README.md)
- [Content testing](../../content/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
