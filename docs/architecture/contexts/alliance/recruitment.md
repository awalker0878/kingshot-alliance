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

## Current lifecycle and write ordering

Public intake stabilizes optional active account identity before Alliance, active Kingdom and current Recruitment settings. Account email, invitation consumption, required answers and candidate/history/delivery records share the owning transaction. Anonymous intake remains supported.

Anonymized candidates are terminal. All candidate mutation owners validate this invariant under the candidate lock, both merge inputs are checked, and management detail and duplicate projections exclude terminal rows. Event-driven joined projection ignores terminal candidates. No global query scope conceals historical records from retention owners.

Communication/onboarding updates discover routing, lock the current scoped candidate, then lock and revalidate the child binding. Retention uses the same candidate-before-child order. Reviewer assignment takes exclusive Alliance scope before actor and target memberships. [ADR-0035](../../adr/0035-current-recruitment-lifecycle-and-target-authority.md) records these boundaries.

Bulk preview and execution enforce one to fifty distinct IDs at the owner boundary. Repeated IDs yield one outcome and one canonical audit selection; current authorization and per-candidate independent commits are retained.

Joined is recorded by the existing `invitation.accepted` projection after matching the candidate's current Accepted stage and captured Player. Manual and bulk owner entrypoints reject Joined even when a pending invitation exists. Delayed events do not reopen Declined, Withdrawn or anonymized candidates, and replay does not duplicate history or delivery.

If current recruiter permission is revoked during bulk execution, each remaining actionable candidate receives a permission-denied failure. Earlier committed changes and the aggregate receipt remain visible; selective retry uses only failed candidate IDs after authority is restored.

## Candidate history reads

RecruitmentManagement owns current authorized candidate-detail composition. Notes, stage history and communications return independent 25-record timestamp/ID pages; duplicate matching returns the same bounded PageSlice contract in oldest-first order. Cursors bind Alliance, candidate and category, and duplicate cursors bind current matching facts. Each request rechecks current authority and terminal state. The page preserves other history sections and unsaved note drafts while navigating. [ADR-0036](../../adr/0036-bounded-recruitment-candidate-history.md) defines the response, current indexes and remaining catalogue/selector audit scope.

## Review text inputs

RecruitmentTextInput defines the existing accepted limits: notes contain 1–10,000 characters after trimming; individual/bulk stage, merge and re-entry reasons are optional and contain at most 5,000 characters after trimming. Unicode characters are counted independently of their UTF-8 byte length. Empty optional reasons are stored as null. Mutation owners validate these inputs before effects, including bulk execution before preview/receipts. HTTP adapters and page limits use the same constants; note errors use the form's body field.

## Private data at retention

Due unsuccessful-candidate anonymization clears the candidate's Player link, source, re-entry control/reason/review/set fields and existing private child data. AuditRecorder redacts the candidate's application-source, candidate-tag and re-entry metadata in the same transaction, preserving event/actor/subject/time evidence with an explicit retention marker. Current reason/review dates remain audited until retention; delivery events carry identifiers and change/presence flags without free-form private values. Both candidate and re-entry detail reject anonymized rows. Membership handoff facts and aggregate stage/milestone dates retain their existing owners. [ADR-0037](../../adr/0037-recruitment-private-retention-and-audit-metadata.md) records the exact boundary.

Recruitment audit details in the governance timeline require current RecruitmentManage in addition to governance admission. The shared ReadModel enforces this for direct, HTTP and Assistant reads before filtering and pagination, including after role revocation (ADR-0038).
