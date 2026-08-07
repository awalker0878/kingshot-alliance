# Phase 2 Operations — Content and Public Presence

## Runtime responsibilities

Phase 2 adds three operational behaviors to the Phase 1 runtime:

1. Scheduled content publication.
2. Transactional outbox traffic for content/profile/media mutations.
3. Persistent uploaded media stored outside the database.

No new process role is introduced. The existing scheduler and worker roles remain the operational boundary.

## Scheduled publication

`content:publish-scheduled` runs every minute through the existing scheduler. The scheduled command uses single-server and overlap protection, while the publisher rechecks due state under database row locks before transitioning content.

Operational signals to watch:

- scheduler process availability
- oldest overdue `scheduled` content item
- command failures/exceptions
- database lock or connectivity errors

A scheduled item that is overdue should remain non-public until a successful publisher transition; the public query does not infer publication merely from `scheduled_for`.

## Audit and outbox

Every meaningful Phase 2 profile/content/media mutation writes an attributable audit event and a transactional outbox row with the same alliance boundary. Existing request/trace correlation continues to flow into audit records when an HTTP request exists.

The existing outbox publisher remains at-least-once and carries stable event idempotency keys through retries. Monitor:

- unpublished outbox age
- retry attempts and `last_error`
- worker/scheduler availability
- repeated publication failures

## Media storage

### Local and ephemeral environments

Development and the ephemeral CI staging deployment may use `CONTENT_MEDIA_DISK=local`. The local disk is private and tenant-prefixed. CI/local media is disposable test data and is **not** evidence of production media disaster recovery.

### External staging and production

The deployable staging template uses `CONTENT_MEDIA_DISK=s3`. Production startup fails closed unless Phase 2 media uses S3-backed storage, and any hosted configuration using S3 media requires a configured bucket.

Before production launch, the approved object-store configuration must define:

- private bucket/object access
- encryption and key ownership appropriate to the environment
- versioning or equivalent recovery protection
- retention/lifecycle rules for active and archived media
- backup/replication/recovery policy appropriate to service objectives
- monitoring for storage errors, access-denied responses, capacity/quotas, and failed uploads

`CONTENT_MEDIA_MAX_KB` controls the application upload limit; the default is 8192 KiB.

## Backup and restore boundary

`bin/backup` and `bin/restore` protect the PostgreSQL database. They **do not contain uploaded media objects**.

A database backup contains media metadata and object paths. A complete production recovery therefore requires the corresponding object-store recovery point to remain available. Operational recovery must not restore database metadata to a point whose referenced media objects have already been irreversibly deleted.

Normal application rollback continues to deploy the previous immutable image without automatically reversing Phase 2 schema migrations.

## Malware screening

`MediaScanner` is the integration contract. The built-in `BasicMediaScanner` rejects common executable/script signatures and is useful as a baseline and deterministic test implementation.

Before production launch, bind `MediaScanner` to the organization's approved malware-scanning service and validate timeout/failure behavior. Scanner failure must fail closed: an object that has not passed screening must never become an active public branding object.

Monitor scan rejection counts and scanner/storage exceptions. Rejected uploads are audited without persisting a media record or file.

## Public delivery and caching

Only clean, active image assets explicitly attached to a public branding slot can be streamed publicly. The controller sends `nosniff` and a one-hour public cache directive. Content text remains database-backed and rendered as escaped text through Inertia/Vue.

Changing branding may leave a prior representation in downstream caches for up to the configured response cache window. Emergency takedown procedures should account for CDN/proxy cache invalidation if an external cache is introduced.

## Search and capacity

Phase 2 search uses bounded database queries scoped by alliance and visibility before search filters are applied. Public and member result limits are configuration-capped. Monitor slow-query metrics if content volume grows; a dedicated search service is not required in Phase 2.

## Health, logs, metrics, and alerts

Phase 2 does not add a new public health endpoint or expose dependency detail. Existing liveness/readiness remains unchanged.

Structured logs and audit/outbox records should make these failure classes observable:

- content publication/scheduler failure
- outbox lag/retry exhaustion
- media scanner rejection/failure
- media storage read/write failure
- authorization/tenant-boundary denials through normal application exception logging

Alert thresholds are deployment-specific, but production should at minimum alert on unavailable scheduler/worker roles, sustained outbox lag, sustained overdue scheduled content, and persistent object-storage failures.

## Deployment smoke checks

Staging acceptance must continue to prove:

- immutable app/web/worker/scheduler roles
- database migrations
- liveness/readiness
- destructive database backup/restore
- vulnerability scanning

For external staging with S3 media configured, release readiness should additionally upload a test image, attach it as branding, verify public delivery, detach/archive it, and clean up the test object through the approved lifecycle process.
