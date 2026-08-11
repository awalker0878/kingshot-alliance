# Recruitment application intake

[← Recruitment domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Recruitment

## 1. Purpose

Defines the public and invitation-only applicant intake boundary, including Recruitment settings, configurable questions, applicant submission, and single-use invitation-only access.

This capability is separated from the private recruiter candidate pipeline because it is an externally reachable privacy boundary with different authorization and data-exposure rules.

## 2. Scope and non-scope

In scope:

- authoritative Recruitment open/closed and public/invitation-only settings;
- public application title/introduction;
- active application questions and options/order/required state;
- public applicant submission;
- invitation-only access records, expiry, single-use behavior, and optional email restriction; and
- creation of the private candidate/application state used by the recruiter pipeline.

Out of scope:

- private reviewer notes/tags/metrics;
- decision/onboarding workflow;
- Membership invitation persistence;
- generic public candidate API/export; and
- automated applicant quality scoring.

## 3. Model and state

Recruitment settings define application availability/mode and retention policy. Application questions have prompt/help/type/options/required/order/active configuration.

Invitation-only application access is an expiring single-use record that may be restricted to one email address.

Submitted answers are historical applicant evidence associated with the candidate/application and the question definition used at submission time.

## 4. Invariants

1. Recruitment settings are authoritative for application availability; Content does not own a duplicate writable recruitment-status field.
2. Public applicants see only the active published intake configuration/questions necessary to submit.
3. Private candidate/reviewer state is never exposed through the public intake surface.
4. Invitation-only access expires and is single use.
5. Email-restricted invitation-only access accepts only its intended normalized email.
6. Submitted answers remain historical evidence; later question edits do not silently reinterpret prior answers.
7. Intake state is Alliance scoped even when the entry surface is public.
8. Public submission never grants Alliance membership or recruiter access.

## 5. Workflows

### Configure intake

A recruiter with `recruitment.manage` configures application availability/mode, public copy, active questions, and invitation-only access records through the private management surface.

### Open public application

When the Alliance configuration permits public applications, the public surface renders only safe Alliance presentation plus active Recruitment intake configuration/questions.

### Open invitation-only application

The applicant presents the invitation-only access value. Recruitment validates active state, expiry, single-use status, and any email restriction before allowing the submission flow.

### Submit application

The applicant submits required answers and identifying/contact fields through the bounded intake contract. Recruitment creates the Alliance-owned private candidate/application state and marks invitation-only access consumed where applicable.

### Question changes

Subsequent question edits affect later application rendering. Historical candidate answers remain associated with their original submitted context rather than being silently reinterpreted.

## 6. Authorization, tenancy and privacy

Public intake intentionally does not require Alliance membership, but all resulting candidate/application data is tenant-owned private Recruitment data.

Private intake configuration requires active Alliance context, `recruitment.manage`, and required Identity assurance for privileged mutations.

Invitation-only access values are controlled access material and should not be exposed in public logs/content.

## 7. Persistence and query semantics

Recruitment owns settings, questions, invitation-only intake access, candidates/applications, and submitted answers.

Public queries begin from the requested Alliance/public presentation and expose only currently active intake fields/questions. Private recruiter queries remain behind the management boundary.

## 8. Events, integrations and background processing

Accepted intake transitions may create audit/outbox evidence where required. There is no accepted public applicant-management API/webhook contract.

Retention/anonymization of unsuccessful candidates is documented in the Recruitment root and is deeper `DCP-P2`/`DCP-P3` material, not an intake authorization shortcut.

## 9. Failure, idempotency and concurrency

- Closed applications reject new public submission.
- Invalid/expired/consumed invitation-only access fails closed.
- Email-restricted invitation mismatch fails closed.
- Cross-Alliance invitation/candidate identifiers are not accepted.
- Repeated consumption of one single-use access record must not create multiple successful independent submissions.
- Validation failure does not expose private pipeline state.

## 10. Operations and observability

Operators should distinguish application availability, question configuration, invitation-only access status/expiry/use, submission validation, and candidate creation.

Telemetry should avoid full answer/private applicant payloads unless explicitly required by an approved secure diagnostic process.

## 11. Tests and validation

Tests should cover:

- public versus closed mode;
- public versus invitation-only mode;
- active question rendering/required validation;
- invitation expiry/single-use/email restriction;
- historical answer preservation across question edits;
- private pipeline non-exposure; and
- Alliance isolation.

## 12. Related documentation

- [Recruitment domain](README.md)
- [Memberships](../memberships/README.md)
- [Content](../content/README.md)
- [Authorization](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
