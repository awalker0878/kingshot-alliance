# Rollback Runbook

## Principles

- Application rollback redeploys the previous immutable image.
- Database rollback is not automatic.
- Prefer backward-compatible migrations and forward fixes.
- Restore from backup only when data integrity requires it and the incident owner approves data-loss implications.

## Application rollback

1. Stop promotion and announce rollback.
2. Drain or pause queue workers where message compatibility is uncertain.
3. Deploy the previously approved image digest.
4. Run `/up` and `/health/ready`.
5. Verify critical workflows.
6. Resume compatible workers.
7. Monitor errors, latency, queues, and database health.

## Database changes

Classify the migration:

- **Expand-only:** old application remains compatible; roll back the image.
- **Destructive but reversible:** execute the reviewed `down` migration only with approval.
- **Irreversible or data-changing:** deploy a forward fix or restore an approved backup.

Record the decision, commands, timestamps, release SHAs, and data impact.
