# Phase 5 Operations — Contributions and Reporting

## Operational scope

Phase 5 adds contribution records, reporting dashboards, event-participation reconciliation, data-quality review, versioned exports, and scheduled report requests. It reuses the existing PostgreSQL, scheduler, Notifications domain, and transactional outbox. It does not add Phase 6 webhooks, external API credentials, billing, or platform-admin operations.

## Runtime surfaces

- `/alliance/contributions` is the authenticated member progress/history surface.
- `/alliance/contributions/manage` requires `contributions.manage`.
- privileged mutations and exports require recent password confirmation.
- self-report submissions are rate limited and create pending records only.
- export routes are rate limited and create report-run audit metadata.

## Event participation reconciliation

Leadership can reconcile `event_attendance` calculated categories against Phase 3 attendance records. The process is idempotent: it creates missing derived records, reverses records whose attendance no longer qualifies, and restores the same derived record when attendance is corrected back to attended.

Operators should not manually edit event-derived contribution rows. Correct the authoritative attendance status and rerun reconciliation instead.

## Scheduled reporting

The command:

```text
php artisan contributions:queue-reports --limit=50
```

queues due contribution report requests through the Notifications domain and transactional outbox. The scheduler runs the command every minute using `onOneServer()` and `withoutOverlapping(10)`.

Each due occurrence uses a deterministic SHA-256 idempotency key derived from schedule ID, due time, and report version. Re-running the queue operation cannot create a duplicate run for the same due occurrence.

## Exports and reproducibility

CSV and Excel-readable SpreadsheetML exports are produced from alliance-scoped record rows. Each export records the report version, format, row count, SHA-256 checksum, requesting user, and completion time. Use this metadata when comparing a previously distributed report with current application state.

## Data quality

The management workspace can refresh missing-data flags. Current Phase 5 checks identify required evidence that is missing and active members with no record in an active category/current period. Refreshing flags does not mutate contribution values.

## Monitoring and alerts

Operational monitoring should cover:

- scheduler failure or missed `contributions:queue-reports` execution;
- sustained transactional outbox backlog;
- repeated export failures or latency spikes;
- errors during event reconciliation;
- unusual authorization failures on management/export routes;
- growing counts of pending approvals or open data-quality flags;
- database errors affecting contribution/report tables.

Do not place evidence text, member emails, or subjective-assessment content into metrics labels.

## Backup and recovery

All Phase 5 persistence is PostgreSQL-backed and participates in the standard backup/restore process. After a restore:

1. verify migration status;
2. validate member and management contribution routes;
3. verify contribution/report table counts;
4. rerun event reconciliation if attendance changed after the restored point;
5. refresh data-quality flags;
6. verify scheduler and outbox health;
7. compare a sample export checksum/row count against expected restored data.

## Incident triage

For suspected cross-alliance exposure, disable the affected route if needed, preserve request/audit identifiers, identify both alliance IDs, and treat confirmed exposure as a security incident.

For incorrect calculated participation, correct Phase 3 attendance truth first and run reconciliation. Do not directly patch derived contribution totals.

For duplicate scheduled-report concerns, inspect `contribution_report_runs.idempotency_key` and matching `contribution.report.requested` outbox rows before replaying any operation.

## Deployment and rollback

Phase 5 follows the existing immutable-image staging, migration, backup/restore, and vulnerability-scan gates. See `PHASE_5_MIGRATION_ROLLBACK.md` before schema rollback.
