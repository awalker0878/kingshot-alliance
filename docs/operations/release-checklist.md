# Release Checklist

## Before build

- [ ] Scope and release owner confirmed
- [ ] All required pull requests merged
- [ ] CI, CodeQL, dependency review, and vulnerability scans pass
- [ ] Database migrations reviewed for locks, duration, and rollback
- [ ] Configuration changes validated in staging
- [ ] Backup completed and restore point recorded
- [ ] Feature flags and operational toggles documented
- [ ] External production controls have named owners and evidence locations

## Build and staging

- [ ] Immutable image built from a tagged commit
- [ ] Image digest and software bill of materials retained
- [ ] Staging deployment completed
- [ ] `/up` and `/health/ready` pass
- [ ] `/platform` preserves the unauthenticated authentication boundary
- [ ] Queue, scheduler, mail, storage, and database smoke tests pass
- [ ] Logs contain release SHA, request ID, and trace ID
- [ ] Migration and application rollback rehearsed
- [ ] Product acceptance recorded

## Production

- [ ] Change window and communication confirmed
- [ ] HTTPS, trusted proxies, DNS, mail, object storage, secrets, and backup ownership confirmed
- [ ] Webhook/integration egress restrictions verified outside the application boundary
- [ ] At least two active platform administrators use verified accounts with confirmed MFA
- [ ] Workers drained or paused where required
- [ ] Migrations applied once by the release job
- [ ] Application image deployed by digest
- [ ] `sh bin/launch-check` passes against the production deployment
- [ ] `php artisan app:launch-check --json` output retained as release evidence
- [ ] Health checks and key workflows verified
- [ ] Error rate, latency, queue depth, outbox backlog, failed jobs, webhook failures, database health, and storage capacity observed
- [ ] Database + private-media + application-key recovery evidence is current
- [ ] `docs/product/PRODUCTION_LAUNCH_APPROVAL.md` contains no pending production control
- [ ] Release announcement published

## Close

- [ ] Deferred work recorded
- [ ] Incident or rollback notes recorded
- [ ] Runbooks and ADRs updated
- [ ] Release evidence retained
