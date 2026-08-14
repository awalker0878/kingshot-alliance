# Notifications interfaces

[← Notifications domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary boundary:** Internal scheduled reminder/report delivery coordination through deterministic state and the shared outbox  
**P4 inventory decision:** Profile only

## 1. Boundary purpose and ownership

Notifications owns durable coordination for Event reminders and scheduled Contribution report delivery requests. Its current P4 boundary is internal/asynchronous: it consumes Events/Contributions source state, resolves current audiences and creates deterministic Player-specific delivery records, records notification outbox work, and reacts to Platform outbox publication.

Notifications does not own Event schedules/registrations, Contribution report semantics, or generic external webhook delivery.

## 2. Surface inventory

There is no direct Notifications browser, public API, manager API, or external webhook-management route in the current runtime.

Material entry points are internal actions and commands:

- `QueueDueEventReminders`;
- `QueueDueContributionReports`;
- `MarkEventReminderSent` as an `OutboxPublished` consumer;
- `events:queue-reminders`; and
- `contributions:queue-reports`.

## 3. Callers, authorization and tenancy

Scheduler/operator commands run under trusted application/operator context rather than end-user HTTP authorization. Each action derives Event scope/Player/source identity from persisted eligible state and remains tenant bound.

First-party Event/Contribution managers configure source reminders/report schedules through their owning domains; Notifications does not expose an alternate route that bypasses those permissions.

## 4. Input and validation contracts

Event reminder audience resolution consumes persisted eligible Event/occurrence/registration/reminder configuration. Contribution report coordination consumes persisted Contributions schedules and active recipient/Player state.

Command `--limit` options are bounded in `routes/console.php`: Event reminder queue up to 1000 and contribution report queue up to 250; scheduled invocations use 100 and 50 respectively.

Actions recheck source eligibility rather than trusting stale queued intent.

## 5. Output and disclosure contracts

Notification coordination produces durable internal delivery/outbox state and eventually supported first-party notification/mail effects. It does not expose a stable public JSON payload or generic external message schema.

Source-domain facts included in notification payload/evidence must remain minimized and tenant safe. Outbox publication does not make reminder/report internals a public webhook contract by default.

## 6. Internal actions, queries and services

Notifications exposes the scheduler-facing actions above and consumes source-domain query/model contracts required to decide eligibility. Its delivery records provide deterministic identities/status used for retry/reconciliation.

Events/Contributions remain authoritative for source facts. Consumers should not update those domains from notification persistence.

## 7. Events, outbox and cross-domain consumers

Notifications records eligible delivery requests through the shared Platform transactional outbox. `AppServiceProvider` invokes `MarkEventReminderSent` for every `OutboxPublished`; that consumer recognizes only its supported Event-reminder message identity/type and ignores unrelated events.

Platform owns outbox durability/publication. Integrations separately consumes `OutboxPublished` for external webhook fan-out and does not convert Notifications internal state into an API contract.

## 8. Commands, jobs and scheduled work

Current scheduler contracts:

- `events:queue-reminders --limit=100` — every minute;
- `contributions:queue-reports --limit=50` — every minute.

All run on one server with overlap protection through the shared scheduler configuration. Safe catch-up/reconciliation is defined in [Notifications operations](../operations/README.md) and [Scheduled delivery](../operations/scheduled-delivery.md).

## 9. Files, imports, exports and external dependencies

Notifications owns no file import/export format. Contributions owns report-generation/export semantics; Notifications coordinates scheduled delivery intent.

Externally relevant dependencies may include configured mail transport plus Platform outbox/queue runtime. Dependency failure does not permit fabrication of delivered status.

## 10. Failure, idempotency, versioning and compatibility

Deterministic delivery/run identities prevent duplicate logical audience resolution on scheduler retries. Source eligibility is rechecked before advancing work. Outbox publication is at-least-once and consumers therefore must remain idempotent.

Command names and the source→notification ownership split are internal compatibility contracts. There is no accepted public Notifications API version/schema.

## 11. Explicit non-capabilities

Notifications does not:

- own Event occurrence/registration state;
- own Contribution records/report schema;
- expose a public notification-management API;
- own Integrations webhook subscriptions/delivery;
- infer recipient eligibility from stale queued data; or
- mark work delivered merely because it was queued/published.

## 12. Focused contracts, evidence and related documentation

No new focused P4 interface contract is required; the internal scheduler/outbox boundary is coherent in this profile and existing operations contracts.

Related documentation:

- [Notifications domain](../README.md)
- [Notifications security](../security/README.md)
- [Notifications operations](../operations/README.md)
- [Scheduled delivery operations](../operations/scheduled-delivery.md)
- [Events interfaces](../../events/interfaces/README.md)
- [Contributions interfaces](../../contributions/interfaces/README.md)
- [Platform transactional outbox](../../platform/transactional-outbox.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
