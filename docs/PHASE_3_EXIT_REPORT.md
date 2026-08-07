# Phase 3 Exit Report

**Phase:** Events and Rallies  
**Status:** Not accepted  
**Branch:** `agent/phase-3-events-and-rallies`

## Objective

Deliver the platform's primary operational value: tenant-safe event scheduling, registration, reminders, attendance, formations, and rally coordination without relying on external spreadsheets or chat-only workflows.

## Planned scope

- One-time and recurring events with UTC persistence, alliance time-zone authoring, user-local presentation, duration, registration windows, capacity, instructions, and lifecycle status.
- Event templates for repeatable alliance activities.
- Registration, waitlist, cancellation, attendance, no-show, and participation history.
- Configurable reminder rules using the existing transactional outbox/queue foundation with observable retry/idempotency behavior.
- Rally configuration with lead requirements, joiner guidance, troop-ratio recommendations, hero guidance, notes, and effective-dated rationale/source fields.
- Saved member formations and event-specific recommended formations.
- Rally groups, lead slots, joiner assignments, standby status, and participation records.
- Coordinator readiness/attendance dashboard plus member calendar/list/detail flows.
- Calendar export and iCalendar feed foundation with explicit alliance isolation.

## Scope boundaries

- Game-specific recommendations are configuration data, never hard-coded controller/UI logic.
- Recruitment remains Phase 4-owned.
- Contribution scoring/reporting remains Phase 5-owned.
- No automated Kingshot game-data collection is introduced without an approved interface.

## Required verification

- recurrence correctness across daylight-saving transitions
- concurrent registration/waitlist capacity enforcement
- duplicate-reminder prevention and retry safety
- tenant isolation across calendar/list/detail/feed/export/queue/cache routes
- event/rally mutation authorization and auditability
- mobile/keyboard-accessible registration and formation guidance
- migration forward/rollback behavior
- scheduler, queue, outbox, alert, backup/recovery, and operational impacts documented

## Implementation checkpoint

- Event definition/occurrence persistence, recurrence calculation, registration, cancellation, waitlist promotion, templates, attendance, reminder rules/deliveries, effective-dated rally guidance, saved formations, event recommendations, rally groups, assignments, standby, and participation application services are implemented on the Phase 3 branch.
- The first event/registration checkpoint passed PostgreSQL migrations, Pint, PHPStan, PHPUnit, and frontend checks before reminder/rally expansion.
- PostgreSQL migrations continued to pass after the reminder/rally schema expansion.
- Reminder/rally application code is covered by focused feature tests, but final semantic validation remains pending on the current formatted head.
- Phase 3 HTTP/UI calendar, detail, coordinator dashboard, export/iCalendar, accessibility, and final operational/staging evidence remain incomplete.

## Acceptance evidence

Pending final-head validation. Current checkpoints are implementation evidence only and do not constitute Phase 3 acceptance.

## Exit criteria

- [ ] Leadership can create a recurring event, publish instructions, collect registrations, assign rally roles, send reminders, and record attendance.
- [ ] Members can understand event time and formation guidance without relying on external spreadsheets.
- [ ] Reminder delivery is observable and safe to retry.
- [ ] Authorization and tenant-isolation tests cover Phase 3 routes, queries, feeds, exports, jobs, and rally assignments.
- [ ] Security review identifies no unresolved critical or high application-security finding.
- [ ] Accessibility implementation and regression evidence meet the agreed Phase 3 standard.
- [ ] Phase 3 migration forward/rollback behavior is tested and documented.
- [ ] Logging, traces/audit, scheduler/queue/outbox, health, metrics, and alert implications are documented.
- [ ] User and technical documentation are updated.
- [ ] Staging deployment, backup/recovery, and vulnerability scanning pass on the accepted final head.

## Acceptance decision

**Phase 3 — Events and Rallies: NOT ACCEPTED.**
