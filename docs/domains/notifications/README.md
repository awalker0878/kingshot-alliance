# Notifications domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Notifications`  
**Primary authorization boundary:** source-domain authorization/eligibility; tenant/Player identity remains explicit in persisted coordination state

## 1. Purpose and ownership

Notifications owns durable due-time coordination that should not live inside the source feature domain.

The current runtime has two independent capabilities: Event reminder delivery coordination and scheduled Contribution-report request coordination. Notifications does not own generic email/SMS/push/webhook transport.

## 2. Scope

In scope: durable Event reminder state, scheduler audience resolution/queueing/catch-up, scheduled Contribution report due-time coordination, deterministic request identity, and shared outbox interaction.

Out of scope: Events/Contributions source-state ownership, generic messaging providers, and webhook transport.

## 3. Domain model

The domain intentionally separates:

- [Event reminders](event-reminders.md) — reminder rules/deliveries and `pending`/`queued`/`sent`/`failed`/`skipped` lifecycle.
- [Scheduled Contribution report coordination](scheduled-report-coordination.md) — deterministic due schedule occurrence and durable report request.

## 4. Core invariants

1. Source feature domains remain authoritative for the facts/configuration that trigger notification work.
2. Scheduler execution is at-least-once and safe to rerun.
3. Persisted coordination state carries explicit Event scope/Player/source identity.
4. Deterministic identities prevent routine duplicate logical work.
5. Shared outbox publication is at-least-once; downstream handling remains idempotent.
6. Notifications is not generic external message transport.

## 5. Lifecycles and workflows

Event reminder audience resolution/due queueing/publication completion is defined in [event-reminders.md](event-reminders.md).

Contribution report schedule due-time selection/request identity/next-due advancement is defined in [scheduled-report-coordination.md](scheduled-report-coordination.md).

Both are designed for safe catch-up after scheduler/outbox interruption.

## 6. Authorization and tenancy

Source domains decide who may configure Event reminders or report schedules. Notifications coordinates persisted authorized state and keeps tenant/Player identity explicit. Member-facing reminder reads resolve under authenticated User and Player ownership context.

## 7. Cross-domain contracts

Consumes Events occurrence/participation facts, Kingdoms Player ownership/current context, Contributions report schedule/version/run semantics, and Platform transactional outbox/scheduler infrastructure.

Exposes durable reminder status to Events and scheduled report-request coordination to Contributions.

## 8. Persistence and data ownership

Notifications owns Event reminder rule/delivery state. Contributions retains ownership of report schedules/versions/runs even when Notifications coordinates due time.

## 9. Events, outbox and integrations

Current coordination uses scheduler + PostgreSQL + Platform outbox. Webhook transport is Integrations-owned and does not become Notifications responsibility because an outbox event exists.

## 10. HTTP, UI and API surfaces

Recent sent in-app reminders may be presented by Events. Notifications has no generic public notification/provider API.

## 11. Background processing

The current scheduler invokes bounded Event reminder and Contribution report coordination commands plus the shared outbox publisher. Detailed state/concurrency belongs in the capability files.

## 12. Failure, idempotency and concurrency

Both capabilities use deterministic identities and concurrency-safe due claiming. Persisted due/unpublished state enables catch-up without replaying source business actions. See the capability contracts for exact semantics.

## 13. Security and privacy

Payloads/logs contain only information needed for downstream coordination; unrelated private candidate/Player data and secrets are excluded. Event scope identity is never inferred from hidden global process state.

## 14. Observability and operations

Diagnose source eligibility/configuration, persisted coordination state, scheduler execution, and outbox publication separately. See [Background processing](../../operations/background-processing.md).

## 15. Testing and architecture enforcement

Tests protect deterministic identities, repeated scheduler execution, concurrent due claiming, catch-up, tenant isolation, and ownership boundaries with Events/Contributions/Platform/Integrations.

## 16. Explicit non-capabilities

Notifications does not provide generic email, SMS, push-provider, third-party messaging abstraction, or webhook transport.

## 17. Capability documents

- [Event reminders](event-reminders.md)
- [Scheduled Contribution report coordination](scheduled-report-coordination.md)

## 18. Related documentation

- [Events](../events/README.md)
- [Contributions](../contributions/README.md)
- [Platform](../platform/README.md)
- [Integrations](../integrations/README.md)
- [Background processing](../../operations/background-processing.md)
- [`app/Domain/Notifications/README.md`](../../../app/Domain/Notifications/README.md)
