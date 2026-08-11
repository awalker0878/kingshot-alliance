# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/snapshot/import, transfer, Alliance-intelligence/diplomacy, and K4 ingestion/promotion/scheduler state with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain predominantly synchronous Laravel/PostgreSQL workflows with shared audit/outbox. K4 through P4 adds manager ingestion control, delegated factual promotion, and generic background acquisition/scheduling on shared Laravel Scheduler/Redis/Horizon infrastructure.

No concrete production source is configured; the production adapter allowlist remains empty.

## 2. Persistent state and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/import, transfer, game-Alliance intelligence/diplomacy, K4 subscriptions/batches/candidates, scheduler/cursor/failure state, and promoted canonical observation history.

K4 operational rows are not canonical history. Promoted K1/K3 records copy bounded source provenance independently of operational-row retention.

## 3. Configuration and runtime dependencies

Primary dependencies are PostgreSQL, active tenant/auth/audit/outbox infrastructure, Laravel Scheduler, Redis queues/Horizon and repository/config adapter registration.

K4 has a dedicated `kingdoms-ingestion` queue. Production `ingestion_adapters` remains intentionally empty; no source endpoint/credential is accepted. A real adapter would add separately approved network/secret dependencies.

## 4. Normal flow and background processing

Managers with `kingdoms.manage` may view status, configure an already registered adapter, transition subscription state, reject quarantined candidates, and replay quarantined candidates; privileged human mutations require recent password confirmation.

`kingdoms:queue-ingestion --limit=100` runs every minute with `onOneServer()` and `withoutOverlapping(10)`, claims due subscriptions under row locks and dispatches unique/overlap-protected per-subscription jobs to the isolated queue. Workers call accepted staging plus P2/P3 promotion actions; cursor advances only after Completed/Partial batches.

## 5. Health, observability and diagnostics

Use request/trace/audit/outbox correlation plus Alliance/current/captured Kingdom, adapter/version, opaque cursor/window, subscription/batch/candidate lifecycle, next-run/claim/success/failure/circuit timing, stable/source IDs, hashes, promoted-record IDs, and bounded failure/quarantine codes.

Do not log normalized payload bodies, raw source responses, credentials, headers/cookies, raw exception text or private manager/diplomacy/contact text.

## 6. Failure modes and diagnosis

Failure classes include no approved adapter, inactive subscription, adapter-version mismatch, Kingdom/context drift, open circuit, source-window/cursor conflict, unsupported/bounded values, missing/unknown/ambiguous stable identity, missing/inactive tenant relationship, business-record validation failure, retry exhaustion, invalid batch transition and cross-tenant ID tampering.

Expected production state remains **zero approved source adapters**; that is not an outage.

## 7. Recovery, replay and reconciliation

Use supported domain actions rather than editing rows. Exact completed-window/promoted-candidate retry should resolve existing state. Pause/disable stale subscriptions; never rewrite captured context or cursor by hand.

Password-confirmed manager replay is limited to quarantined candidates and re-runs the accepted promotion rules. Do not guess stable IDs, auto-create/reactivate roster/tracking, or use machine correction/invalidation as recovery.

## 8. Backup, restore, migration and rollback

The K4 scheduling migration extends the accepted Kingdoms dependency chain after foundation/provenance migrations. Focused and whole-domain migration tests exercise down/up order; full CI applies all migrations on PostgreSQL.

Shared backup/restore/immutable-image rollback applies. After restore, validate representative K1–K3 history, K4 ownership/cursor/scheduler/failure state, promoted-record correlation and independence of canonical history from operational K4 retention.

## 9. Capacity, query and performance boundaries

K1–K3 retain accepted query gates. P4 bounds one acquisition page to 250 records, adapter polling to 60–86,400 seconds, job timeout to 120 seconds and dedicated production/staging queue concurrency to defaults of 2/1.

These are safety limits, not a real-source throughput SLO. P5 must add capacity/retention/alert evidence before source enablement.

## 10. External-service degradation

K4 generic failure/backoff/circuit mechanics now exist, but there is still no accepted external game-data dependency. Do not add scraping/OCR/bots/unapproved provider calls as an operational workaround.

A later approved adapter must define authorization/terms, timeout/rate/cursor/schema/revocation behavior and safe network/secret boundaries.

## 11. Safe operator actions and stop conditions

Safe: inspect persisted lifecycle/cursor/provenance, pause/disable supported subscriptions, replay a legitimate quarantined candidate through the manager action, restore database/Redis/runtime health, and verify production adapter config remains intentionally empty.

Stop if recovery would require unapproved source/network access, source secrets/raw-response storage, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact automation, scoring/recommendation or manual row/cursor rewrites.

## 12. Evidence, focused runbooks and related documentation

**P3 inventory decision:** Kingdoms retains domain-owned focused operational guides for roster intelligence, transfer planning, Alliance intelligence, and automated ingestion; shared queue/deployment/backup mechanics remain top-level Operations-owned.

Focused Kingdoms guides:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

K4-P4 runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed DR `31545866277`, CodeQL `31545866288`, CI `31545866249`, including frontend, 523 Pint files, PHPStan 371/371 zero errors, 423 tests / 9,697 assertions, image/staging/backup/scan.

Use with [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), [rollback](../../../operations/runbooks/rollback.md), and [Kingdoms security](../security/README.md).
