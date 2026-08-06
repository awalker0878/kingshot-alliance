# Backup and Restore Runbook

## Scope

The Phase 0 scripts protect PostgreSQL for local and staging demonstrations. Production design must also protect object storage, secret metadata, and deployment configuration through provider-native controls.

## Create a local backup

```bash
make backup
```

The script writes two files under `backups/`:

- `database-<timestamp>.sql.gz`
- `manifest-<timestamp>.txt`

The manifest records the UTC timestamp, database filename, SHA-256 checksum, release SHA, and image reference.

## Create a staging backup

```bash
COMPOSE_FILE=docker-compose.staging.yml \
ENV_FILE=deploy/staging.env \
APP_IMAGE=ghcr.io/owner/kingshot-alliance@sha256:<digest> \
./bin/backup
```

The PostgreSQL service must be running. The script fails if Docker, gzip, checksum tooling, or the database service is unavailable.

## Verify

Check compression independently:

```bash
gzip -t backups/database-YYYYMMDDTHHMMSSZ.sql.gz
```

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

1. Verifies the backup checksum.
2. Requires explicit confirmation.
3. Stops application, web, worker, and scheduler services.
4. Recreates the selected PostgreSQL database.
5. Imports with `ON_ERROR_STOP` enabled.
6. Runs outstanding migrations using the application image.
7. Restarts application services.

Verify `/health/ready` and representative records after completion.

## Demonstration evidence

A phase or release restore test records:

- source release SHA and image digest
- backup timestamp and checksum
- target environment
- restore start and finish time
- migration result
- readiness result
- representative record counts
- tester and reviewer

Never claim a backup is valid until a restore has been demonstrated.
