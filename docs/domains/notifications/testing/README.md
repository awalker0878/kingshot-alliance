# Notifications testing and evidence

[← Notifications domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary validation boundary:** deterministic Player-specific reminder delivery, exact audience eligibility, outbox handoff, and idempotent completion

## 1. Critical claims and validation ownership

Tests must prove reminder delivery identity is Player-specific, scheduler execution is duplicate-safe, audience resolution uses current Event eligibility, one User with multiple Players receives independent deliveries, and exact Event authorization protects reminder configuration.

## 2. Executable suite mapping

Feature tests cover reminder rules/audiences/multi-Player delivery. Integration tests cover scheduler/outbox publication. Architecture tests protect server-owned Player context and domain boundaries. Tenant/scope-isolation tests cover Alliance and Kingdom audiences.

## 3. Architecture and domain-boundary validation

Events owns occurrence and participation facts; Notifications owns reminder rule/delivery coordination; Kingdoms owns durable Player identity; Platform owns outbox publication. Tests must detect cross-domain writes that redefine those facts.

## 4. Authorization, tenancy, security and privacy validation

Tests prove exact Player/Alliance/Kingdom manager authorization, authoritative `players.user_id` recipient resolution, cross-user Player isolation, stale-roster rejection, and minimized delivery payloads.

## 5. Feature, interface and integration validation

Feature evidence covers rule creation, target/responded/registered/all-scope audiences, due queueing, separate Player deliveries, Needs Your Attention integration, and publisher completion to `sent`.

## 6. Idempotency, concurrency and asynchronous validation

Repeated reminder scheduler runs must reuse the unique rule/occurrence/Player identity and must not create a second outbox request. At-least-once `OutboxPublished` handling must remain safe and ignore unrelated messages.

## 7. Persistence, schema and recovery evidence

Schema tests protect unique Player delivery identity and registration state constraints. Recovery testing verifies scheduler/outbox reruns converge from persisted state without replaying source Event mutations.

## 8. Performance, query and capacity evidence

Reminder scanning is explicitly bounded. Performance review focuses on due-rule/occurrence queries, audience Player resolution, unique-key contention, and outbox backlog rather than assuming a throughput target.

## 9. Accessibility and frontend evidence

Event Show/Manage/Calendar surfaces must expose response, registration, attendance, and attention state textually and remain keyboard operable. Player identity must be visible where self-service state could otherwise be ambiguous.

## 10. Current executable evidence

`tests/Feature/Events/EventParticipationTest.php` protects response independence, capacity/waitlist promotion, attendance separation, multi-Player reminders, active-Player request enforcement, and attention queries. `tests/Architecture/EventParticipationArchitectureTest.php` protects server-resolved self-service identity and Player-keyed persistence.

## 11. Evidence identity, retention and supersession

Living validation follows the current repository revision and CI results. Any future release evidence should record exact revision/workflow identity under the repository testing-evidence standard.

## 12. Gaps, non-capabilities and related documentation

Notifications does not claim third-party provider delivery or generic notification API behavior.

Related documentation:

- [Notifications domain](../README.md)
- [Notifications security](../security/README.md)
- [Notifications operations](../operations/README.md)
- [Event reminders](../event-reminders.md)
- [Events testing](../../events/testing/README.md)
