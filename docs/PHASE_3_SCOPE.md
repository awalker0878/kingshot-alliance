# Phase 3 Scope — Events and Rallies

Phase 3 implements the event/rally capabilities defined by `docs/IMPLEMENTATION_PLAN.md` and must remain within that boundary.

## In scope

- alliance-scoped event templates and event instances
- one-time and recurring scheduling
- UTC storage with alliance-time-zone authoring and user-local presentation
- registration windows, capacity, waitlists, cancellation, attendance, and no-show tracking
- configurable reminders over the existing queue/outbox foundation
- rally guidance, lead/joiner requirements, troop ratios, hero guidance, notes, rationale/source, and effective dates
- member-saved formations and event-specific recommended formations
- rally groups, lead slots, joiner assignments, standby state, and participation records
- coordinator readiness/attendance views
- member event list/calendar/detail views
- tenant-safe exports and iCalendar feed foundation

## Explicitly deferred

- recruitment/candidate pipeline work (Phase 4)
- contribution scoring/leaderboards/reporting (Phase 5)
- platform administration/scale work beyond what Phase 3 operational safety requires (Phase 6)
- automated Kingshot game-data ingestion without an approved interface

## Cross-cutting requirements

- explicit tenant identity in routes, queries, exports, cache keys, jobs, notifications, and audit events
- game guidance stored as configuration data, not embedded in controllers or Vue components
- recurring schedule logic covered across daylight-saving transitions
- row-lock/concurrency-safe registration and waitlist promotion
- retry-safe/idempotent reminder creation and delivery
- migration rollback evidence
- accessibility, security/threat, and operations documentation before acceptance
