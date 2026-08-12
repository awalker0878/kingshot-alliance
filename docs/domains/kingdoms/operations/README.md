# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current — `KINGDOMS-004` Accepted  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/snapshot/import, transfer, Alliance-intelligence/diplomacy, and K4 ingestion/promotion/scheduler/maintenance state with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain predominantly synchronous Laravel/PostgreSQL workflows with shared audit/outbox. Accepted K4 adds manager ingestion control, delegated factual promotion, generic background acquisition/scheduling, source-revocation reconciliation, bounded operational retention and payload-free health monitoring on shared Laravel Scheduler/Redis/Horizon infrastructure.

No concrete production source is configured; the production adapter allowlist remains empty.

## 2. Persistent state and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/import, transfer, game-Alliance intelligence/diplomacy, K4 subscriptions/batches/candidates, scheduler/cursor/failure state, and promoted canonical observation history.

K4 operational rows are not canonical history. Promoted K1/K3 records copy bounded source provenance independently of operational-row retention.

## 3. Configuration and runtime dependencies

Primary dependencies are PostgreSQL, active tenant/auth/audit/outbox infrastructure, Laravel Scheduler, Redis queues/Horizon and repository/config adapter registration.

K4 has a dedicated `kingdoms-ingestion` queue. Production `ingestion_adapters` remains intentionally empty; no source endpoint/credential is accepted. Repository-controlled retention and health thresholds are configuration, not tenant-supplied source settings.

## 4. Normal flow and background processing

Managers with `kingdoms.manage` may view status, configure an already registered adapter, transition subscription state, reject quarantined candidates, and replay quarantined candidates; privileged human mutations require recent password confirmation.

`kingdoms:queue-ingestion --limit=100` runs every minute with `onOneServer()` and `withoutOverlapping(10)`, claims due subscriptions under row locks and dispatches unique/overlap-protected per-subscription jobs to the isolated queue. Workers call accepted staging plus P2/P3 promotion actions; cursor advances only after Completed/Partial batches.

`kingdoms:reconcile-ingestion-sources --limit=1000` runs every five minutes and disables active/paused subscriptions whose adapter key/version is no longer approved. `kingdoms:enforce-ingestion-retention` runs daily at 04:15 and redacts/prunes age-qualified operational state.

## 5. Health, observability and diagnostics

`kingdoms:ingestion-health --json` returns aggregate counts for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches plus `attentionRequired`; it exits non-zero when attention is required.

Use request/trace/audit/outbox correlation plus Alliance/current/captured Kingdom, adapter/version, opaque cursor/window, subscription/batch/candidate lifecycle, next-run/claim/success/failure/circuit timing, stable/source IDs, hashes, promoted-record IDs, and bounded failure/quarantine codes.

Do not log normalized payload bodies, raw source responses, credentials, headers/cookies, raw exception text or private manager/diplomacy/contact text.

## 6. Failure modes and diagnosis

Failure classes include no approved adapter, inactive subscription, adapter-version mismatch/revocation, Kingdom/context drift, open circuit, source-window/cursor conflict, unsupported/bounded values, missing/unknown/ambiguous stable identity, missing/inactive tenant relationship, business-record validation failure, retry exhaustion, invalid batch transition and cross-tenant ID tampering.

Expected production state remains **zero approved source adapters**; that is not an outage.

## 7. Recovery, replay and reconciliation

Use supported domain actions rather than editing rows. Exact completed-window/promoted-candidate retry should resolve existing state. Pause/disable stale subscriptions; never rewrite captured context or cursor by hand.

After restore, inspect aggregate ingestion health and reconcile current source approval before acquisition resumes. Password-confirmed manager replay remains limited to quarantined candidates and re-runs accepted promotion rules.

Do not guess stable IDs, auto-create/reactivate roster/tracking, use machine correction/invalidation as recovery, or directly re-enable a source whose registry approval disappeared.

## 8. Backup, restore, migration and rollback

The K4 scheduling migration extends the accepted Kingdoms dependency chain after foundation/provenance migrations. Focused and whole-domain migration tests exercise down/up order; full CI applies all migrations on PostgreSQL.

Shared backup/restore/immutable-image rollback applies. After restore, validate representative K1–K3 history, K4 ownership/cursor/scheduler/failure state, source approval, promoted-record correlation and independence of canonical history from operational K4 retention.

Default K4 operational retention windows are 30-day terminal payload redaction, 90-day terminal promoted/rejected candidate and candidate-free terminal batch retention, 180-day quarantined-candidate retention, and 30-day disabled-subscription scheduling/failure compaction.

## 9. Capacity, query and performance boundaries

K1–K3 retain accepted query gates. K4 bounds one acquisition page to 250 records, adapter polling to 60–86,400 seconds, job timeout to 120 seconds and dedicated production/staging queue concurrency to defaults of 2/1.

The accepted operations gate uses 250 subscriptions, 40 failed batches and 110 candidates and requires the aggregate health snapshot to remain at no more than eight SELECT queries. These are repository safety/capacity limits, not a real-source throughput SLO.

## 10. External-service degradation

K4 generic failure/backoff/circuit/revocation mechanics are accepted, but there is still no accepted external game-data dependency. Do not add scraping/OCR/bots/unapproved provider calls as an operational workaround.

A later approved adapter must define authorization/terms, endpoint/network/TLS/egress, timeout/rate/cursor/schema/revocation behavior and safe secret boundaries.

## 11. Safe operator actions and stop conditions

Safe: inspect persisted lifecycle/cursor/provenance and aggregate health, pause/disable supported subscriptions, reconcile source approval, run repository-controlled retention, replay a legitimate quarantined candidate through the manager action, restore database/Redis/runtime health, and verify production adapter config remains intentionally empty.

Stop if recovery would require unapproved source/network access, source secrets/raw-response storage, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact automation, scoring/recommendation or manual row/cursor/candidate rewrites.

## 12. Evidence, focused runbooks and related documentation

**P3 inventory decision:** Kingdoms retains domain-owned focused operational guides for roster intelligence, transfer planning, Alliance intelligence, and automated ingestion; shared queue/deployment/backup mechanics remain top-level Operations-owned.

Focused Kingdoms guides:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

K4 whole-increment candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`: frontend/build, 529 Pint files, PHPStan 374/374 zero errors, 429 tests / 9,799 assertions, clean migrations, immutable image, staging, backup/restore and scan.

Use with [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), [rollback](../../../operations/runbooks/rollback.md), [K4 exit report](../product/kingdoms-automated-ingestion-exit-report.md), [K4 Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md), and [Kingdoms security](../security/README.md).
