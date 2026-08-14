# Events testing and evidence

[← Events domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Events  
**Code owner:** `app/Domain/Events`  
**Primary validation boundary:** scoped authorization, durable Player identity, scheduling policy, participation integrity, operational workspaces, query bounds, localization, and accessibility

## 1. Critical claims and validation ownership

Events validation proves exact Player/Alliance/Kingdom scope isolation, one-User-to-many-Players ownership, active Player Context enforcement, recurrence/time-zone correctness, capacity-safe registration/waitlisting, Player-specific reminders/votes/rosters/Rallies/objectives/results, and capability-driven UI behavior.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`. Unit coverage owns schedule-policy and calculation semantics. Feature and Integration coverage own end-to-end scoped workflows. Performance coverage owns bounded-query claims for calendar, attention, intelligence, and operational planners.

## 3. Architecture and domain-boundary validation

Architecture tests protect Events ownership of catalogue/schedule/occurrence/participation/phase/poll/roster/objective/result state; Notifications ownership of reminder delivery; Rallies ownership of Rally-specific state; Kingdoms ownership of `Player` and `Kingdom`; and Authorization ownership of permission grants.

They also enforce `player_id` as the durable participant identity and prevent membership identity from becoming an Event participant key.

## 4. Authorization, tenancy, security and privacy validation

Tests cover:

- Player self-service only for the validated active Player owned by `players.user_id`;
- one User owning multiple independent Players;
- R4/R5 and specialist Alliance authority without Kingdom leakage;
- exact-Kingdom role isolation;
- direct-request resistance when UI controls are hidden;
- current-target checks after Player Kingdom or Alliance context changes; and
- current permission checks before reminders, attention items, Event data, or result subjects are disclosed.

[Events security](../security/README.md) defines the security claims that regression evidence preserves.

## 5. Feature, interface and integration validation

Feature tests cover agenda/calendar/detail/create/manage, templates, exports, responses, registration/waitlist, attendance, reminders, phases, polls, rosters, formations, Rally operations, battle plans, and results/intelligence. Integration tests protect audit/outbox and Notifications/Rallies boundaries.

[Calendar exports](../interfaces/calendar-exports.md) and [Event registration and attendance](../registration-and-attendance.md) are first-party interface/lifecycle contracts.

## 6. Idempotency, concurrency and asynchronous validation

Tests protect transaction-safe capacity and waitlist promotion, idempotent participation facts, one active Rally/roster assignment where configured, immutable poll choices after voting begins, deterministic reminder deliveries, and safe occurrence replacement during rescheduling without deleting operational history.

Asynchronous retry never changes Event source truth merely because a downstream delivery retries.

## 7. Persistence, migration, rollback and recovery evidence

Fresh-schema tests validate Event catalogue, scheduling, participation, phases/polls, rosters, battle plans, results, and cross-table occurrence/target constraints. Recovery validation checks that Event source state, Player identity, reminders, and dependent operational records remain referentially consistent after PostgreSQL restoration.

Recovery procedures are documented in [Events operations](../operations/README.md) and Notifications operations.

## 8. Performance, query and capacity evidence

Performance tests require query counts to remain bounded as upcoming occurrences or eligible Players grow. Attention/reminder/calendar/intelligence queries batch facts rather than issue per-occurrence or per-Player query loops. Business capacity is enforced transactionally and is separate from infrastructure capacity.

No universal Event request-time or throughput SLA is inferred from repository tests.

## 9. Accessibility and frontend evidence

Frontend and architecture checks protect semantic page landmarks, native/labeled controls, pressed/current state for selectable controls, keyboard-visible focus, responsive overflow for dense planners/tables, locale-driven date labels, and absence of actor Player identifiers in Event forms.

`npm run check` remains the complete frontend quality gate when dependencies are installed; parser/static checks provide repository-local evidence when dependency trees are unavailable.

## 10. Current acceptance evidence

Current acceptance is the executable suite and domain contracts on the current source tree. Evidence is identified by the release/commit SHA produced by the delivery workflow together with CI, CodeQL, database, and frontend checks for that revision.


## 11. Evidence identity, retention and supersession

Retain failing/passing test identities, query-count evidence, database engine/version, release SHA, and CI/security check identifiers with the release. Newer evidence supersedes older evidence when architecture or capabilities change.

## 12. Gaps, non-capabilities and related documentation

No anonymous long-lived calendar token, Event import, automated game ingestion, or Events-owned generic notification transport is accepted. Localization intentionally supports English fallback when a non-core catalogue phrase is not translated; the operational shell is localized for every supported locale.

Related documentation:

- [Events domain](../README.md)
- [Registration and attendance](../registration-and-attendance.md)
- [Events security](../security/README.md)
- [Events operations](../operations/README.md)
- [Events interfaces](../interfaces/README.md)
- [Rallies testing](../../rallies/testing/README.md)
- [Notifications testing](../../notifications/testing/README.md)
