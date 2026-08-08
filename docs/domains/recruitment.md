# Recruitment

## Purpose

Recruitment provides an alliance-scoped workflow from applicant intake through review, decision, invitation, onboarding, and retention. Recruiters should be able to operate the pipeline without a separate spreadsheet.

## Recruiter workflow

Authorized recruiters open **Recruitment** from the active alliance home page. The workspace is visible only when the active membership has recruitment-management permission. Recruiter mutations require recent password confirmation; read-only pipeline views remain available without forcing reconfirmation.

### Configure intake

Recruiters can:

- choose public or invitation-only application mode;
- open or close applications;
- set the public application title and introduction;
- configure the unsuccessful-candidate retention period;
- create application questions;
- edit existing question prompt, help text, type, options, required state, order, and active state;
- issue an expiring invitation-only application link, optionally restricted to an email address.

Question edits affect subsequent application rendering. Historical candidate answers remain attached to the question/candidate records already submitted; recruiters should avoid changing a question's meaning solely to reinterpret past answers.

## Candidate pipeline

The private pipeline supports these stages:

- new
- screening
- contacted
- interview
- accepted
- declined
- withdrawn
- joined

Recruiters can open a candidate to review application details, assign reviewers, add private notes/tags, set workflow state and next actions, prepare controlled decision communication, and use duplicate/merge support where appropriate. Every privileged transition requires recent password confirmation and is attributable through the audit/outbox foundation.

## Public application boundary

Public applicants see only the active alliance application configuration and active questions needed for submission. They cannot read the private candidate pipeline, recruiter notes, internal metrics, or other applicants' answers.

Invitation-only links expire and are single use. An email-restricted invitation must be submitted for its intended email address.

Recruitment settings are the authoritative source for the public alliance page's displayed recruitment state: closed, open for public applications, or invitation-only. The Content domain does not keep a second recruitment-status field.

## Duplicate handling

Use the duplicate finder and merge workflow rather than manually deleting one candidate. A merge records the relationship and keeps the active pipeline deterministic. Cross-alliance merges are prohibited.

## Decisions and onboarding

Decision templates support controlled accepted/declined messaging with supported placeholders. The application records prepared/sent communication state rather than assuming an external mail provider delivered a message.

An accepted candidate can be converted into a controlled alliance membership invitation and onboarding checklist. Mark the candidate joined only after the membership/onboarding conditions represented by the workflow are satisfied.

## Metrics

The recruiter dashboard summarizes alliance-scoped recruitment facts including:

- candidate totals and stage counts;
- source counts;
- response-time metrics;
- acceptance/join conversion;
- stage aging.

Metrics describe recorded workflow data and should not be treated as an automated quality score for applicants or recruiters.

## Retention and deletion

Declined and withdrawn candidates receive a retention due date based on the alliance configuration. A daily scheduled job anonymizes records when that date is reached. The job removes applicant answers, private notes, communications, reviewer/tag links, and onboarding rows and strips identifying candidate fields while preserving minimal history needed for audit/explainability.

If a candidate must be retained longer for a legitimate operational reason, change the documented retention policy through an approved product process rather than bypassing the scheduled purge manually.

## Security rules

- Always verify the correct active alliance before acting on a candidate.
- Reconfirm your password when prompted before privileged recruiter mutations.
- Do not copy recruiter notes or candidate personal data into public alliance content.
- Do not share invitation-only application links in public channels.
- Do not use direct database edits for stage changes, merges, or retention cleanup.
- Any future recruitment export or external integration surface is a candidate-data disclosure boundary and requires explicit authorization, audit, retention, and privacy review before implementation.

## Troubleshooting

If a recruiter cannot see the Recruitment link, confirm the active alliance and that the membership has recruitment-management permission. If a candidate from another alliance appears inaccessible, that is expected tenant isolation rather than an error. If a privileged action redirects to password confirmation, confirm the password and retry the action. If an invitation-only link returns not found, it may be invalid, expired, already used, or associated with a different application condition; issue a new controlled link rather than attempting to recover the old token.

See the [operations index](../operations/README.md) for current runbooks and historical operational evidence, [Identity, tenancy, and membership](identity-tenancy-and-membership.md) for authorization/tenant context, and the [security baseline](../security/security-baseline.md) for the current security and privacy contract.
