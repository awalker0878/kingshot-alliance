# Contributions and Reporting

## Purpose

Phase 5 provides explainable contribution and participation reporting for an alliance. It deliberately separates recorded facts, calculated metrics, and subjective assessments so totals remain understandable and auditable.

## Domain model

### Contribution categories

A category defines the meaning of a contribution record:

- name, description, and unit;
- daily, weekly, monthly, season, or custom period;
- optional per-member goal;
- evidence requirement;
- whether members may self-report;
- whether the category participates in leaderboards;
- data class: `recorded_fact`, `calculated_metric`, or `subjective_assessment`;
- for calculated metrics, a calculation key, version, and human-readable explanation.

Season and custom categories have explicit effective start/end dates. Calculated categories must carry versioned calculation metadata.

### Contribution records

Records are intentionally historical rather than mutable totals. Each record carries:

- alliance, category, and membership identity;
- source (`manual`, `self_reported`, or `event_participation`);
- data class and value;
- effective period;
- pending, approved, or reversed status;
- evidence and actor attribution;
- calculation provenance when derived;
- correction/reversal provenance.

Corrections create a replacement record linked through `correction_of_record_id`. The original is retained and reversed. This preserves the evidence needed to reproduce and explain historical reporting.

### Event-derived participation

The supported Phase 5 calculated rule is `event_attendance`. Reconciliation reads Phase 3 `event_registrations` whose status is `attended`, materializes one approved contribution record per category/registration, and records the configured calculation version. Reconciliation is idempotent. If attendance changes, the derived record is reversed; if attendance returns to `attended`, the same record is restored.

Event attendance remains authoritative in the Events domain. Contributions does not duplicate or independently edit attendance truth.

### Data quality

Data-quality flags identify:

- records missing evidence required by their category;
- active members missing a record for an active category/current period.

Refreshing data quality never changes contribution values. Flags may be resolved independently after the underlying issue is corrected or reviewed.

## Reporting

Member reporting contains only the member's own record history/progress plus alliance leaderboards for categories where leaderboards are enabled.

The management dashboard includes:

- contribution totals and pending approvals;
- attendance/no-show summary;
- recruitment volume/joined count;
- membership joins/leaves;
- missing-data flags;
- calculation descriptions/versions;
- scheduled report state and report-run history.

Leaderboards sum approved, non-reversed records for the category's current effective period. A category can opt out completely.

## Exports

Interactive exports support CSV and Excel-readable SpreadsheetML. Export rows preserve record-level provenance rather than publishing only aggregate totals. Every export creates an auditable report run with:

- report version;
- format;
- row count;
- SHA-256 checksum;
- requesting user and alliance;
- completion timestamp.

## Scheduled reports

Schedules are alliance scoped and identify a recipient membership, cadence, timezone, next due time, and report version. The Notifications domain queues due report requests through the transactional outbox with a deterministic idempotency key. This provides retry safety without introducing Phase 6 integration/webhook infrastructure.

## Authorization and tenancy

All member surfaces require authenticated, verified, active-alliance context. Management, exports, corrections, approvals, reversals, reconciliation, quality operations, and schedules require `contributions.manage`; privileged HTTP operations also require recent password confirmation. Mutable identifiers are re-resolved under the active `alliance_id` and fail closed for another tenant.

## Phase boundary

Phase 5 does not add platform administration, billing, generic webhooks, external API credentials, support impersonation, or Phase 6 lifecycle tooling.
