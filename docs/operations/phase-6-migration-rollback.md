# Phase 6 migration and rollback

Phase 6 uses the canonical migration `2026_08_07_050000_create_platform_scale_and_administration_tables.php` because the application is pre-production. Schema defects found before launch must be corrected in that migration rather than adding compatibility or cleanup migrations.

## Forward migration

The migration adds alliance lifecycle columns, user deletion/anonymization timestamps, platform-admin/plan/configuration/usage/legal-hold/account-deletion/export metadata tables, and integration credential/webhook tables. It seeds the standard plan and assigns standard plan/settings rows to pre-existing alliances.

Validate forward migration on PostgreSQL with `php artisan migrate --force`. Confirm plan/settings assignment for existing alliances and normal default provisioning for newly created alliances.

## Rollback

The Phase 6 migration is currently the latest product migration, so rollback is direct:

`php artisan migrate:rollback --step=1 --force`

Rollback removes Phase 6 tables and lifecycle/account columns. It is destructive for Phase 6-only state (administrator grants, webhooks, API credential metadata, lifecycle timestamps, usage snapshots, holds, export metadata, and deletion requests). Take a verified backup before rollback when preservation matters.

Reapply with `php artisan migrate --force`. The integration test `PlatformMigrationRollbackTest` exercises down/up behavior against the test database.

## Application rollback

Rollback code and database as one release unit. Do not run pre-Phase-6 application code against a database that contains partially executed Phase 6 lifecycle changes without an explicit recovery plan. If a tenant was suspended/closed/deleted during a failed deployment, record the intended final lifecycle state before database rollback so it can be restored deliberately after recovery.
