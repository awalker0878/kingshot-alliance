# Recruitment security profile

[← Recruitment domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Recruitment  
**Code owner:** `app/Domain/Recruitment`  
**Primary security boundary:** public/invitation-only applicant intake separated from tenant-private recruiter pipeline data and retention/anonymization state

## 1. Security purpose and scope

Recruitment protects applicant personal data across intake, private recruiter review, duplicate/merge, decisions, Memberships onboarding handoff, and unsuccessful-candidate retention/anonymization.

The anonymously/invitation reachable input boundary is reviewed separately in [Application intake security review](application-intake-security-review.md).

## 2. Assets and sensitive data

Assets include Recruitment settings/questions, applicant identity/contact details, submitted answers, private recruiter notes/tags/reviewer assignments, stage/decision/next-action state, prepared communication/onboarding state, duplicate/merge relationships, and retention/anonymization dates.

Candidate answers and reviewer notes are private tenant data. Public application availability/copy/questions are intentionally public only when configured. Invitation-access material is controlled access data and is not a generic public candidate link.

## 3. Actors, authentication and authorization

Anonymous or invitation-authorized applicants may use only the approved intake surface. Private recruiter workflows require authenticated verified active-Alliance context plus `recruitment.manage` and recent Identity assurance for privileged mutations.

Accepted-candidate conversion uses Memberships' invitation contract; recruiter authority never becomes direct membership-persistence authority.

## 4. Tenant and privacy boundaries

Candidate/reviewer/tag/note/merge/onboarding/retention state is Alliance scoped. Submitted identifiers and duplicate-merge targets are re-resolved under the active Alliance; cross-Alliance merge/access fails closed.

Content may present Recruitment-owned public availability but must never copy private pipeline/answers/notes into public authored content.

## 5. Trust boundaries and data flows

Material boundaries include anonymous/invitation applicant → intake endpoint, intake submission → private Recruitment persistence, recruiter browser → private pipeline, accepted candidate → Memberships invitation handoff, scheduler → retention/anonymization, and public Content → Recruitment-owned availability state.

No current public candidate management API/export/webhook boundary exists.

## 6. Threats, abuse cases and controls

Threats include applicant enumeration/spam, bypassing recruitment mode/invitation access, overbroad question/answer collection, public disclosure of private pipeline data, cross-tenant candidate access/merge, unauthorized recruiter mutation, silent reinterpretation of historical answers after question edits, direct membership creation, retention bypass, and sensitive notes in logs/audit/outbox.

Controls include rate-limited bounded intake, authoritative settings/mode checks, controlled invitation access, tenant-scoped private queries, `recruitment.manage`, historical answer preservation, same-Alliance merge constraints, Memberships invitation handoff, scheduled persisted retention eligibility, anonymization, and payload minimization.

## 7. Integrity, concurrency and idempotency

Historical submitted answers retain the question context required for interpretation; later question edits do not silently mutate prior meaning. Candidate merge uses supported relationships within one Alliance rather than destructive deletion.

Retention runs operate from persisted eligibility/due state and must be safe to retry. Membership onboarding uses the invitation lifecycle so repeated recruiter actions cannot bypass Memberships concurrency/idempotency rules.

## 8. Secrets and credential handling

Recruitment owns no password/MFA/API/webhook secret. Invitation-only intake access material must not be logged or copied into public content. Applicant answers/notes are private data, not secret storage, and should never contain credentials/recovery material by design.

Membership invitation token handling remains Memberships-owned.

## 9. Destructive operations, retention and deletion

Declined/withdrawn candidate retention is explicit persisted state. Scheduled anonymization removes identifying/private detail when due while preserving the minimal history allowed for workflow integrity/metrics/evidence.

Legal hold/account/Alliance lifecycle orchestration is Platform-owned and may block or coordinate destructive work; Recruitment remains semantic owner of candidate/anonymization rules.

## 10. Auditability, observability and evidence

Privileged stage/decision/merge/onboarding/retention-relevant transitions are attributable where required using safe metadata. Operators distinguish intake access/configuration, private workflow authorization, duplicate/merge state, Memberships handoff, and retention/anonymization.

Tests protect public/private separation, intake controls, tenant isolation, stage/decision authorization, merge constraints, onboarding boundary, and retention/anonymization. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Applicants can voluntarily submit sensitive free text; product/question design and recruiter practice must minimize unnecessary collection beyond technical access controls. Repository code cannot prove external mailbox/device privacy for invitation links or prepared communications.

Recruitment does not expose private candidate data publicly, guarantee external message delivery, perform cross-Alliance merges, own Membership persistence, or automate applicant/recruiter quality scoring.

## 12. Focused reviews and related documentation

- [Application intake security review](application-intake-security-review.md)
- [Application intake contract](../application-intake.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Content security profile](../../content/security/README.md)
- [Platform security profile](../../platform/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 4 threat model](../../../security/phase-4-threat-model.md)
