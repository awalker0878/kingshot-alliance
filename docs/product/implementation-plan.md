# Kingshot Alliance Program Implementation Plan

**Status:** Approved baseline — Phases 0–6 complete  
**Repository:** `awalker0878/kingshot-alliance`  
**Delivery model:** Phase-gated, production-ready increments  
**Target platform:** Enterprise modular monolith built with Laravel  
**Current state:** Repository-controlled production hardening is accepted; real production cutover remains not yet approved. See [production launch approval](production-launch-approval.md).

## 1. Purpose

This plan converts the Kingshot Alliance master blueprint into an executable program of work. It establishes the product scope, target architecture, delivery phases, governance, quality controls, security expectations, release approach, and measurable exit criteria required to build an enterprise-ready, multi-alliance platform.

The plan is intentionally phase-gated. A phase is complete only when its functions are implemented end to end, tested, documented, observable, secure, tenant-safe, deployed to staging, and accepted. Future-phase capabilities must not be partially introduced as placeholders.

## 2. Product vision

Kingshot Alliance will provide one secure platform through which multiple Kingshot alliances can manage their public presence, members, events, rallies, recruitment, contributions, communications, and operational reporting.

The product should replace fragmented chat messages, spreadsheets, screenshots, and manual reminders with a shared operational system that is easy for regular members to use and powerful enough for alliance leadership.

### Intended outcomes

1. Give each alliance an isolated, configurable workspace.
2. Allow one global user account to participate in multiple alliances.
3. Improve event participation through schedules, reminders, formations, rally guidance, and attendance tracking.
4. Reduce leadership workload by centralizing recruitment, membership administration, announcements, contribution tracking, and reporting.
5. Provide a scalable foundation for integrations, platform administration, and optional commercial features.
6. Protect alliance data through strong authorization, auditability, tenant isolation, backup, and recovery controls.

## 3. Scope

### Initial program scope

- Global user registration, authentication, profile management, and account recovery.
- Alliance creation, configuration, branding, invitations, and membership lifecycle.
- Alliance-scoped roles and permissions.
- Public alliance pages, guides, announcements, and content management.
- Event calendar, registration, reminders, attendance, recurring schedules, and time-zone handling.
- Rally and formation guidance, rally lead/joiner configuration, troop-ratio recommendations, and event instructions.
- Recruitment pipeline, applications, reviews, notes, decisions, and onboarding.
- Contribution and participation records, goals, leaderboards, exports, and reporting.
- In-app and email notifications, with an integration-ready notification model.
- Platform administration, audit, support, operational monitoring, and alliance lifecycle management.
- API and integration foundations for future game-data, Discord, webhook, and bot capabilities.

### Deferred until validated

- Automated collection of Kingshot game data where no approved or reliable interface exists.
- Native mobile applications; the first release will use a responsive web application and may add progressive-web-app capabilities.
- Real-money transactions, marketplace functions, or regulated payment flows.
- Advanced billing beyond the minimum platform foundation needed to support future plans.
- Cross-alliance competitive rankings unless alliances explicitly opt in.
- AI-generated operational decisions without human review.

## 4. Guiding architecture decisions

The baseline architecture is an enterprise modular monolith rather than premature microservices. Domain boundaries must be explicit so high-volume or independently scaled capabilities can later be extracted without rewriting the core product.

### Technology baseline

- **Application:** Current supported Laravel release and PHP release.
- **Frontend:** Inertia.js with Vue and TypeScript.
- **Database:** PostgreSQL.
- **Cache, queues, locks, and rate controls:** Redis.
- **Queue operations:** Laravel Horizon.
- **Application telemetry:** Laravel Pulse plus OpenTelemetry-compatible metrics and traces.
- **Authentication:** Laravel Sanctum for first-party web and API access.
- **Feature rollout:** Laravel Pennant.
- **Object storage:** S3-compatible storage through Laravel filesystem abstractions.
- **Local and deployment packaging:** Docker.
- **Continuous integration:** GitHub Actions.

### Architectural principles

