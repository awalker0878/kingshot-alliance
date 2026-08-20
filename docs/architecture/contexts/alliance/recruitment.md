# Alliance — Recruitment

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Recruitment`

Recruitment owns Alliance candidate/application behavior up to the controlled handoff into Membership.

## Responsibilities

- recruitment/application intake;
- review and decision behavior;
- recruiter/reviewer authority;
- handoff to Membership when an accepted candidate is eligible to become a member;
- retention/anonymization behavior for recruitment records containing applicant data;
- explicit Alliance consent to appear in public recruitment discovery;
- coarse, visible application-source attribution for conversion measurement.
- bounded, previewed candidate-stage triage with per-candidate outcomes and selective failed-item retry.

## Authority

Alliance recruiters/reviewers act through the active Player and concrete Alliance scope. Public/account-facing intake may know the User account where necessary, but that does not make recruiter authority User-scoped.

## Public discovery boundary

The cross-Alliance recruitment board is composed by `app/ReadModels/RecruitmentDiscovery`. Recruitment remains the owner of application settings and candidate records; the read model joins only the public Alliance identity, Kingdom reference, and opt-in recruitment settings.

An Alliance appears only when all of these conditions are true:

- the Alliance lifecycle status is active;
- applications are open;
- application mode is public;
- an authorized recruiter has explicitly enabled `is_listed`.

The board never exposes candidates, answers, notes, reviewers, conversion counts, or invitation-only links.

Application links may attach one of the bounded sources `recruitment-board`, `alliance-public-page`, or `alliance-share`. The application form displays that attribution before submission. It is stored as ordinary application metadata and is not an identity or cross-session tracking mechanism.

## Boundary

Recruitment does not create a parallel membership model. Once membership is created, `Alliance/Membership` is the authoritative Alliance relationship. Cross-Alliance discovery remains read-only; applications still enter through the Recruitment-owned intake action.

Bulk stage triage accepts at most 50 concrete candidate IDs. Preview authorization and transition checks are repeated by owner actions at commit time. Eligible candidates proceed independently, blocked or stale candidates receive stable result codes, and an aggregate audit receipt complements each successful candidate's stage-history, audit, and outbox evidence. The `joined` transition remains outside bulk triage because it requires the controlled Membership invitation handoff.
