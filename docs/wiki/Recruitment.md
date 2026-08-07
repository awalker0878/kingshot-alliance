# Recruitment

Phase 4 provides an alliance-scoped recruitment workflow from applicant intake through internal review, decision, membership invitation/onboarding, metrics, and retention.

The detailed guide is [docs/RECRUITMENT.md](../RECRUITMENT.md).

## Recruitment settings

Authorized recruiters can configure:

- public or invitation-only intake;
- whether applications are open;
- application title and introduction;
- unsuccessful-candidate retention period;
- application questions, types, choices, help text, ordering, and required/active state;
- expiring invitation-only application links, optionally restricted to an email address.

Recruitment settings are the authoritative source for the public alliance page's recruitment state. The Content domain does not maintain a duplicate writable recruitment-status field.

## Candidate pipeline

Supported stages are:

- new
- screening
- contacted
- interview
- accepted
- declined
- withdrawn
- joined

Recruiters can assign reviewers, add private notes and tags, set next actions, prepare controlled decision communication, and use duplicate/merge support.

Read-only pipeline views follow normal recruiter authorization. Privileged recruiter mutations additionally require recent password confirmation and produce attributable audit/outbox evidence.

## Public application boundary

Public applicants see only the active alliance's application configuration and active questions needed for submission. They cannot access the private pipeline, recruiter notes, internal metrics, or other applicants' information.

Invitation-only links are expiring and single-use. Email-restricted invitations must be submitted for the intended email address.

## Duplicate handling

Use the duplicate finder and merge workflow rather than manually deleting one record. Merge behavior preserves deterministic workflow history and prohibits cross-alliance merges.

## Decisions and onboarding

Decision templates support controlled accepted/declined messaging. The system records communication state without pretending an external provider delivered a message unless that delivery is actually integrated.

Accepted candidates can be converted into a controlled Phase 1 alliance membership invitation and onboarding checklist. Recruitment does not create a parallel membership system.

## Metrics

Alliance-scoped recruitment metrics include candidate/stage totals, source counts, response time, acceptance/join conversion, and stage aging.

These are workflow facts, not automated quality scores for applicants or recruiters.

## Retention and anonymization

Declined and withdrawn candidates receive a retention due date from alliance settings. A scheduled job anonymizes records after the retention window, removing applicant answers, private notes, communications, reviewer/tag links, onboarding rows, and identifying candidate fields while retaining minimal history needed for audit/explainability.

## Security rules

- Verify the active alliance before acting on a candidate.
- Reconfirm the password for privileged recruiter mutations.
- Keep recruiter notes and candidate data out of public content.
- Do not publish invitation-only links in public channels.
- Do not bypass stage, merge, or retention workflows through direct database edits.

See [Security and Tenancy](Security-and-Tenancy.md), [Phase 4 threat model](../PHASE_4_THREAT_MODEL.md), [Phase 4 operations](../PHASE_4_OPERATIONS.md), and the [Phases 1–4 alignment audit](../PHASES_1_4_ALIGNMENT_AUDIT.md).
