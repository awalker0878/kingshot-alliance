# Recruitment domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Recruitment`  
**Primary authorization boundary:** `recruitment.manage`

## 1. Purpose and ownership

Recruitment owns the Alliance-scoped workflow from applicant intake through review, decision, controlled membership invitation, onboarding, metrics, retention, and anonymization. The workflow is designed so recruiters can operate the pipeline without a separate spreadsheet.

Recruitment settings are the authoritative source for whether Alliance applications are closed, public, or invitation-only.

## 2. Scope

### In scope

- recruitment/application configuration;
- public and invitation-only application intake;
- application questions and historical submitted answers;
- candidate pipeline stages, reviewers, private notes/tags, next actions, and duplicate/merge support;
- controlled accepted/declined decision preparation;
- accepted-candidate conversion into a Memberships-owned invitation and onboarding checklist;
- recruitment metrics and stage aging; and
- retention/anonymization of unsuccessful candidates.

### Out of scope

- Alliance membership persistence itself;
- general role/permission ownership;
- external mail-delivery guarantees;
- public disclosure of candidate/reviewer data;
- generic recruitment export/API contracts unless separately approved; and
- automated applicant quality scoring.

## 3. Domain model

### Recruitment settings

Settings control:

- public versus invitation-only application mode;
- open/closed application state;
- public application title/introduction; and
- unsuccessful-candidate retention period.

### Application questions

Recruiters may configure prompt, help text, type, options, required state, order, and active state.

Question edits affect subsequent application rendering. Historical candidate answers remain attached to the question/candidate records already submitted; a question's meaning should not be changed solely to reinterpret past answers.

### Candidate pipeline

The stage vocabulary is:

- `new`;
- `screening`;
- `contacted`;
- `interview`;
- `accepted`;
- `declined`;
- `withdrawn`; and
- `joined`.

Candidate state may include reviewer assignment, private notes/tags, next-action dates, controlled communication state, duplicate relationships, and onboarding state.

### Invitation-only application links

Invitation-only links are expiring, single-use application access records and may optionally be restricted to one email address.

## 4. Core invariants

1. All private pipeline state is scoped to the active Alliance.
2. Public applicants can see only active Alliance application configuration and active questions required for submission.
3. Public applicants never gain access to private candidate pipelines, reviewer notes, internal metrics, or another applicant's answers.
4. Recruitment settings, not Content, are authoritative for recruitment availability.
5. Invitation-only links expire and are single use.
6. An email-restricted application invitation must be submitted for its intended email address.
7. Candidate merges remain within one Alliance.
8. Accepted-candidate conversion uses the supported Memberships invitation contract rather than taking ownership of invitation persistence.
9. Historical submitted answers are not silently reinterpreted by later question edits.
10. Retention cleanup preserves only the minimal history needed for audit/explainability after anonymization.

## 5. Lifecycles and workflows

### Configure intake

Authorized recruiters can choose application mode, open/close applications, set public title/introduction, configure retention, manage questions, and issue expiring invitation-only links.

### Submit application

Public submission is constrained to the active published application configuration. Invitation-only submissions must satisfy the invitation conditions.

### Review candidate

Recruiters can open a candidate, review submitted details, assign reviewers, add private notes/tags, update workflow state and next action, and prepare controlled communication.

Every privileged transition requires recent password confirmation and remains attributable through the audit/outbox foundation.

### Duplicate handling

Use the supported duplicate finder/merge workflow instead of manually deleting one candidate. The merge relationship is recorded so the active pipeline remains deterministic. Cross-Alliance merges are prohibited.

### Decisions and onboarding

Decision templates support controlled accepted/declined messaging with supported placeholders. The application records prepared/sent communication state rather than claiming an external mail provider delivered the message.

An accepted candidate can be converted into a controlled Alliance membership invitation and onboarding checklist. Mark the candidate `joined` only after the membership/onboarding conditions represented by the workflow are satisfied.

### Metrics

The recruiter dashboard summarizes Alliance-scoped facts including:

- candidate totals/stage counts;
- source counts;
- response-time metrics;
- acceptance/join conversion; and
- stage aging.

These are recorded workflow metrics, not automated quality scores for applicants or recruiters.

### Retention and anonymization

