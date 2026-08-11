# Recruitment domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Recruitment`  
**Primary authorization boundary:** `recruitment.manage`

## 1. Purpose and ownership

Recruitment owns the Alliance recruitment workflow from applicant intake through private candidate review/decision, controlled Memberships onboarding handoff, metrics, and unsuccessful-candidate retention/anonymization.

Recruitment settings are authoritative for application availability; Content may display that state but does not own it.

## 2. Scope

In scope: intake settings/questions, public/invitation-only application submission, private candidate pipeline/review/notes/tags, duplicate handling, decision/onboarding state, metrics/stage aging, and retention/anonymization.

Out of scope: Membership persistence, general RBAC, external mail-delivery guarantees, public candidate disclosure/API/export unless separately approved, and automated applicant scoring.

## 3. Domain model

Recruitment settings define application mode/availability/public copy/retention. Candidate stages include new, screening, contacted, interview, accepted, declined, withdrawn, and joined with private reviewer/workflow state.

The externally reachable intake boundary is documented in [Recruitment application intake](application-intake.md).

## 4. Core invariants

1. Private pipeline state is Alliance scoped.
2. Recruitment settings are authoritative for application availability.
3. Public intake never exposes private pipeline/reviewer state.
4. Historical submitted answers are not silently reinterpreted by later question edits.
5. Candidate merges remain within one Alliance and preserve supported merge relationships.
6. Accepted-candidate membership conversion uses Memberships' supported invitation contract.
7. Retention/anonymization removes identifying/private detail while preserving minimal allowed history.
8. The domain does not create automated applicant/recruiter quality scores.

## 5. Lifecycles and workflows

Public/invitation-only configuration, question rendering, controlled access, and submission are defined in [application-intake.md](application-intake.md).

Private recruiters review candidates, assign reviewers, maintain notes/tags/next actions, transition stages, use supported duplicate merge, prepare decisions, and convert accepted candidates through Memberships invitation/onboarding. Declined/withdrawn candidates receive retention due dates and scheduled anonymization.

## 6. Authorization and tenancy

The private workspace requires authenticated active Alliance context plus `recruitment.manage`; privileged mutations require recent Identity assurance. Submitted candidate/reviewer/tag/related IDs are re-resolved under the active Alliance.

Public intake has its separate privacy/access boundary in [application-intake.md](application-intake.md).

## 7. Cross-domain contracts

Consumes Alliances tenant/public context, Authorization, Memberships invitation/onboarding contract, Content presentation, and Audit/Platform scheduler/outbox infrastructure.

Exposes public-safe application availability/configuration and accepted-candidate handoff without exposing private candidate persistence.

## 8. Persistence and data ownership

Recruitment owns settings/questions, candidate/application/answer state, private notes/tags/reviewers, duplicate/merge state, prepared communication state, onboarding checklist state, retention dates, and derived recruitment metrics. Memberships owns invitations/membership.

## 9. Events, outbox and integrations

Privileged transitions use audit/outbox where required. No public candidate management API/webhook/export is implied by generic Integrations behavior.

## 10. HTTP, UI and API surfaces

Authorized recruiters use the private Recruitment workspace. Applicants use only the public or invitation-only intake surface defined by [application-intake.md](application-intake.md).

## 11. Background processing

A scheduled retention job anonymizes eligible declined/withdrawn candidates. Shared scheduler/operations controls apply; no hidden applicant-scoring worker exists.

## 12. Failure, idempotency and concurrency

Cross-Alliance access/merge fails closed; supported duplicate handling avoids destructive manual deletion. Intake-access failure semantics are defined in [application-intake.md](application-intake.md). Retention runs must remain tenant safe and retryable according to their persisted eligibility state.

## 13. Security and privacy

Candidate answers, notes, tags, reviewer and communication/onboarding state are private Alliance data. Recruiters must not copy private pipeline data into public Content. Public intake exposes only explicitly approved intake fields/questions.

## 14. Observability and operations

Diagnose intake configuration/access, private stage/reviewer state, Memberships onboarding handoff, retention/anonymization, and outbox/audit state separately.

## 15. Testing and architecture enforcement

Tests protect public/private boundaries, stage authorization/audit, intake invitation controls, tenant isolation, duplicate/merge, Memberships handoff, retention/anonymization, and Content ownership separation.

## 16. Explicit non-capabilities

Recruitment does not guarantee external message delivery, expose private candidate/reviewer data publicly, allow cross-Alliance merges, own Membership persistence, or assign automated quality scores.

## 17. Capability documents

- [Recruitment application intake](application-intake.md) — public/invitation-only configuration, questions, controlled access, submission, and privacy boundary.

## 18. Related documentation

- [Memberships](../memberships/README.md)
- [Content](../content/README.md)
- [Authorization](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Operations](../../operations/README.md)
- [`app/Domain/Recruitment/README.md`](../../../app/Domain/Recruitment/README.md)