- Alliance-level multi-tenancy with global users and alliance memberships.
- Tenant identity resolved explicitly and carried through authorization, queries, jobs, notifications, logs, cache keys, exports, and storage paths.
- Policies and permission services as the authoritative access-control layer.
- Scoped route binding and query objects that fail closed.
- Thin controllers; business logic implemented through actions, services, commands, and queries.
- Domain events for meaningful business changes.
- Transactional outbox for reliable asynchronous side effects.
- Idempotent queued jobs and integration handlers.
- UTC storage for time values with explicit user and alliance time zones.
- Accessible, responsive, keyboard-operable interfaces.
- Secure defaults, minimal privileges, and complete auditability for privileged actions.

## 5. Program workstreams

The program will run through coordinated workstreams rather than treating implementation as only a feature backlog.

| Workstream | Accountability | Primary outputs |
|---|---|---|
| Product and alliance operations | Product owner | Product outcomes, personas, workflows, priorities, acceptance decisions |
| Architecture and domain design | Technical lead | ADRs, domain boundaries, data model, integration contracts, non-functional requirements |
| Application engineering | Engineering team | Backend, frontend, database migrations, queues, APIs, tests |
| Experience and accessibility | Product/design lead | Navigation, design system, responsive layouts, accessibility validation, content patterns |
| Security and privacy | Security owner | Threat model, access-control review, secret handling, audit, incident requirements |
| Platform and operations | Platform owner | Environments, CI/CD, observability, backups, recovery, runbooks, deployment controls |
| Quality engineering | Quality owner | Test strategy, automation, performance checks, regression evidence, release acceptance |
| Documentation and enablement | Documentation owner | Repository guides, user help, domain READMEs, support procedures, release notes |

A small team may combine roles, but the accountabilities remain explicit.

## 6. Delivery governance

### Stage-gate model

Every phase follows the same lifecycle:

1. **Initiate:** Confirm outcome, owner, scope, dependencies, risks, and acceptance criteria.
2. **Design:** Approve data model, authorization model, user flows, API boundaries, observability, and operational impact.
3. **Build:** Implement complete vertical slices, including UI, backend, persistence, authorization, audit, and documentation.
4. **Verify:** Complete automated tests, security review, tenant-isolation tests, accessibility review, and operational validation.
5. **Release:** Deploy to staging, execute smoke and rollback tests, review metrics, and obtain acceptance.
6. **Close:** Publish a phase exit report, record deferred work, update ADRs and runbooks, and authorize the next phase.

### Mandatory gates for every phase

- Code review completed.
- Static analysis, formatting, linting, and automated tests pass.
- Authorization and tenant-isolation tests pass.
- New or changed routes, jobs, notifications, and exports are tenant-aware.
- Security review identifies no unresolved critical or high-risk finding.
- Accessibility checks meet the agreed standard.
- Database migration and rollback strategy are tested.
- Logging, metrics, traces, health checks, and alert implications are documented.
- User and technical documentation are updated.
- Staging deployment and operational smoke tests succeed.
- Product owner accepts the phase exit report.

## 7. Roadmap summary

The durations below are planning estimates for a focused delivery team and are not commitments. Some design, documentation, and platform work can proceed in parallel, but phase exit gates remain sequential.

| Phase | Outcome | Estimated duration |
|---|---|---:|
| 0 | Engineering foundation | 2–3 weeks |
| 1 | Identity and multi-tenancy | 3–4 weeks |
| 2 | Content and public presence | 2–3 weeks |
| 3 | Events and rallies | 4–5 weeks |
| 4 | Recruitment | 3–4 weeks |
| 5 | Contributions and reporting | 4–5 weeks |
| 6 | Platform scale and administration | 4–6 weeks |

**Sequential planning range:** approximately 22–30 weeks, followed by production hardening and launch approval.

## 8. Phase implementation details

## Phase 0 — Engineering foundation

### Objective

Create a secure, repeatable engineering and operational foundation before product features are introduced.

### Deliverables

