# Rollback Runbook

## Principles

- Application rollback redeploys the previously approved immutable image digest.
- Database rollback is never automatic.
- Prefer backward-compatible migrations and forward fixes.
- Restore from backup only when data integrity requires it and the incident owner approves the data-loss implications.

## Application rollback

Use the same environment and endpoint controls as deployment:

```bash
ENV_FILE=deploy/staging.env \
STAGING_URL=https://staging.example.test \
./bin/rollback ghcr.io/owner/kingshot-alliance@sha256:<previous-64-hex-digest>
```

The rollback command rejects mutable tags and delegates to the normal deployment health gate with migrations disabled. It still creates a pre-change database backup unless `SKIP_BACKUP=YES` is explicitly supplied.

Operational sequence:

1. Stop promotion and announce rollback.
2. Confirm the previous digest was accepted in staging.
3. Classify the current migration set for backward compatibility.
4. Run `bin/rollback` with the approved digest.
5. Verify `/up` and `/health/ready`.
6. Exercise critical workflows and queue processing.
7. Monitor errors, latency, queues, and database health.
8. Record timestamps, image digests, release SHAs, and impact.

## Database changes

Classify the migration:

- **Expand-only:** the old application remains compatible; roll back only the image.
- **Destructive but reversible:** execute a reviewed `down` migration only with explicit approval.
- **Irreversible or data-changing:** deploy a forward fix or restore an approved checksummed backup.

Never use application rollback as implicit approval to reverse database changes.
