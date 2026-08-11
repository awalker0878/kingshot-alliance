# Content operations profile

[← Content domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Content  
**Code owner:** `app/Domain/Content`  
**Primary operational boundary:** scheduled publication plus private media screening/storage/public-branding eligibility

## 1. Operational purpose and runtime shape

Content owns synchronous authoring/revision/media workflows plus the recurring `content:publish-scheduled --limit=100` scheduler command. Media may depend on private S3-compatible storage and the configured scanner contract.

## 2. Persistent state and ownership

Durable PostgreSQL state includes content/page/revision publication state, scheduled publication timing, media metadata/lifecycle/scanner result and branding references. Media bytes live on the configured private media disk and are therefore part of the domain recovery set even though they are not database rows.

## 3. Configuration and runtime dependencies

Content depends on PostgreSQL, scheduler continuity, `CONTENT_MEDIA_DISK`, `CONTENT_MEDIA_MAX_KB`, filesystem/S3 configuration and production malware-scanner/object-storage controls. Production requires the approved private S3-backed media posture documented in the shared configuration reference.

## 4. Normal flow and background processing

Authoring/media changes are synchronous. Scheduled content is rechecked under lock while still scheduled and due before publication. The scheduler command runs every minute with `onOneServer` and overlap protection. Media becomes publicly usable only through supported clean/active eligibility and branding selection.

## 5. Health, observability and diagnostics

For publishing, inspect due scheduled records, scheduler process/list, command errors and audit/request correlation. For media, inspect tenant ownership, lifecycle/scanner state, private object presence/metadata and branding references; readiness does not actively probe S3 or scanner health.

## 6. Failure modes and diagnosis

Primary failures are scheduler stoppage, database lock/error, stale/invalid publication state, scanner failure, object-store unavailability, missing private object, cross-tenant media reference or branding attachment to an invalid asset.

## 7. Recovery, replay and reconciliation

After scheduler/database recovery, rerun the bounded publish command; already-published/not-due items fail the state recheck. Scanner/storage failures remain fail closed. Reconcile media metadata against the private object only through supported application/recovery procedure; never mark rejected/missing media clean by direct SQL.

## 8. Backup, restore, migration and rollback

Content recovery requires PostgreSQL plus private media/object storage and the application encryption/configuration recovery set. A database-only restore can leave media references unusable. After restore/rollback verify representative published/scheduled content, media object availability and branding eligibility.

## 9. Capacity, query and performance boundaries

Normal scheduled publication uses a bounded default limit of 100. Upload size is configuration bounded. Higher catch-up batches or object-store recovery scans require capacity evidence; repository fixture/query tests are regression gates, not production throughput claims.

## 10. External-service degradation

Object-storage or scanner degradation must fail closed for new/unsafe media while unrelated text content may continue according to application behavior. Do not infer S3/scanner readiness from `/health/ready`; provider monitoring/evidence is external.

## 11. Safe operator actions and stop conditions

Safe actions are restore scheduler/database/storage health, rerun the bounded due-publish command, verify object metadata and detach/replace invalid branding through supported flows. Stop if recovery would require publishing rejected media, bypassing scanner state, crossing tenants or restoring database without the required media recovery set.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, request/trace IDs, content/media IDs, scheduled/due timestamps, command limit/result, scanner/lifecycle state and backup/object-storage recovery identifiers. See [Scheduled publishing and media](scheduled-publishing-and-media.md), [background processing](../../../operations/background-processing.md), [configuration](../../../operations/configuration-reference.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Content security profile](../security/README.md).