- Laravel application scaffold with Inertia, Vue, TypeScript, PostgreSQL, and Redis.
- Docker-based local environment and documented setup workflow.
- Environment configuration model with validated required variables.
- GitHub Actions workflows for formatting, linting, static analysis, tests, dependency scanning, and build validation.
- Baseline test framework with factories, fixtures, and parallel-test support.
- Initial modular domain structure and coding conventions.
- ADR template and initial ADRs covering tenancy, modular-monolith boundaries, authentication, queues, object storage, and observability.
- Structured logging, request correlation, health endpoints, baseline metrics, exception reporting, and trace propagation.
- Migration, deployment, rollback, backup, and restore runbooks.
- Security baseline including headers, rate limiting, secret handling, dependency update policy, and branch protection recommendations.
- Repository contribution guide, definition of done, pull-request template, issue templates, and release checklist.

### Exit criteria

- A new developer can build and run the application from documented steps.
- CI is required and passes on a representative pull request.
- Staging can be deployed repeatably from a tagged build.
- Backup and restore have been demonstrated against staging data.
- No product domain depends on unapproved framework shortcuts or hidden global state.

## Phase 1 — Identity and multi-tenancy

### Objective

Deliver the security boundary on which all later alliance features depend.

### Core capabilities

- Registration, login, logout, email verification, password reset, session management, and profile management.
- Optional invitation-only registration mode.
- Alliance creation and initial owner assignment.
- Global users with many alliance memberships.
- Alliance switcher and active-alliance context.
- Membership states such as invited, active, suspended, left, and removed.
- Alliance roles and permissions, including owner, leader, officer, member, recruiter, event coordinator, and content manager.
- Invitation creation, expiry, acceptance, revocation, and resend.
- Privileged-action confirmation and MFA foundation.
- Audit events for authentication, membership, role, permission, and alliance-setting changes.

### Data and design

Primary entities include `users`, `alliances`, `alliance_memberships`, `roles`, `permissions`, `membership_roles`, `invitations`, `sessions`, and `audit_events`.

Alliance ownership, membership status, and permission changes must be transactional. Domain events must be emitted after successful persistence and published through the outbox.

### Verification focus

- Cross-alliance read, write, cache, export, queue, and route-binding isolation.
- Role escalation and invitation abuse tests.
- Session and account-recovery security.
- Alliance switching under concurrent browser sessions.

### Exit criteria

- One user can safely belong to multiple alliances.
- Alliance leaders can invite and administer members without platform-admin intervention.
- No tested endpoint can expose or modify data belonging to another alliance.
- Privileged changes are auditable and attributable.

## Phase 2 — Content and public presence

### Objective

Give each alliance a usable public identity and controlled internal information hub.

### Core capabilities

- Public alliance profile with name, kingdom, language, time zone, description, recruitment status, and branding.
- Published and draft announcements, guides, rules, event instructions, and reference pages.
- Content categories, ordering, revision history, scheduled publication, and archival.
- Alliance home dashboard with current notices and upcoming activities.
- Media upload with type, size, malware-screening hook, tenant-specific storage path, and lifecycle rules.
- Search and filtering within alliance content.
- Localization-ready content fields and date/time presentation.

### Verification focus

- Publication permissions and draft visibility.
- Stored-content sanitization and upload security.
- Public-versus-member-only content boundaries.
- Responsive and accessible content navigation.

### Exit criteria

- An authorized content manager can operate the alliance public page without developer assistance.
- Public users cannot discover member-only content or unpublished revisions.
- Content changes are versioned and recoverable.

## Phase 3 — Events and rallies

### Objective

Deliver the platform’s primary operational value: coordinated event and rally participation.

### Core capabilities

- One-time and recurring events with start time, duration, registration window, capacity, instructions, and status.
- Alliance time zone plus user-local time display.
- Event registration, waitlist, cancellation, attendance, and no-show tracking.
- Configurable reminders through the notification subsystem.
- Event templates for recurring alliance activities.
- Rally configuration including lead requirements, joiner guidance, formations, troop ratios, hero recommendations, and notes.
- Saved member formations and event-specific recommended formations.
- Rally groups, lead slots, joiner assignments, standby status, and participation records.
- Event coordinator dashboard for readiness and attendance.
- Calendar views, list views, exports, and iCalendar feed foundation.

