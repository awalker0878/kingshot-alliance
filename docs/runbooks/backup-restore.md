# Backup and Restore Runbook

## Scope

The Phase 0 scripts protect PostgreSQL for local and staging demonstrations. Production design must also protect object storage, secret metadata, and deployment configuration through provider-native controls.

## Create a local backup

```bash
make backup
```

The script publishes two owner-readable-only files under `backups/` after dump, compression, integrity verification, and manifest creation all succeed:

- `database-<timestamp>.sql.gz`
- `manifest-<timestamp>.txt`

Temporary dump, archive, manifest, and restore paths are collision resistant and private. Interrupted or failed backup publication removes temporary and partially published output.

The manifest records the UTC timestamp, database filename, SHA-256 checksum, running release SHA, and running image reference. A failed image inspection records `unknown`; it never substitutes an incoming deployment target as source provenance.

## Create a staging backup

```bash
COMPOSE_FILE=docker-compose.staging.yml \
ENV_FILE=deploy/staging.env \
./bin/backup
```

The PostgreSQL service must be running. The script fails if Docker, gzip, checksum or temporary-file tooling, or the database service is unavailable.

When an application container is running, the script derives release and image provenance from that container. `APP_IMAGE` is only used when no application container exists.

## Verify

Check compression independently:

```bash
gzip -t backups/database-YYYYMMDDTHHMMSSZ.sql.gz
```

Confirm private file modes:

```bash
stat -c '%a %n' backups/database-YYYYMMDDTHHMMSSZ.sql.gz \
  backups/manifest-YYYYMMDDTHHMMSSZ.txt
```

Both generated files must be mode `600` unless an approved storage mechanism applies stronger access controls.

`bin/restore` automatically finds the matching manifest and compares the recorded SHA-256 checksum before changing the database. A missing or mismatched manifest fails closed.

`ALLOW_UNVERIFIED_RESTORE=YES` is an emergency override and requires an explicit integrity review and recorded approval.

## Restore locally

```bash
CONFIRM_RESTORE=YES make restore FILE=backups/database-YYYYMMDDTHHMMSSZ.sql.gz
```

## Restore staging

```bash
COMPOSE_FILE=docker-compose.staging.yml \
ENV_FILE=deploy/staging.env \
CONFIRM_RESTORE=YES \
./bin/restore backups/database-YYYYMMDDTHHMMSSZ.sql.gz
```

The restore command:

1. Verifies gzip integrity and the manifest checksum before stopping services.
2. Requires explicit confirmation.
3. Decompresses into an owner-only temporary file and rejects empty output.
4. Stops application, web, worker, and scheduler services.
5. Recreates the selected PostgreSQL database with existing connections forced closed.
6. Imports with `ON_ERROR_STOP` enabled.
7. Runs outstanding migrations using the application image.
8. Restarts application services only after import and migration succeed.
9. Removes temporary restore data on normal exit, failure, or interruption.

Verify `/health/ready`, runtime service state, image identity, and representative records after completion.

## Demonstration evidence

A phase or release restore test records:

- source release SHA and image digest/reference
- backup timestamp and checksum
- archive and manifest file modes
- target environment
- restore start and finish time
- migration result
- readiness result
- post-restore runtime image identity
- representative record counts
- tester and reviewer

Never claim a backup is valid until a restore has been demonstrated.
