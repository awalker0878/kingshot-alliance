# Phase 6 disaster recovery exercise

## Objective

Demonstrate that the service can recover PostgreSQL data, private media, encrypted application fields, queue processing, and tenant isolation without silently losing lifecycle or integration state.

## Exercise sequence

1. Record the deployed image digest, application commit, migration level, and configuration/secret version.
2. Create known evidence in staging: an alliance with content/media, memberships, contribution/event data, an API credential, a webhook subscription, usage snapshot, audit event, and unpublished/published outbox history.
3. Record representative row counts, media checksum, export checksum, and the known tenant ID.
4. Take/identify the normal PostgreSQL and media backups plus the protected application encryption key/configuration bundle.
5. Destroy or isolate the disposable staging data plane.
6. Recreate infrastructure from the same immutable deployment definition.
7. Restore PostgreSQL and private media/object storage.
8. Restore application secrets/configuration through the approved secret-management path.
9. Run `php artisan migrate --force` and `php artisan app:config-check`.
10. Start scheduler and queue workers by partition: core, integrations, maintenance.
11. Validate health/readiness and authenticate a normal tenant user.
12. Verify suspended/closed/deleted tenant rules using a known lifecycle test tenant.
13. Verify representative media checksum and tenant-complete export checksum/table counts.
14. Verify an API credential created before backup still authenticates its tenant and cannot cross tenant boundaries.
15. Verify the restored encrypted webhook signing secret can produce a valid signature; deliver to a controlled test endpoint.
16. Run bounded outbox/webhook recovery commands and confirm no uncontrolled duplicate fan-out.
17. Verify platform admin access still requires verified email, MFA, grant, and password confirmation.
18. Record recovery point, recovery duration, discrepancies, and remediation owners.

## Pass criteria

- PostgreSQL schema/data and private media are mutually consistent for sampled records.
- Application encrypted fields remain decryptable with restored keys.
- Tenant isolation/lifecycle controls behave identically after restore.
- Queue processing resumes without an unbounded retry storm.
- Outbox/webhook idempotency prevents routine duplicate materialization.
- Known exports reconcile expected table counts/checksums or documented time-of-backup differences.
- Operational access and audit records are present.

## Rollback demonstration

The CI staging/recovery job remains the automated release gate for image deployment and backup/restore. Phase 6 additionally has migration rollback/reapply coverage. A production-readiness exercise should record an immutable-image rollback to the previous release and, when the schema rollback is safe/required, execute it only from a verified backup following `PHASE_6_MIGRATION_ROLLBACK.md`.
