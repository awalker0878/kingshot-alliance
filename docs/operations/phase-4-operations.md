# Phase 4 Operations — Recruitment

## Operational scope

Phase 4 adds public recruitment intake, private recruiter workflows, recruitment metrics, application invitation tokens, audit/outbox events, and a scheduled retention/anonymization process. It does not add a separate queue topology or external candidate-communication provider.

## Runtime surfaces

### Public application

- Public mode exposes `/alliances/{slug}/apply` only when recruitment is open.
- Invitation mode requires a valid recruitment application token.
- Public submission is rate limited by the `recruitment-application` limiter.
- Public responses must never contain recruiter notes, private candidate records, or recruitment metrics.

### Private recruiter workspace

- `/alliance/recruitment` requires authentication, verification, active-alliance context, and `recruitment.manage`.
- Recruiter mutations are active-alliance scoped and significant changes write audit/outbox records.
- Alliance-home navigation exposes the workspace only to users authorized to manage recruitment.

## Scheduled retention

The command:

```text
php artisan recruitment:purge-expired --limit=100
```

anonymizes declined/withdrawn candidates whose `retention_due_at` is due. The scheduler runs it daily at 03:15 with `onOneServer()` and `withoutOverlapping(30)` and a production schedule limit of 250 records.

The purge:

- rechecks candidate eligibility under a row lock;
- removes application answers, recruiter notes, communications, reviewer/tag links, and candidate onboarding rows;
- clears identifying candidate fields and replaces the email with a non-routable deletion marker;
- preserves minimal non-identifying stage/audit context needed for explainability;
- emits audit and outbox records.

Operators should investigate repeated purge failures because they indicate candidate data may remain identifiable beyond configured retention.

## Transactional outbox

Recruitment actions use the existing transactional outbox foundation for durable business-change records. `outbox:publish` continues to run every minute. Phase 4 does not introduce a separate publisher. Existing outbox backlog/error monitoring therefore covers recruitment events as well.

## Health and alert implications

Phase 4 should be included in existing application, database, scheduler, and outbox health monitoring. Operational alerts should cover:

- failed or missing scheduler execution;
- sustained outbox backlog or publish failures;
- elevated public-application error/rate-limit activity;
- database errors affecting recruitment writes;
- repeated retention purge failures;
- authorization/error spikes on private recruiter routes.

Candidate names, emails, answers, and reviewer notes should not be copied into metrics labels or routine structured logs.

## Backup and recovery

Recruitment data is stored in PostgreSQL and is included in the standard database backup/restore drill. A successful database restore should be followed by:

1. application configuration validation;
2. database migration status verification;
3. authenticated recruiter smoke test;
4. public application smoke test for a non-sensitive staging alliance;
5. scheduler/outbox health verification.

A restored backup can reintroduce candidate data that had later been anonymized. After disaster recovery, operators must run the normal retention command and confirm due candidates are anonymized again.

## Incident triage

### Suspected cross-alliance exposure

Disable the affected recruitment route/workflow if necessary, preserve logs/audit identifiers, identify the active alliance and object IDs involved, and treat any confirmed candidate disclosure as a security/privacy incident. Do not copy candidate content into broad incident channels.

### Public application abuse

Review rate-limit activity, application volume, duplicate patterns, and alliance recruitment mode. Close recruitment temporarily through alliance settings if intake must be stopped while preserving existing candidate records.

### Retention job failure

Run the purge command manually with a bounded limit after fixing the underlying issue. Confirm the anonymized count and audit records. Never directly delete candidate rows as a routine substitute because that can break history and referential integrity.

### Outbox backlog

Use the existing outbox runbook. Recruitment business transactions remain committed even if publication is delayed; do not replay the business action merely to force an outbox event.

## Deployment and rollback

Phase 4 follows the existing immutable-image staging process. Before acceptance, CI must pass frontend/PHP checks, PostgreSQL migrations, staging deployment, backup/restore, and image vulnerability scanning. See [Phase 4 migration and rollback](phase-4-migration-rollback.md) before any destructive schema rollback.
