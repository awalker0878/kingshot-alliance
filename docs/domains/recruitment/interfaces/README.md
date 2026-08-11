# Recruitment interfaces

[← Recruitment domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Recruitment  
**Code owner:** `app/Domain/Recruitment`  
**Primary boundary:** Public/invitation-only applicant intake, private Alliance recruitment management, Membership onboarding handoff, and retention coordination  
**P4 inventory decision:** Focused contract reused — `../application-intake.md`

## 1. Boundary purpose and ownership

Recruitment owns the applicant/candidate workflow boundary from public or invitation-only application intake through private manager review/decision/onboarding coordination and eventual retention/anonymization.

The anonymous/external input boundary is intentionally separated from the private candidate workspace. Memberships owns accepted-candidate invitation/membership creation; Content may present application availability but does not own it.

## 2. Surface inventory

Public first-party routes in `routes/web.php` are:

- `GET /alliances/{slug}/apply` — application form/configuration/questions;
- `POST /alliances/{slug}/apply` — application submission, throttled by `recruitment-application`.

Private active-Alliance surfaces include recruitment index/candidate detail and privileged settings/questions/application-invites/decision-templates/onboarding-items/stage/reviewer/note/tag/merge/communication/conversion/onboarding mutations.

The public/intake contract is defined in [Recruitment application intake](../application-intake.md).

## 3. Callers, authorization and tenancy

Anonymous or authenticated applicants may use the public route only when Recruitment settings allow the applicable application mode. The POST limiter is 3 requests/minute by normalized email + IP.

Invitation-only mode requires a valid unused/unexpired 64-hex application token bound to the active Alliance and hashed persisted invite. Token-bound email may be locked into the form prefill.

Private recruiter work requires authenticated, verified active-Alliance context plus `recruitment.manage`; privileged mutations require recent password confirmation.

## 4. Input and validation contracts

Public submission validates:

- `full_name` required, max 160;
- RFC email required, max 320;
- optional contact handle, max 160;
- optional source, max 120;
- optional `application_token`, exactly 64 characters; and
- answers array interpreted against current active Recruitment questions under the accepted intake contract.

Historical submitted answers are not silently reinterpreted by later question edits. Private candidate/reviewer/tag/merge/onboarding identifiers are tenant-resolved and validated by owning actions.

## 5. Output and disclosure contracts

Public GET exposes only active Alliance public identity needed for the form, authoritative Recruitment open/mode/title/introduction state, active public questions, and safe prefill values. It does not disclose candidate pipeline, reviewers, notes, tags, decisions, communications, or onboarding state.

Private recruiters receive the tenant-authorized pipeline/workspace payload. There is no accepted public candidate API/export schema.

## 6. Internal actions, queries and services

Supported contracts include application token validation/submission, private candidate workflow actions/queries, duplicate handling, stage/decision/onboarding actions, retention/anonymization, and accepted-candidate Membership invitation handoff.

Content may consume safe Recruitment availability for public presentation. Memberships consumes accepted candidate identity through the supported invitation/onboarding contract rather than sharing Recruitment persistence ownership.

## 7. Events, outbox and cross-domain consumers

Material Recruitment transitions may record Audit/Platform-outbox evidence. `AppServiceProvider` registers `MarkRecruitmentCandidateJoined` as an internal `OutboxPublished` consumer so accepted Membership handoff can reconcile candidate joined state.

This internal consumer is not a public Recruitment webhook contract. Integrations external eligibility remains independent, and private candidate data is not made public by generic outbox publication.

## 8. Commands, jobs and scheduled work

`recruitment:purge-expired {--limit=100}` invokes retention/anonymization of eligible unsuccessful candidates. The scheduler runs `recruitment:purge-expired --limit=250` daily at 03:15 with one-server/overlap protection.

Safe recovery/reconciliation is based on persisted eligibility and is documented in [Recruitment operations](../operations/README.md) and [Retention/anonymization runbook](../operations/retention-and-anonymization.md).

## 9. Files, imports, exports and external dependencies

Recruitment has no accepted public/private candidate file import or candidate export contract. Application intake is an HTTP form contract, not a generic JSON/file ingestion API.

Externally relevant dependencies may include configured mail delivery for communications/invitations plus Memberships handoff; mail transport success is not itself candidate/membership state authority.

## 10. Failure, idempotency, versioning and compatibility

Inactive Alliance, closed applications, invalid invitation mode/token, throttling, validation, tenant mismatch, or insufficient recruiter permission fails closed. Public submission behavior and invitation token semantics follow [Recruitment application intake](../application-intake.md).

Candidate conversion/handoff uses supported Memberships idempotency rather than direct membership row creation. Retention retries re-evaluate persisted eligibility.

The public application form has no numeric version, but its token/access/question/input/privacy semantics are compatibility-sensitive first-party behavior.

## 11. Explicit non-capabilities

Recruitment does not:

- expose private candidate/reviewer/note/tag/communication data publicly;
- provide a public candidate management/export API;
- automatically score/rank applicants;
- own Membership persistence;
- guarantee external mail delivery as workflow completion; or
- allow cross-Alliance candidate merge/reviewer access.

## 12. Focused contracts, evidence and related documentation

P4 reuses [Recruitment application intake](../application-intake.md) for the complete public/invitation-only boundary.

Related documentation:

- [Recruitment domain](../README.md)
- [Recruitment application intake](../application-intake.md)
- [Recruitment security](../security/README.md)
- [Recruitment operations](../operations/README.md)
- [Retention/anonymization operations](../operations/retention-and-anonymization.md)
- [Membership invitations](../../memberships/invitations.md)
- [Content interfaces](../../content/interfaces/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
