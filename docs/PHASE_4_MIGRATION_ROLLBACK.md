# Phase 4 Migration and Rollback — Recruitment

## Scope

Phase 4 introduces the recruitment schema through:

- `2026_08_07_030000_create_recruitment_tables.php`
- `2026_08_07_031000_add_recruitment_anonymization.php`

The schema supports recruitment settings/questions, application invitations, candidates and answers, stage history, reviewers, notes, tags, decision communications/templates, onboarding, and retention/anonymization state.

## Forward migration

Production and staging deployments use the standard immutable application image and run Laravel migrations against PostgreSQL before the new application version is considered ready. Phase 4 application code must not assume recruitment tables or anonymization columns are present before migration completion.

## Rollback verification

`RecruitmentMigrationRollbackTest` exercises the Phase 4 migration rollback/forward path against the test database. The test verifies that Phase 4 objects can be removed and recreated without leaving the schema in a partially migrated state.

Rollback is destructive for Phase 4 recruitment data. It is therefore an operational recovery action, not the default method for undoing a bad application release after real candidate records exist.

## Release rollback strategy

1. Prefer application rollback to the previous immutable image when the previous code remains schema-compatible.
2. Take/verify a current database backup before any destructive migration rollback.
3. Stop recruitment writes and scheduled recruitment retention work before reverting Phase 4 schema.
4. Run the tested Phase 4 `down()` migrations only when the accountable operator has accepted recruitment-data loss or a restore is planned.
5. If data must be preserved, restore the known-good backup rather than attempting manual partial table reconstruction.
6. Re-run migrations and smoke tests before reopening recruitment writes.

## Retention-specific caution

The anonymization migration adds fields used by the scheduled retention workflow. Do not run `recruitment:purge-expired` while rolling the recruitment schema backward. The purge is intentionally destructive to expired candidate personal data and is not reversible from application tables.

## Acceptance condition

Phase 4 is not accepted unless forward migrations pass in CI/staging, the automated rollback test remains green, the staging backup/restore drill succeeds, and operators have a documented path to recover the database before destructive rollback.
