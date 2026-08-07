# Release Checklist

## Before build

- [ ] Scope and release owner confirmed
- [ ] All required pull requests merged
- [ ] CI, CodeQL, dependency review, and vulnerability scans pass
- [ ] Database migrations reviewed for locks, duration, and rollback
- [ ] Configuration changes validated in staging
- [ ] Backup completed and restore point recorded
- [ ] Feature flags and operational toggles documented

## Build and staging

- [ ] Immutable image built from a tagged commit
- [ ] Image digest and software bill of materials retained
- [ ] Staging deployment completed
- [ ] `/up` and `/health/ready` pass
- [ ] Queue, scheduler, mail, storage, and database smoke tests pass
- [ ] Logs contain release SHA, request ID, and trace ID
- [ ] Migration and application rollback rehearsed
- [ ] Product acceptance recorded

## Production

- [ ] Change window and communication confirmed
- [ ] Workers drained or paused where required
- [ ] Migrations applied once by the release job
- [ ] Application image deployed by digest
- [ ] Health checks and key workflows verified
- [ ] Error rate, latency, queue depth, and database health observed
- [ ] Release announcement published

## Close

- [ ] Deferred work recorded
- [ ] Incident or rollback notes recorded
- [ ] Runbooks and ADRs updated
- [ ] Release evidence retained
