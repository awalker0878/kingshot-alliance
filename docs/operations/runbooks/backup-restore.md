# Backup and restore runbook

Status: Current

Repository helpers are `bin/backup` and `bin/restore` (also exposed through Make targets where configured).

## Backup

A completed database backup must include the archive and its integrity/provenance metadata. Incomplete command output is not a valid backup.

```bash
make backup
```

For production, database recovery is only one part of the recoverable system. Treat the following as one recovery set:

- PostgreSQL data;
- private/durable media/object storage;
- application encryption key and required secret configuration.

## Restore

Restore is destructive and requires explicit operator intent. Use the repository restore helper and confirmation mechanism, then verify database readiness and application health before reopening normal traffic.

```bash
CONFIRM_RESTORE=YES make restore FILE=backups/database-....sql.gz
```

## Verification

After restore, verify schema/migrations, account authentication, representative Player/Alliance/Event data, readiness, queue processing and access to private media. A database-only CI restore demonstration is not sufficient production disaster-recovery evidence.