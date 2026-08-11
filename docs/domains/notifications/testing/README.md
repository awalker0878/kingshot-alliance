# Notifications testing and evidence

[← Notifications domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Notifications  
**Code owner:** `app/Domain/Notifications`  
**Primary validation boundary:** Deterministic Event-reminder and Contribution-report materialization, outbox handoff, source recheck, and at-least-once consumer idempotency  
**P5 evidence decision:** Living suite map with Phase 3/5 and P3 operations evidence reused

## 1. Critical claims and validation ownership

Notifications validation must prove deterministic reminder/report delivery identities, duplicate-safe scheduler execution, source-state eligibility rechecks, safe outbox handoff, idempotent `OutboxPublished` consumption, cancellation/ineligibility suppression and separation from Events/Contributions source ownership.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, and `Unit`. `TenantIsolation` applies through member reminder/report workflows and source-domain isolation. No standalone Notifications performance SLA is accepted.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Notifications ownership of delivery coordination while Events owns occurrence/registration facts, Contributions owns report schedules/report semantics, Platform owns outbox publication and Integrations owns external webhooks.

The absence of a direct Notifications public/API route is an intentional current boundary.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence proves reminder inbox/delivery state remains scoped to active Alliance and membership, scheduled report recipients remain eligible active members and private source data is not exposed across tenants.

[Notifications security](../security/README.md) defines minimized payload/privacy requirements.

## 5. Feature, interface and integration validation

Phase 3 Event reminder tests cover materialization, duplicate-safe queue/publish behavior, cancellation suppression, sent state and reminder-inbox isolation. Phase 5 evidence covers scheduled report request idempotency and Contributions→Notifications coordination.

[Notifications interfaces](../interfaces/README.md) maps scheduler/actions/outbox consumers; there is no direct HTTP interface to validate independently.

## 6. Idempotency, concurrency and asynchronous validation

Deterministic delivery/run identities are the central regression claim. Scheduler retries must not create duplicate logical reminders/reports; `OutboxPublished` is at-least-once, so `MarkEventReminderPublished` must remain duplicate-safe and ignore unrelated messages.

Source eligibility is rechecked before advancing work rather than trusting stale queued intent.

## 7. Persistence, migration, rollback and recovery evidence

Notification persistence arrived with the accepted Event/Contribution workflows and is covered by their migration/rollback evidence plus current CI forward migrations and backup/restore.

Safe scheduler catch-up/outbox reconciliation is documented in [Notifications operations](../operations/README.md) and [Scheduled delivery operations](../operations/scheduled-delivery.md).

## 8. Performance, query and capacity evidence

Commands use explicit bounded batch limits, but no general notification throughput/latency SLA is accepted. Bounded scheduler work is a safety/capacity claim exercised by Integration/operations evidence rather than a fabricated service target.

## 9. Accessibility and frontend evidence

The member reminder inbox is part of accepted [Phase 3 accessibility review](../../../product/phase-3-accessibility.md), including textual status and an `aria-live="polite"` section. Notifications has no separate standalone management UI in the current runtime.

`npm run check` protects frontend quality only.

## 10. Historical accepted evidence

Primary historical evidence is [Phase 3 exit report](../../../product/phase-3-exit-report.md) for reminders and [Phase 5 exit report](../../../product/phase-5-exit-report.md) for scheduled Contribution report requests. P3 DCP operations evidence later normalized recovery/coordination semantics.

## 11. Evidence identity, retention and supersession

Phase 3/5 SHAs/check IDs/test counts stay historical. Living notification validation follows current code/tests.

Future acceptance evidence must record exact revision/workflow identity under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Notifications has no direct public API, no ownership of Event/Contribution facts, no generic external-webhook delivery contract and no claim that queue publication equals delivery. No standalone throughput SLA is accepted.

Related documentation:

- [Notifications domain](../README.md)
- [Notifications security](../security/README.md)
- [Notifications operations](../operations/README.md)
- [Scheduled delivery operations](../operations/scheduled-delivery.md)
- [Notifications interfaces](../interfaces/README.md)
- [Events testing](../../events/testing/README.md)
- [Contributions testing](../../contributions/testing/README.md)
- [Platform testing](../../platform/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
