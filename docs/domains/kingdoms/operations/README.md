# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/snapshot/import, transfer, Alliance-intelligence/diplomacy, and K4 ingestion/promotion state with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 are predominantly synchronous Laravel/PostgreSQL workflows with shared audit/outbox. K4 through P3 adds synchronous manager ingestion control plus internal batch/candidate and delegated promotion actions. It still adds no real source, scheduler, acquisition worker, crawler/scraper/OCR/bot, or replay loop.

## 2. Persistent state and ownership

Kingdoms owns neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/import, transfer, game-Alliance intelligence/diplomacy, K4 subscriptions/batches/candidates, and promoted canonical observation history.

K4 operational rows capture Alliance/Kingdom context and are not canonical history. Promoted K1/K3 records copy bounded source provenance independently of operational-row retention.

## 3. Configuration and runtime dependencies

Primary dependencies are PostgreSQL plus active tenant/auth/audit/outbox infrastructure. K4 adds repository/config adapter registration; production `ingestion_adapters` remains intentionally empty. No source endpoint/credential is accepted.

Shared Redis/queue availability matters to downstream outbox consumers, not K4 acquisition because no K4 acquisition scheduler/worker exists yet.

## 4. Normal flow and background processing

Managers with `kingdoms.manage` may view status, configure an already registered adapter, transition subscription state, and reject quarantined candidates; human mutations require recent password confirmation.

Internal K4 services can manage batch/candidate lifecycle and promote factual player snapshots or game-Alliance observations only to existing tenant relationships. **No autonomous background processing is implemented through P3.**

## 5. Health, observability and diagnostics

Use request/trace/audit/outbox correlation plus Alliance/current/captured Kingdom, adapter/version, subscription/batch/candidate lifecycle, stable/source IDs, hashes, promoted-record IDs, and bounded failure/quarantine codes. Do not log normalized payload bodies, raw source responses, credentials, headers/cookies, or private manager/diplomacy/contact text.

## 6. Failure modes and diagnosis

K4 failure classes now include no approved adapter, inactive subscription, adapter-version mismatch, Kingdom/context drift, duplicate source window, unsupported/bounded values, missing/unknown/ambiguous stable identity, missing/inactive/ambiguous tenant relationship, business-record validation failure, invalid batch transition, and cross-tenant ID tampering.

Expected production state remains **zero approved source adapters**; that is not an outage.

## 7. Recovery, replay and reconciliation

Use supported domain actions rather than editing rows. Exact promoted-candidate retry should resolve existing canonical history. Disable stale subscriptions rather than rewriting captured context. Never guess stable IDs, auto-create/reactivate roster/tracking, or use machine correction/invalidation as recovery.

Scheduler/cursor/retry/replay implementation remains P4; do not improvise cron/curl/scraper/bot execution around internal actions.

## 8. Backup, restore, migration and rollback

K4 foundation, player-provenance, and game-Alliance-provenance migrations extend the accepted Kingdoms migration dependency chain. Focused migration tests exercise the new provenance migrations down/up; full CI applies all migrations on PostgreSQL.

Shared backup/restore/immutable-image rollback applies. After restore, validate representative K1–K3 history, K4 ownership/state/hash context, promoted-record correlation, and independence of canonical promoted history from operational K4 retention.

## 9. Capacity, query and performance boundaries

K1–K3 retain accepted query gates. K4 through P3 has no external throughput/capacity promise and no scheduler. Future source frequency, batch size, queue throughput, retention/storage, backpressure and replay capacity require P4/P5 evidence before real production enablement.

## 10. External-service degradation

K4 currently has no accepted external game-data dependency. Do not add scraping/OCR/bots/unapproved provider calls as an operational workaround.

A later approved adapter must define timeout/rate/cursor/retry/schema-change/revocation behavior and safe secret/network boundaries before degradation behavior becomes an operations contract.

## 11. Safe operator actions and stop conditions

Safe: inspect persisted lifecycle/provenance, pause/disable supported subscription, use documented K1–K3 recovery, restore database/runtime health, and verify production adapter config remains intentionally empty.

Stop if recovery would require unapproved source/network access, source secrets/raw-response storage, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact automation, scoring/recommendation, or ad-hoc K4 background processing.

## 12. Evidence, focused runbooks and related documentation

Focused Kingdoms guides:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, CI `31541291501`, including image/staging/backup/scan.

Use with [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), [rollback](../../../operations/runbooks/rollback.md), and [Kingdoms security](../security/README.md).
