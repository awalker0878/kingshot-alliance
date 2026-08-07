# Phase 5 Migration and Rollback — Contributions and Reporting

## Migrations

Phase 5 introduces:

1. `2026_08_07_040000_create_contributions_and_reporting_tables.php`
2. `2026_08_07_040010_add_contribution_management_permission.php`

The schema migration creates contribution categories, immutable contribution records, data-quality flags, report schedules, and report runs. The permission migration creates `contributions.manage` and grants it to existing system owner and leader roles.

## Forward deployment

Before deployment:

- ensure the Phase 4 schema is current;
- take/verify the normal database backup;
- run the complete CI migration suite;
- confirm no unapproved Phase 6 runtime code is present.

Apply migrations normally with the release image. After migration:

- confirm all five Phase 5 tables exist;
- confirm `contributions.manage` exists;
- confirm existing owner and leader roles carry the new permission;
- smoke-test member and management reporting routes;
- create a synthetic contribution/category in staging and verify approval/export;
- run scheduled-report queuing against a synthetic due schedule.

## Rollback order

Rollback must reverse the permission migration before dropping Phase 5 tables:

1. `2026_08_07_040010_add_contribution_management_permission.php` down
2. `2026_08_07_040000_create_contributions_and_reporting_tables.php` down

The automated integration test executes this order and reapplies both migrations.

## Data-loss warning

Rolling back the Phase 5 schema deletes contribution records, correction/reversal history, data-quality flags, schedules, and report-run provenance. Do not perform a destructive rollback as a routine application fix after real Phase 5 data exists without an accepted backup/recovery decision.

Because the application is pre-production, no compatibility/bridge migration is retained. If a Phase 5 schema defect is found before launch, correct the canonical Phase 5 migration on the feature branch and revalidate clean migration/rollback rather than layering a cleanup migration.

## Recovery after rollback

To restore Phase 5 after a rollback:

1. restore the approved database backup if Phase 5 data must be recovered;
2. deploy the corrected release;
3. apply both Phase 5 migrations;
4. verify permission assignments;
5. run event-participation reconciliation;
6. refresh data-quality flags;
7. verify report schedules and outbox health;
8. regenerate exports rather than trusting files created from a discarded database state.

## Acceptance evidence

Phase 5 is not accepted until PostgreSQL migration/reapply tests, full application tests, staging deployment, and backup/restore validation pass on the final branch head.
