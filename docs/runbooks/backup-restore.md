# Backup and Restore Runbook

## Scope

The Phase 0 script backs up PostgreSQL. Production design must also protect object storage, secrets metadata, and deployment configuration through provider-native controls.

## Create a local backup

```bash
make backup
```

The script writes a compressed SQL dump and SHA-256 manifest under `backups/`.

## Verify

```bash
gzip -t backups/database-*.sql.gz
sha256sum -c backups/manifest-*.txt
```

The manifest includes additional key-value lines; select the checksum line when using `sha256sum -c`.

## Restore locally

```bash
CONFIRM_RESTORE=YES make restore FILE=backups/database-YYYYMMDDTHHMMSSZ.sql.gz
```

The script stops application processes, recreates the database, imports the dump, restarts services, and applies outstanding migrations.

## Demonstration evidence

A phase or release restore test records:

- source release SHA
- backup timestamp and checksum
- target environment
- restore start and finish time
- migration result
- readiness result
- representative record counts
- tester and reviewer

Never claim a backup is valid until a restore has been demonstrated.
