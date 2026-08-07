# Phase 2 Migration and Rollback

## Migration

Phase 2 introduces `2026_08_07_010000_create_content_domain_tables.php`.

It creates additive content-domain tables for:

- media assets
- alliance public profiles and branding-media slots
- content categories
- content items
- immutable content revisions

The migration does not rewrite Phase 1 identity/membership rows. Tenant ownership is enforced through alliance foreign keys and composite same-alliance references.

## Application rollback

Normal application rollback deploys the previous immutable application image and **does not automatically reverse database migrations**. The Phase 2 schema is additive and is designed to remain present while the previous application version runs.

This is the preferred operational rollback because it avoids destroying content created after the migration.

## Schema rollback

The migration's `down()` path drops Phase 2 tables in dependency-safe order:

1. `content_revisions`
2. `content_items`
3. `content_categories`
4. `alliance_branding_media`
5. `alliance_profiles`
6. `media_assets`

A schema rollback is destructive to Phase 2 content and media metadata. It must only be used with an approved maintenance plan and a verified backup/recovery point.

## CI evidence

`ContentMigrationRollbackTest` runs under `RefreshDatabase` in an isolated test worker. It asserts all six Phase 2 tables exist, invokes the migration's `down()` method, asserts that all six tables are removed, then invokes `up()` and verifies that all six are recreated. CI runs this against PostgreSQL 18 as part of the normal PHP suite.

The inherited staging/recovery job separately proves database backup, destructive restore, runtime health, and immutable-image continuity.
