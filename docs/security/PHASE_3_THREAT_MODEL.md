# Phase 3 Threat Model — Events and Rallies

## Scope

This review covers the Phase 3 event, registration, reminder, attendance, formation, rally, export, and calendar-feed surfaces. It extends the existing identity/multi-tenancy security boundary rather than replacing it.

## Protected assets

- alliance event schedules and instructions
- membership registrations, waitlist positions, attendance, and no-show state
- member-saved formations
- alliance rally guidance and recommendation provenance
- rally-group membership, lead/joiner assignments, standby and participation state
- reminder delivery/outbox state
- CSV and iCalendar output
- privileged audit attribution

## Trust boundaries

1. Browser to authenticated Laravel application.
2. Active-alliance session context to alliance-scoped queries/actions.
3. Application transaction to PostgreSQL constraints/row locks.
4. Scheduler/worker to reminder/outbox records.
5. Authenticated export/feed response to the member's client.

No Phase 3 interface trusts alliance/member IDs supplied by the browser without re-scoping them to the active alliance.

## Threats and controls

### Cross-alliance object reference

**Threat:** A member submits an occurrence, membership, formation, group, registration, assignment, or guidance ID belonging to another alliance.

**Controls:**

- `alliance.context` resolves the active membership before Phase 3 routes execute.
- Read queries include `alliance_id`.
- Coordinator controller mutations re-resolve submitted IDs under the active `alliance_id` before calling application actions.
- Composite foreign keys enforce same-alliance relationships for Phase 3 tenant entities.
- HTTP tests cover foreign occurrence denial and export/feed non-leakage.
- Reminder inbox sharing resolves the active membership and filters by both `alliance_id` and `membership_id`.

**Residual risk:** New Phase 3 endpoints must reuse the same scoped-query pattern; unscoped model lookup is a review blocker.

### Privilege escalation

**Threat:** A normal member creates events, modifies guidance, assigns rally roles, or records attendance.

**Controls:**

- `events.manage` is checked by application actions and the coordinator dashboard.
- Active members can save only their own formations and manage only their own event registration.
- Coordinator IDs are re-scoped before mutations.
- Privileged actions are audit-attributed.

**Residual risk:** Role administration remains the Phase 1 security boundary and must not grant `events.manage` unintentionally.

### Registration overbooking race

**Threat:** Concurrent registration requests exceed event capacity or create duplicates.

**Controls:**

- registration locks the occurrence row before deciding registered vs waitlisted state;
- unique `(occurrence_id, membership_id)` prevents duplicate rows;
- cancellation and oldest-waitlist promotion occur in one transaction;
- concurrent/capacity behavior has PostgreSQL feature coverage.

**Residual risk:** Direct database writes can bypass application ordering semantics and are operationally prohibited without controlled recovery.

### Duplicate or stale reminders

**Threat:** Scheduler retries send the same reminder multiple times, or a reminder is sent after registration cancellation.

**Controls:**

- deterministic SHA-256 reminder idempotency key for `(occurrence, rule, membership)`;
- unique delivery key plus one durable delivery row;
- registration status is rechecked before queuing;
- cancellation marks a materialized-but-not-queued reminder cancelled;
- outbox publication has attempt/backoff state and published-at protection;
- tests cover repeat materialization, repeat queue/publish, cancellation suppression, and active-alliance inbox isolation.

**Residual risk:** A future external email/push adapter must preserve the same idempotency key at the provider boundary.

### Guidance tampering or stale game advice

**Threat:** Game recommendations are silently embedded in application logic or stale guidance is presented as timeless truth.

**Controls:**

- troop ratios/hero advice are persisted configuration, not controller/UI constants;
- guidance carries `effective_from`, optional `effective_until`, `source`, and `rationale`;
- formation composition validates a 100% troop total;
- member detail UI displays provenance/effective dates when linked.

**Residual risk:** Leaders remain responsible for the quality of configured game advice; the platform does not claim official game-data authority.

### Export or calendar leakage

**Threat:** Another alliance's events appear in CSV/iCalendar output or responses are stored in shared caches.

**Controls:**

- endpoints require authenticated, verified, active-alliance context;
- query object filters every occurrence by active `alliance_id`;
- responses use `Cache-Control: private, no-store`;
- tests create two alliances and assert the inactive alliance title is absent from both CSV and iCalendar output.

**Residual risk:** Phase 3 does not issue public subscription tokens. Any future tokenized calendar subscription requires revocation, entropy, tenant binding, and cache review.

### Stored-content injection

**Threat:** Event instructions, guidance, notes, hero names, or titles execute markup/script in member/coordinator pages.

**Controls:**

- Vue renders Phase 3 text through normal interpolation; Phase 3 does not use `v-html` for these fields;
- request validation limits sizes/types;
- exported iCalendar text is escaped for backslash, semicolon, comma, and newlines.

**Residual risk:** Any future rich-text event instructions must reuse the established sanitized content pipeline rather than raw HTML rendering.

### Recurrence/resource exhaustion

**Threat:** A crafted recurrence creates unbounded occurrence generation or oversized requests.

**Controls:**

- recurrence interval is validated;
- recurrence generation has a bounded occurrence limit;
- web inputs constrain interval/duration/window values;
- event lists/management datasets are bounded.

**Residual risk:** If recurrence features expand to complex RRULE input, parser complexity and generation bounds require renewed review.

### Rally assignment collision

**Threat:** Duplicate numbered lead/joiner slots or over-capacity joiners create ambiguous rally instructions.

**Controls:**

- rally group is row-locked during assignment;
- joiner capacity overflow becomes `standby` rather than overbooking;
- unique rally slot constraint prevents duplicate `(group, role, slot_number)` values;
- one assignment per member/group;
- assignment membership must be active and same-alliance.

## Privacy considerations

Phase 3 stores operational participation state, including attendance/no-show and rally participation. This information is member/alliance data and is not exposed on public alliance routes. Exports are authenticated and tenant-scoped. Logs/metrics should use identifiers rather than duplicating names/emails wherever possible.

## Security verification evidence

Phase 3 requires all of the following on its final head:

- PHPUnit authorization/tenant-isolation suite green on PostgreSQL;
- PHPStan/Pint/frontend checks green;
- CodeQL green;
- dependency review/audits green;
- production-image scan with no unresolved fixed critical/high finding under the CI policy;
- staging smoke and backup/restore green.

## Decision

No intentional critical or high-risk Phase 3 security exception is accepted. A failing isolation test, CodeQL gate, dependency review, or image scan blocks Phase 3 acceptance.
