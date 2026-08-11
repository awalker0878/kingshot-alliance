# Phase 3 Exit Report

**Phase:** Events and Rallies  
**Status:** Accepted  
**Branch:** `agent/phase-3-events-and-rallies`

## Objective

Deliver the platform's primary operational value: tenant-safe event scheduling, registration, reminders, attendance, formations, and rally coordination without relying on external spreadsheets or chat-only workflows.

## Delivered scope

- One-time and recurring events with UTC persistence, alliance time-zone authoring, user-local presentation, duration, registration windows, capacity, instructions, and lifecycle status.
- Event templates for repeatable alliance activities, including transactionally persisted template provenance.
- Registration, capacity-safe waitlist, cancellation, automatic waitlist promotion, attendance, no-show, and participation history.
- Configurable in-app reminder rules using the existing transactional outbox/queue foundation with deterministic idempotency, retry/backoff visibility, cancellation suppression, and a member-visible reminder inbox.
- Rally configuration with lead requirements, joiner guidance, troop-ratio recommendations, hero guidance, notes, and effective-dated rationale/source fields.
- Saved member formations and event-specific recommended formations.
- Rally groups, numbered lead/joiner slots, capacity-aware assignments, standby status, and participation records.
- Coordinator scheduling/readiness/attendance dashboard plus member event list/detail flows.
- Authenticated, active-alliance-scoped CSV export and iCalendar feed foundation.
- Alliance-home upcoming-event summary and event-management navigation.

## Scope boundaries

- Game-specific recommendations are configuration data, never hard-coded controller/UI logic.
- Recruitment remains Phase 4-owned.
- Contribution scoring/reporting remains Phase 5-owned.
- No automated Kingshot game-data collection is introduced without an approved interface.
- Phase 3 iCalendar support is an authenticated feed/download foundation; it does not introduce a long-lived public subscription token.

## Verification evidence

### Functional and concurrency

- `RecurrenceCalculatorTest` verifies weekly wall-clock recurrence across daylight-saving transitions, one-time recurrence, recurrence-until bounds, and invalid interval rejection.
- `EventRegistrationTest` verifies capacity enforcement, waitlisting, duplicate-registration idempotency, cancellation, oldest-waitlist promotion, and cross-alliance denial using PostgreSQL row locking/constraints.
- `EventReminderDeliveryTest` verifies deterministic reminder materialization, duplicate-safe queue/publish behavior, cancellation suppression, sent delivery state, and active-alliance reminder-inbox isolation.
- `RallyCoordinationTest` verifies effective-dated/source-backed guidance, valid 100% troop composition, member-owned saved formations, active-alliance enforcement, rally capacity, and standby overflow.
- `EventHttpTest` verifies active-alliance list/detail behavior, registration/cancellation through HTTP, coordinator permission denial, recurring event creation, and CSV/iCalendar non-leakage.
- `EventOverviewTest` verifies that the alliance overview exposes only the active alliance's upcoming event and correct event-management authorization.

### Accessibility

- `EventAccessibilityGuardTest` applies the accepted Phase 2 source-level accessibility guard standard to all new Phase 3 event pages: main landmark required, raw `v-html` prohibited, positive `tabindex` prohibited, and native buttons must declare a type.
- Member event flows use native links/buttons/form controls, explicit labels, keyboard-native interactions, textual status, responsive single-column-first layouts, and explicit user/alliance time-zone labels.
- Reminder content is presented in an `aria-live="polite"` member section.
- Deployment-specific browser/branding contrast, device reflow, and assistive-technology smoke testing remain release-readiness activities as documented in the [Phase 3 accessibility review](phase-3-accessibility.md).

### Migration and recovery

- `EventMigrationRollbackTest` invokes the Phase 3 migration `down()` and `up()` against the test database and verifies all eleven Phase 3 tables are removed and restored cleanly.
- The staging pipeline successfully built the immutable production image, deployed an ephemeral staging environment, validated it, completed the database backup/restore drill, recovered service readiness, and completed the production-image vulnerability scan.

### Security and tenancy

- The [Phase 3 threat model](../security/phase-3-threat-model.md) covers cross-alliance object reference, privilege escalation, capacity races, duplicate/stale reminders, guidance provenance, export leakage, stored-content injection, recurrence exhaustion, rally assignment collision, and privacy considerations.
- All submitted coordinator object identifiers are re-resolved under the active alliance before privileged mutation.
- Phase 3 exports are authenticated, active-alliance-scoped, and returned with `Cache-Control: private, no-store`.
- Reminder inbox data is filtered by both active `alliance_id` and active `membership_id`.
- CodeQL and Dependency Review passed with no unresolved critical/high application-security gate failure.

### Operations and documentation

- [Phase 3 operations](../operations/phase-3-operations.md) documents scheduler commands, reminder lifecycle, retry/backoff fields, registration concurrency, UTC/local-time behavior, exports, audit/outbox correlation, health/metrics/alert implications, backup/recovery, rollback, and incident triage.
- The current [Events domain contract](../domains/events/README.md) and [Rallies domain contract](../domains/rallies/README.md) document member/coordinator Event and Rally workflows, reminders, formations, coordination, exports, time-zone behavior, troubleshooting, and tenant/security boundaries.
- Phase 3 uses the existing audit recorder and transactional outbox for privileged business transitions and asynchronous delivery observability.

## Protected-workflow evidence

Final technical report head before product acceptance: `ad1cbf3228f86dd915dbc82466d441f7aca0c475`.

- CI run `31187575503`: **passed**
  - frontend lint/format/typecheck/production build: passed
  - PostgreSQL migrations: passed
  - Pint/PHPStan/full PHPUnit suite: passed
  - production image build: passed
  - ephemeral staging deployment/validation: passed
  - backup/restore recovery drill: passed
  - production-image vulnerability scan: passed
- CodeQL run `31187578967`: **passed**
- Dependency Review run `31187575970`: **passed**
- Pull-request review check found no unresolved review comments/threads.
- Temporary implementation-only formatting workflows were removed before acceptance.

The product-acceptance commit is documentation-only and must also remain green under the protected workflows before merge.

## Exit criteria

- [x] Leadership can create a recurring event, publish instructions, collect registrations, assign rally roles, send member-visible in-app reminders, and record attendance/participation.
- [x] Members can understand event time and formation guidance without relying on external spreadsheets.
- [x] Reminder delivery is observable and safe to retry.
- [x] Authorization and tenant-isolation tests cover Phase 3 routes, queries, reminder inbox, feeds, exports, and rally assignments.
- [x] Security review and protected security workflows identify no unresolved critical/high application-security gate failure.
- [x] Accessibility implementation and automated regression evidence meet the agreed Phase 3 standard.
- [x] Phase 3 migration forward/rollback behavior is tested and documented.
- [x] Logging/audit, scheduler/queue/outbox, health, metrics, alerts, backup/recovery, and incident implications are documented.
- [x] User and technical documentation are updated.
- [x] Staging deployment, backup/recovery, and vulnerability scanning pass.
- [x] Product owner accepted the Phase 3 outcome and authorized continuation on 2026-08-07.

## Acceptance decision

**Phase 3 — Events and Rallies: ACCEPTED.**

The product owner authorized continuation through the Phase 3 gate on 2026-08-07. PR #13 may be marked ready and merged once this acceptance-record commit remains green under the protected workflows.
