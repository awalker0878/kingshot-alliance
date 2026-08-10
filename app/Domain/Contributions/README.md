# Contributions domain

## Purpose

Owns Alliance contribution categories/records, explainable calculations, corrections/reversals, Event-attendance reconciliation, data-quality flags, reporting, exports, and scheduled report definitions.

## Owned code

Runtime code in this module owns Contribution persistence/calculation semantics, report schedules/runs, reconciliation, reporting/data-quality queries, and export workflows.

## Public contracts

- recorded facts, versioned calculated metrics, and explicit subjective assessments;
- append-oriented correction/reversal workflow;
- idempotent `event_attendance` reconciliation from Events-owned attendance;
- member/manager reporting and opt-in leaderboards; and
- scheduled report definitions consumed by Notifications for due-time coordination.

## Dependencies

- `Events` — authoritative Event attendance facts.
- `Memberships` — member/report-recipient identity.
- `Notifications` — scheduled report-request coordination.
- `Authorization` — `contributions.manage`.
- `Audit` / Platform outbox — privileged/durable evidence.

## Canonical documentation

- [`docs/domains/contributions/`](../../../docs/domains/contributions/README.md)
