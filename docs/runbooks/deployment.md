# Deployment Runbook

## Release artifact

Deploy an immutable production image built from a tagged commit. Record:

- source commit
- image digest
- application version
- build and dependency evidence
- migration set
- rollback image

## Staging deployment

1. Confirm CI and security checks pass.
2. Create a PostgreSQL backup and verify its manifest.
3. Deploy the release image to staging by digest.
4. Run migrations once using a release job.
5. Start application, worker, and scheduler processes.
6. Verify `/up` and `/health/ready`.
7. Exercise the home page, database, cache, queue, mail, and object-storage paths.
8. Verify JSON logs include version, release SHA, request ID, and trace ID.
9. Observe error rate, latency, queue depth, worker failures, and database health.
10. Record acceptance evidence.

## Production promotion

Production promotion uses the same image digest validated in staging. Configuration values may differ, but the image must not be rebuilt.

Migrations are run by one controlled release job. Web instances must never race to migrate during startup.

## Post-deployment

Observe the release through the agreed stabilization window. Close only after the release checklist and monitoring evidence are complete.
