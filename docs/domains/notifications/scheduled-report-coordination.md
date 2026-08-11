# Scheduled Contribution report coordination

[← Notifications domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Notifications

## 1. Purpose

Defines due-time coordination for scheduled Contribution reports. Contributions owns report schedules, report versions, report-run meaning, and report content; Notifications owns safe repeated scheduling of the durable report request.

## 2. Scope and non-scope

In scope:

- selection of due enabled Contribution report schedules;
- deterministic schedule/due-time/report-version request identity;
- concurrent due-schedule claiming;
- creation/reuse of queued report-run/request state through the supported Contributions/Platform contracts;
- advancement of `next_due_at`; and
- scheduler catch-up.

Out of scope:

- report calculation/content semantics;
- export formatting;
- recipient Membership ownership;
- generic email/SMS/push delivery; and
- webhook transport.

## 3. Model and state

A due occurrence is identified from the Contributions-owned schedule ID, the specific due timestamp, and the report version.

The deterministic identity allows repeated scheduler runs to create/reuse one logical `ContributionReportRun`/`contribution.report.requested` request rather than duplicate report work.

Supported schedule cadences are daily, weekly, and monthly with schedule time-zone semantics.

## 4. Invariants

1. Contributions remains authoritative for schedule configuration and report semantics.
2. One schedule/due-time/report-version produces at most one logical queued report request.
3. Concurrent scheduler workers do not advance the same due schedule occurrence twice.
4. `next_due_at` advances according to the schedule's configured time zone/cadence.
5. Monthly advancement uses calendar/no-overflow semantics rather than fixed-day approximation.
6. Tenant and recipient membership identity are explicit.
7. Scheduler execution is at-least-once and safe to rerun.
8. Generic transport delivery is outside this capability.

## 5. Workflows

### Find due schedules

`contributions:queue-reports` selects enabled schedules whose `next_due_at` has arrived, with bounded batch size and concurrency-safe row claiming.

### Create/reuse request

For each due occurrence, Notifications derives the deterministic identity, creates or reuses the corresponding queued report run/request through the supported domain boundary, records recipient/report version/`as_of`, and creates or reuses the durable outbox message.

### Advance schedule

The schedule's next due time advances in its configured time zone according to daily/weekly/monthly cadence.

### Catch up

After scheduler interruption, rerun the bounded command. Existing deterministic identities prevent routine duplicate logical report requests.

## 6. Authorization, tenancy and privacy

Contributions owns who may create/manage report schedules. Notifications coordinates only already-authorized persisted schedules.

Each due occurrence carries the owning Alliance and recipient membership identifiers. Cross-tenant recipient/schedule combinations are invalid.

## 7. Persistence and query semantics

Contributions owns report schedules, versions, and runs. Notifications does not absorb semantic ownership merely because it coordinates due time.

Due schedule queries are bounded, ordered, and concurrency-safe. Existing deterministic run/request identity is reused on retry.

## 8. Events, integrations and background processing

The scheduler command runs on the shared scheduling foundation. Durable report requests use the Platform transactional outbox.

Webhook transport is unrelated unless a separately approved producer event becomes externally eligible through Integrations.

## 9. Failure, idempotency and concurrency

- Duplicate scheduler execution reuses deterministic request identity.
- Concurrent workers use row-lock/skip-locked semantics to avoid double schedule advancement.
- Existing queued request state is not multiplied on retry.
- Invalid tenant/recipient state fails closed.
- Scheduler interruption is recovered by rerun rather than manual `next_due_at` edits.

## 10. Operations and observability

Operators should inspect schedule enabled state, `next_due_at`, time zone/cadence, deterministic report-run identity, outbox publication state, and scheduler execution.

Repair schedule/outbox processing rather than creating duplicate report-run rows manually.

## 11. Tests and validation

Tests should cover:

- daily/weekly/monthly advancement;
- monthly no-overflow behavior;
- deterministic request identity;
- duplicate rerun;
- concurrent claiming;
- tenant/recipient scope; and
- Contributions versus Notifications ownership.

## 12. Related documentation

- [Notifications domain](README.md)
- [Contributions](../contributions/README.md)
- [Platform transactional outbox](../platform/transactional-outbox.md)
- [Background processing](../../operations/background-processing.md)