### Design constraints

Game-specific advice must be configuration-driven rather than embedded in controllers or UI components. Recommendation rules should carry an effective date and source or rationale so they can be revised as the game changes.

### Verification focus

- Recurrence and daylight-saving transitions.
- Concurrent registration and capacity enforcement.
- Duplicate reminders and idempotent jobs.
- Alliance isolation in calendars, feeds, exports, and queues.
- Clear mobile workflow for joining events and viewing formations.

### Exit criteria

- Leadership can create a recurring event, publish instructions, collect registrations, assign rally roles, send reminders, and record attendance.
- Members can understand event time and formation guidance without relying on external spreadsheets.
- Reminder delivery is observable and safe to retry.

## Phase 4 — Recruitment

### Objective

Replace informal recruitment tracking with a transparent, accountable pipeline.

### Core capabilities

- Public or invitation-based application form.
- Configurable questions and required fields by alliance.
- Candidate pipeline stages such as new, screening, contacted, interview, accepted, declined, withdrawn, and joined.
- Reviewer assignment, private notes, tags, status history, and next-action dates.
- Duplicate-candidate detection and merge workflow.
- Decision templates and controlled communications.
- Accepted-candidate conversion into an alliance invitation and onboarding checklist.
- Recruitment metrics including source, response time, conversion, and stage aging.
- Retention and deletion controls for unsuccessful applications.

### Verification focus

- Protection of candidate information and reviewer notes.
- Separation between public application access and internal review access.
- Status-transition authorization and audit.
- Safe export and retention behavior.

### Exit criteria

- Recruiters can manage candidates from application through membership without a separate spreadsheet.
- Every decision and status transition is attributable.
- Candidate data follows documented retention and access rules.

## Phase 5 — Contributions and reporting

### Objective

Provide fair, explainable operational reporting without turning the platform into an opaque scoring system.

### Core capabilities

- Configurable contribution categories, goals, units, periods, and evidence requirements.
- Manual entry, member self-report, approval, correction, and reversal workflows.
- Participation records derived from completed events where appropriate.
- Alliance dashboards for participation, attendance, contribution progress, recruitment, and membership trends.
- Member-facing history and progress views.
- Leaderboards that show calculation rules and allow alliance opt-out.
- CSV and spreadsheet exports with authorization and audit.
- Scheduled reports through the notification framework.
- Data-quality flags, missing-data views, and correction workflow.

### Design constraints

Reporting must distinguish recorded facts, calculated metrics, and subjective assessments. Calculation versions must be preserved so historical results remain explainable after rules change.

### Verification focus

- Calculation accuracy and effective dating.
- Export authorization and tenant-specific storage.
- Large-report queue behavior and retry safety.
- Reconciliation between event participation and derived contribution records.

### Exit criteria

- Leaders can answer who participated, who contributed, what data is missing, and how every total was calculated.
- Members can view their own records and understand corrections.
- Reports are reproducible, versioned, and auditable.

## Phase 6 — Platform scale and administration

### Objective

Prepare the product to operate reliably for many alliances and support controlled growth.

### Core capabilities

- Platform-admin console with strict separation from alliance administration.
- Alliance provisioning, suspension, deletion, export, restoration, and ownership-transfer workflows.
- Feature flags and alliance-level configuration.
- Support impersonation only through controlled, time-limited, fully audited access if approved.
- Usage limits, quotas, storage reporting, queue visibility, and operational dashboards.
- API credentials, webhook subscriptions, signing, delivery logs, retries, and revocation.
- Billing-domain foundation and plan entitlements without forcing premature payment implementation.
- Data retention jobs, legal-hold hook, account deletion, and alliance closure workflow.
- Performance testing, index review, queue partitioning, cache strategy, and database maintenance procedures.
- Disaster-recovery exercise and production launch readiness review.

### Verification focus

- Platform-admin privilege boundaries.
- High-volume alliance and event scenarios.
- Queue backlogs, retry storms, and webhook abuse.
- Backup restoration, recovery objectives, and rollback.
- Safe alliance deletion and export completeness.

