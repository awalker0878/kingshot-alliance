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

Temporary dump, archive, manifest, and restore paths are collision resistant and private. Interrupted or failed publication removes temporary and partially published output.

The verified archive is renamed into place first. Its manifest is renamed last and acts as the completion marker that the pair is eligible for restore. A final archive without a manifest is incomplete and must not be restored without explicit unverified-restore approval.

The manifest records the UTC timestamp, exact database filename, SHA-256 checksum, running release SHA, and running image reference. A failed image inspection records `unknown`; it never substitutes an incoming deployment target as source provenance.

## Create a staging backup

```bash
COMPOSE_FILE=docker-compose.staging.yml \
ENV_FILE=deploy/staging.env \
./bin/backup
```

The PostgreSQL service must be running. The script fails if Docker, gzip, checksum or temporary-file tooling, or the database service is unavailable.

When an application service container exists, including a stopped or unhealthy container, the script derives release and image provenance from that container configuration. `APP_IMAGE` is only a fallback when no application container exists.

`bin/deploy` inspects PostgreSQL directly. Any populated public schema is backed up before migrations even when the previous application process is stopped; only a verified empty first-deployment schema skips backup unless `SKIP_BACKUP=YES` is explicitly authorized.

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

Inspect the manifest and confirm it contains exactly one `database_file` entry naming the selected archive and exactly one 64-character lowercase `database_sha256` entry. `bin/restore` enforces these checks automatically before changing the database.

`ALLOW_UNVERIFIED_RESTORE=YES` is an emergency override for a missing manifest only. It requires an explicit integrity review and recorded approval; it does not bypass gzip validation or database readiness checks.

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

1. Verifies gzip integrity.
2. Requires a matching manifest unless the emergency override is explicitly approved.
3. Confirms the manifest contains exactly one filename and checksum entry, names the selected archive, and matches its SHA-256 checksum.
4. Confirms the PostgreSQL service is running and ready before creating an application outage.
5. Requires explicit destructive confirmation.
6. Decompresses into an owner-only temporary file and rejects empty output.
7. Stops application, web, worker, and scheduler services.
8. Recreates the selected PostgreSQL database with existing connections forced closed.
9. Imports with `ON_ERROR_STOP` enabled.
10. Runs outstanding migrations using the application image.
11. Restarts application services only after import and migration succeed.
12. Removes temporary restore data on normal exit, failure, or interruption.

The script reports import completion, not recovery acceptance. Verify `/health/ready`, runtime service state, image and release identity, and representative records before declaring recovery successful.

## Demonstration evidence

A phase or release restore test records:

- source release SHA and image digest/reference
- backup timestamp, exact filename, and checksum
- archive and manifest file modes
- target environment
- restore start and finish time
- PostgreSQL readiness before outage
- migration result
- readiness result
- post-restore runtime image and release identity
- representative record counts
- tester and reviewer

Never claim a backup is valid until a restore has been demonstrated.
