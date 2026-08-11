# Content scheduled publishing and media operations

[← Content operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Content  
**Capability:** Scheduled publishing and private media  
**Code owner:** `app/Domain/Content`

## 1. Scope, prerequisites and safety boundary

Use this runbook when scheduled Content is not publishing, private media upload/screening/storage is failing, or branding references appear inconsistent. Do not use it to bypass publication state, scanner decisions, tenant ownership or private-storage controls.

Prerequisites: identify the affected Alliance/content/media IDs, current release SHA, scheduler state, PostgreSQL health and—when media is involved—the configured media disk/provider state.

## 2. Runtime and persistent state

Scheduled publication is PostgreSQL state progressed by `content:publish-scheduled --limit=100` every minute. The command rechecks due/scheduled state under lock.

Media uses PostgreSQL metadata plus bytes on `CONTENT_MEDIA_DISK`; production media storage is an external private-storage recovery dependency. Scanner result/lifecycle and branding attachment determine whether an asset can be used publicly.

## 3. Healthy operating flow

1. Author schedules content or uploads media through supported application flows.
2. Scheduler continuously runs and invokes the bounded publication command.
3. Due content passes the locked state check and becomes published once.
4. Uploaded media passes validation/scanning, is stored privately and reaches an eligible lifecycle state.
5. Public branding references only a same-Alliance clean/active supported asset.

## 4. Signals and diagnostics

For publishing check `php artisan schedule:list`, scheduler process health, due records/status/timestamps, command errors and audit/request correlation. For media check media lifecycle/scanner state, tenant ownership, configured disk, object existence/provider errors and current branding references.

Shared `/health/ready` covers PostgreSQL/cache only; it does not prove media storage or scanner availability.

## 5. Failure modes and triage

- Scheduler absent/stalled: due records remain scheduled.
- Database/lock failure: command errors and state does not advance.
- Record no longer due/scheduled: no recovery action is required; state recheck intentionally skips it.
- Scanner failure/rejection: media remains unusable; treat as fail closed.
- Private object unavailable/missing: metadata may exist while serving/eligibility fails.
- Branding points at an invalid/archived asset: use supported detach/replace flow; do not mutate the foreign key directly.

## 6. Recovery, replay and reconciliation

After fixing scheduler/database health, run the normal bounded command:

`php artisan content:publish-scheduled --limit=100`

Repeat only while a known backlog remains and database load is acceptable. The state recheck makes normal catch-up safe for already-progressed rows.

For media, restore provider/scanner health first. Reconcile a missing object using approved backup/provider recovery; do not create a placeholder object or change scanner/lifecycle state to manufacture success.

## 7. Capacity and dependency degradation

The default publish batch is 100. Increase catch-up only within implemented command bounds and after checking database lock/load pressure. Media upload size remains bounded by `CONTENT_MEDIA_MAX_KB`.

S3/scanner degradation should be isolated as far as supported, but unsafe media must remain unavailable. Production provider capacity, egress and scanner SLA are external operational evidence.

## 8. Backup, migration and rollback

A usable Content recovery requires database state and private media bytes to correspond. Follow the shared [backup/restore runbook](../../../operations/runbooks/backup-restore.md); production recovery must include provider-native media recovery plus application key/configuration where applicable.

Application rollback uses the shared immutable-image process. Do not reverse Content schema after real data without explicit compatibility/data-loss review.

## 9. Stop conditions and prohibited operator actions

Stop and escalate if recovery would require direct publication-state edits, cross-tenant media reassignment, marking rejected/unscanned media clean, exposing private storage publicly, restoring only one half of an inconsistent database/media recovery set, or deleting audit evidence.

## 10. Validation and evidence to retain

After recovery verify representative due content advanced exactly once, already-published content did not duplicate, media remains private and eligible assets are retrievable, branding references are valid, and shared readiness is green.

Retain release SHA, command timestamp/limit/result, affected IDs, before/after statuses, request/trace IDs, scanner/provider error identifiers, backup/object recovery identifiers and validation outcome.