### Exit criteria

- The platform can onboard and operate multiple alliances with measurable capacity and support procedures.
- Production support can diagnose tenant, queue, database, notification, and integration issues.
- Recovery and rollback have been demonstrated.
- Launch risks are accepted by the accountable owners.

## 9. Cross-cutting requirements

### Security

- Formal threat model updated at least once per phase.
- MFA required for platform administrators and recommended or required for alliance owners and privileged leaders.
- Policy-based authorization for every protected operation.
- Rate limits for authentication, invitations, applications, uploads, exports, APIs, and webhooks.
- Encryption in transit and provider-managed encryption at rest.
- Secret storage outside the repository.
- Dependency, container, and code scanning in CI.
- Tamper-resistant audit records for privileged and security-relevant actions.

### Privacy and data governance

- Collect only information required for alliance operations.
- Document purpose, retention, export, correction, and deletion rules for each domain.
- Avoid storing sensitive real-world identity data unless explicitly justified.
- Provide alliance and user export/delete workflows appropriate to their roles.

### Accessibility and localization

- Responsive design suitable for phone use during gameplay.
- Keyboard navigation, semantic structure, labelled forms, clear validation, and sufficient contrast.
- User and alliance time-zone support from Phase 1 onward.
- Translation-ready application strings and content models.

### Observability

- Structured logs include request or job correlation, domain action, tenant identifier, actor identifier where appropriate, and outcome without exposing secrets.
- Metrics cover request latency, errors, database health, queue depth, job failures, notification delivery, event registration, and integration delivery.
- Traces connect web requests, database work, jobs, notifications, and outgoing integrations.
- Alerts are tied to runbooks and actionable thresholds.

## 10. Environments and release strategy

### Environments

- **Local:** Docker-based, seeded development data, mail and object-storage emulation.
- **Continuous integration:** Ephemeral test environment for every change.
- **Staging:** Production-like configuration with synthetic or approved test data.
- **Production:** Controlled access, protected secrets, backups, monitoring, and change records.

### Release approach

- Trunk-based development with short-lived branches and protected `main`.
- Pull requests require automated checks and review.
- Feature flags separate deployment from activation.
- Database changes use backward-compatible expand-and-contract patterns where required.
- Releases are tagged and accompanied by migration notes, rollback instructions, and user-impact notes.
- High-risk features use limited alliance rollout before general availability.

## 11. GitHub delivery model

### Recommended repository structure