Declined and withdrawn candidates receive a retention due date based on Alliance configuration. A daily scheduled job anonymizes due records by removing applicant answers, private notes, communications, reviewer/tag links, onboarding rows, and identifying candidate fields while preserving minimal historical evidence.

## 6. Authorization and tenancy

The private Recruitment workspace requires active authenticated Alliance context plus `recruitment.manage`. Recruiter mutations require recent password confirmation; read-only pipeline views do not force reconfirmation.

Submitted candidate, reviewer, invitation, tag, and related identifiers are re-resolved under the active Alliance.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active Alliance context and recruitment presentation context.
- **Authorization** — `recruitment.manage`.
- **Memberships** — supported invitation/onboarding transition for accepted candidates.
- **Content** — public page presentation consumes Recruitment's authoritative availability state, not the reverse.
- **Audit/Platform** — attributable audit/outbox/scheduler infrastructure.

### Exposes

- public application availability/configuration safe for the public Alliance page; and
- intentional accepted-candidate handoff into Memberships without exposing private candidate persistence.

## 8. Persistence and data ownership

Recruitment owns settings, questions, candidate/application state, answers, private recruiter notes/tags, reviewer workflow, duplicate/merge state, prepared communication state, onboarding checklist state, retention due dates, and recruitment metrics derived from those records.

Membership invitation/member persistence remains owned by Memberships.

## 9. Events, outbox and integrations

Privileged recruitment transitions are auditable and use the shared transactional-outbox foundation where durable downstream publication is required.

No future recruitment export, public API, or webhook is implicitly approved. Candidate-data disclosure through an external integration requires explicit authorization, audit, privacy, and retention review.

## 10. HTTP, UI and API surfaces

Authorized recruiters use the private **Recruitment** workspace from the active Alliance home page. Public applicants use only the published application surface appropriate to open/public or invitation-only mode.

There is no accepted public candidate-management API.

## 11. Background processing

A daily scheduled retention job anonymizes declined/withdrawn candidates when their retention due date is reached.

The job must remain tenant safe and respect documented retention rules; operators should change retention through approved product configuration/process rather than bypassing cleanup manually.

## 12. Failure, idempotency and concurrency

- If Recruitment is unavailable, verify active Alliance and `recruitment.manage`.
- If a privileged mutation redirects to password confirmation, confirm and retry.
- If an invitation-only application link is not found, it may be invalid, expired, already used, or restricted to another condition; issue a new controlled link rather than recovering the old token.
- Cross-Alliance candidate access/merge attempts fail closed.
- Duplicate handling uses the supported merge relationship instead of destructive manual deletion.

## 13. Security and privacy

Candidate answers, reviewer notes, private tags, communication state, and other applicant data are private Alliance data. Recruiters must not copy them into public Content.

Invitation-only links should not be shared publicly. Direct database edits must not be used for stage changes, merges, or retention cleanup.

## 14. Observability and operations

Operational diagnosis should distinguish public configuration, invitation validity, private pipeline state, scheduled retention/anonymization, and outbox/audit state.

See [Background processing](../../operations/background-processing.md), [Observability](../../operations/observability.md), and the [operations index](../../operations/README.md).

## 15. Testing and architecture enforcement

Tests should protect:

- public/private application boundaries;
- stage-transition authorization/audit;
- invitation-only expiry/single-use/email restrictions;
- cross-Alliance candidate isolation;
- duplicate/merge behavior;
- accepted-candidate Memberships handoff;
- retention/anonymization behavior; and
- the architecture rule that Content does not own recruitment availability and Recruitment does not own Memberships invitation persistence.

## 16. Explicit non-capabilities

Recruitment does not:

- guarantee delivery through an external mail provider merely because communication is marked prepared/sent;
- expose private candidate/reviewer information publicly;
- allow cross-Alliance candidate merges;
- own Alliance membership persistence; or
- assign automated applicant/recruiter quality scores.

## 17. Capability documents

No separate Recruitment capability files are required at present.

## 18. Related documentation

- [Memberships domain](../memberships/README.md)
- [Content domain](../content/README.md)
- [Authorization domain](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Operations index](../../operations/README.md)
- [`app/Domain/Recruitment/README.md`](../../../app/Domain/Recruitment/README.md)
