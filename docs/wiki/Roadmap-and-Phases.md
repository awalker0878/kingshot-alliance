# Roadmap and Phases

Kingshot Alliance uses phase-gated delivery. A phase is considered complete only when its functionality is implemented end to end, tested, documented, observable, secure, tenant-safe, deployable, and accepted.

The canonical program definition is [docs/IMPLEMENTATION_PLAN.md](../IMPLEMENTATION_PLAN.md). The current integrated state is captured by the [Phases 1–4 alignment audit](../PHASES_1_4_ALIGNMENT_AUDIT.md).

## Current status

| Phase | Outcome | Status |
|---|---|---|
| 0 | Engineering foundation | Complete |
| 1 | Identity and multi-tenancy | Complete |
| 2 | Content and public presence | Complete |
| 3 | Events and rallies | Complete |
| 4 | Recruitment | Complete |
| 5 | Contributions and reporting | Next / not implemented |
| 6 | Platform scale and administration | Planned |

## Phase 0 — Engineering foundation

Established the Laravel/Vue/PostgreSQL/Redis foundation, Docker development environment, CI quality/security gates, testing framework, modular domain conventions, ADRs, structured logging, health checks, backup/restore, deployment controls, security baseline, and repository governance.

Reference: [Phase 0 exit report](../PHASE_0_EXIT_REPORT.md).

## Phase 1 — Identity and multi-tenancy

Implemented the platform security boundary:

- registration, authentication, verification, recovery, sessions, and profile management;
- alliance creation and active-alliance context;
- global users with many alliance memberships;
- fixed alliance roles and permissions;
- invitation lifecycle;
- MFA foundation;
- audit and transactional outbox foundations;
- tenant-isolation enforcement.

Reference: [Phase 1 exit report](../PHASE_1_EXIT_REPORT.md).

## Phase 2 — Content and public presence

Implemented alliance public/member content and controlled publishing:

- public alliance profile and branding;
- announcements, guides, rules, and reference content;
- draft/published content, revisions, scheduling, archival, search, and filtering;
- media upload and lifecycle controls;
- public vs member-only visibility.

Recruitment availability is no longer owned by this domain; Phase 4 is authoritative.

Reference: [Phase 2 exit report](../PHASE_2_EXIT_REPORT.md).

## Phase 3 — Events and rallies

Implemented alliance operations for scheduled activities:

- one-time and recurring events;
- registration, waitlist, cancellation, attendance, and no-shows;
- time-zone-safe recurrence;
- reminder delivery through the outbox pipeline;
- configurable rally guidance and formations;
- rally groups, lead/joiner assignments, standby handling, and participation;
- authenticated CSV/iCalendar exports.

Reference: [Phase 3 exit report](../PHASE_3_EXIT_REPORT.md) and [Events and Rallies](Events-and-Rallies.md).

## Phase 4 — Recruitment

Implemented the recruitment lifecycle:

- public and invitation-only intake;
- configurable questions and recruitment settings;
- candidate stages, reviewers, notes, tags, and next actions;
- duplicate detection and merge workflow;
- controlled decisions and communications;
- accepted-candidate conversion into Phase 1 membership invitations/onboarding;
- recruitment metrics;
- retention and anonymization of unsuccessful candidates.

Reference: [Phase 4 exit report](../PHASE_4_EXIT_REPORT.md) and [Recruitment](Recruitment.md).

## Integrated Phase 1–4 ownership

The integration audit resolved four important cross-phase issues:

1. Removed the premature Phase 5 `contributions.manage` permission.
2. Removed duplicated recruitment status from the Content domain; Recruitment is authoritative.
3. Removed the obsolete Phase 2 placeholder that said event schedules would arrive in Phase 3.
4. Applied recent password confirmation consistently to privileged Phase 3 and Phase 4 mutations.

No compatibility shims or duplicated state were retained for these pre-production boundaries.

## Phase 5 — Contributions and reporting

This is the next product phase and is **not implemented yet**. Planned scope includes configurable contribution categories/goals, member reporting and approval workflows, participation-derived facts, dashboards, leaderboards with transparent calculation rules, exports, scheduled reports, data-quality workflows, and calculation versioning.

Do not introduce Phase 5 permissions, tables, routes, scoring logic, or placeholders until the phase is intentionally started.

## Phase 6 — Platform scale and administration

Planned for multi-alliance operational scale: platform administration, alliance lifecycle, feature flags, quotas, API/webhook management, performance work, retention controls, disaster recovery, and launch hardening.

## Gate rule

Every phase must pass the repository's normal quality, security, tenant-isolation, accessibility, migration/rollback, observability, documentation, staging, and acceptance gates before the next phase becomes authoritative.