```text
app/
  Domain/
    Alliances/
    Audit/
    Authorization/
    Content/
    Contributions/
    Events/
    Identity/
    Integrations/
    Kingdoms/
    Memberships/
    Notifications/
    Platform/
    Rallies/
    Recruitment/
docs/
  adr/
  domains/
  operations/
  product/
  security/
resources/js/
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

### Backlog organization

- One GitHub milestone per phase.
- One epic issue per domain outcome.
- Vertical-slice issues that include UI, backend, persistence, authorization, tests, documentation, and observability.
- Labels for `phase`, `domain`, `type`, `risk`, `priority`, and `status`.
- Architecture decisions recorded in ADRs rather than hidden in issue discussions.
- Deferred work recorded explicitly with the phase that may reconsider it.

### Initial epics

1. Engineering foundation and CI/CD.
2. Observability, backup, and recovery baseline.
3. Global identity and account security.
4. Alliance tenancy and membership lifecycle.
5. Roles, permissions, and audit.
6. Alliance public presence and content.
7. Events, recurrence, registration, and reminders.
8. Rally guidance, formations, and participation.
9. Recruitment pipeline and onboarding.
10. Contribution records and calculation engine.
11. Reporting, exports, and dashboards.
12. Platform administration and alliance lifecycle.
13. Integrations, webhooks, and API access.
14. Performance, resilience, and launch readiness.

## 12. Program metrics

The program will measure delivery health and product outcomes.

### Delivery metrics

- Phase predictability and gate pass rate.
- Lead time from approved issue to production.
- Escaped defects and regression rate.
- Automated test reliability and build duration.
- Security findings by severity and remediation age.
- Documentation and runbook completeness.

### Product metrics

- Activated alliances and active members.
- Invitation acceptance and onboarding completion.
- Event registration, attendance, and reminder effectiveness.
- Percentage of events using published rally guidance.
- Recruitment response time and conversion.
- Contribution-record completeness and correction rate.
- User-reported administrative time saved.
- Availability, latency, job success, and notification delivery.

Metrics must not encourage unhealthy play or punitive member management. Alliances should control which competitive or comparative views they enable.

## 13. Key risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Tenant data leakage | Critical trust and security failure | Explicit tenant context, policies, scoped queries, isolation test suite, code review checklist |
| Overbuilding before user validation | Delayed value | Phase-gated MVP, alliance interviews, limited rollout, explicit deferrals |
| Game mechanics change | Incorrect guidance | Effective-dated configuration, source/rationale field, admin-managed recommendation rules |
| Time-zone and recurrence defects | Missed events | UTC storage, dedicated recurrence library, DST test matrix, user-local display |
| Notification duplication or failure | User fatigue or missed coordination | Outbox, idempotency keys, delivery logs, retries, preference controls |
| Privilege complexity | Unauthorized actions | Small permission vocabulary, policy tests, role templates, audit, periodic review |
| Reporting becomes punitive or opaque | Reduced adoption | Explainable calculations, opt-in leaderboards, correction workflow, member visibility |
| Operational burden grows faster than adoption | Unstable service | Automation, dashboards, runbooks, quotas, phased integrations, capacity testing |
| Unapproved game integration | Legal or reliability exposure | Use only approved interfaces; keep manual/configurable workflows as the baseline |

## 14. First 30 days

### Week 1

- Approve this plan and name the product, technical, security, quality, and platform owners.
- Confirm the first pilot alliance and recruit representative leaders and members for discovery.
- Create Phase 0 milestone, labels, issue templates, and the first ADRs.
- Confirm hosting, domain, email, object storage, and observability choices.

### Week 2

- Scaffold Laravel, frontend, database, Redis, Docker, and CI.
- Establish coding, testing, migration, security, and documentation standards.
- Build the first staging deployment and health checks.
- Complete the initial threat model and tenant-isolation test strategy.

### Week 3

- Validate backup and restore.
- Implement structured logging, tracing, queue monitoring, and error reporting.
- Finalize the Identity, Alliances, Memberships, Authorization, and Audit domain models.
- Review Phase 1 user journeys and acceptance criteria with the pilot alliance.

### Week 4

- Close Phase 0 against its exit criteria.
- Publish the Phase 0 exit report.
- Begin Phase 1 with registration, alliance creation, memberships, invitations, and policy enforcement as the first vertical slices.

## 15. Program definition of done

A feature or phase is done only when:

- The intended user outcome is complete and accepted.
- Backend, frontend, database, authorization, audit, and asynchronous behavior are implemented.
- Automated unit, feature, integration, authorization, and tenant-isolation tests pass.
- Security, accessibility, privacy, and operational impacts are addressed.
- Logs, metrics, traces, alerts, and support diagnostics are sufficient.
- Migrations, deployment, rollback, backup, and recovery implications are documented and tested as applicable.
- User help, domain documentation, ADRs, and release notes are current.
- The feature is successfully deployed and validated in staging.
- Deferred enhancements are recorded without partially implementing them.

## 16. Approval decisions required

Before Phase 0 closes, the program must explicitly approve:

1. Initial hosting and deployment target.
2. Supported authentication methods and MFA requirements.
3. Alliance tenancy and URL model.
4. Initial role and permission vocabulary.
5. Pilot alliance and launch cohort.
6. Notification channels included in the first production release.
7. Data-retention defaults for recruitment, audit, exports, and closed alliances.
8. Production availability, recovery, and support targets.
9. Accessibility standard and supported browsers.
10. Whether billing remains dormant infrastructure or is excluded until after Phase 6.

Once approved, these decisions should be recorded as ADRs and converted into measurable backlog acceptance criteria.