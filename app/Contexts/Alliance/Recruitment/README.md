# Recruitment domain

## Purpose

Owns Alliance recruitment intake, application questions/settings, candidate pipeline/review/decisions, controlled onboarding handoff, metrics, and unsuccessful-candidate retention/anonymization.

## Owned code

Runtime code in this module owns Recruitment settings/questions/candidates/answers/private review state, decision/onboarding/retention workflows, and recruiter/public application surfaces.

## Public contracts

- authoritative public/invitation-only recruitment availability/configuration;
- private recruiter candidate-management workflow under `recruitment.manage`; and
- accepted-candidate handoff into the supported Memberships invitation contract.

## Dependencies

- `Alliances` — active tenant/public Alliance context.
- `Authorization` — `recruitment.manage`.
- `Memberships` — controlled membership invitation/onboarding transition.
- `Identity` — actor/password assurance.
- `Audit` / Platform outbox — privileged/durable evidence.

Content may display Recruitment availability but does not own a duplicate writable recruitment-status field.

## Canonical documentation

- [`docs/architecture/contexts/alliance/recruitment.md`](../../../../docs/architecture/contexts/alliance/recruitment.md)

Candidate detail is composed by `ReadModels/RecruitmentManagement/Queries/RecruitmentCandidateDetailQuery`; the Recruitment candidate controller only adapts mutations. The duplicate owner query returns a scoped 25-record PageSlice, matching the bounded note/history/communication pages. See [ADR-0036](../../../../docs/architecture/adr/0036-bounded-recruitment-candidate-history.md).

Review text is bounded in RecruitmentTextInput at each mutation owner: notes allow 10,000 characters, optional stage/bulk/merge/re-entry reasons 5,000, with trimmed Unicode-aware lengths and null empty reasons. The HTTP and page contracts use the same limits.

Unsuccessful-candidate retention includes Player/source/re-entry fields and scoped audit-metadata redaction through AuditRecorder. Private reason/date/source values are excluded from delivery events; candidate and re-entry detail reject terminal rows. Audit chronology and independent Membership/Player facts remain.

RecruitmentInput defines the configuration/intake text, option, position, retention and invitation limits used by owners, HTTP validation and forms. Direct writes reject invalid values before effects; short answers are limited to 240 characters and long answers to 10,000, while multi-select answers accept each configured choice once (maximum 30). Optional blank text remains absent. Management form and row errors preserve drafts. See ADR-0040. Catalogue and attachment cardinality work remains tracked under HARD-088.
