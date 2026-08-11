# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/snapshot/import, transfer, Alliance-intelligence/diplomacy, and K4 ingestion-control state with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 are predominantly synchronous Laravel/PostgreSQL workflows with shared audit/outbox. K4-P1 adds a synchronous manager ingestion control plane plus internal batch/candidate domain actions. It adds no source poller, scheduler, queue worker/partition, crawler, scraper, OCR service, bot, replay worker, or real external game-data provider.

## 2. Persistent state and ownership

Kingdoms owns global neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/import, transfer, game-Alliance intelligence/diplomacy, and K4 `kingdom_ingestion_subscriptions`, `kingdom_ingestion_batches`, `kingdom_ingestion_candidates`.

Global references never imply cross-Alliance access. K4 operational rows capture Alliance/Kingdom context and are not canonical promoted history.

## 3. Configuration and runtime dependencies

Primary dependencies are PostgreSQL plus active tenant/auth/audit/outbox infrastructure. K4 adds `config/kingdoms.php` adapter registration. Production `ingestion_adapters` is intentionally empty; no source endpoint/credential is accepted.

Shared Redis/queue availability matters to downstream outbox consumers, not to current K4 acquisition because no K4 background acquisition exists.

## 4. Normal flow and background processing

Managers with `kingdoms.manage` may view K4 status, configure an already registered adapter, transition subscription state, and reject quarantined candidates; mutations require recent password confirmation.

Internal batch/candidate actions support later workers, but **background processing is not implemented for K4 yet**. No external data is fetched and no candidate is automatically promoted in Slice A.

## 5. Health, observability and diagnostics

Use request/trace/audit/outbox correlation plus Alliance/current/captured Kingdom, subscription adapter/version/state/health, batch state/counts/timing/failure code, candidate target/state/stable/source IDs/timing/reason. Do not log normalized payload bodies, raw source responses, credentials, headers/cookies, or private manager text.

## 6. Failure modes and diagnosis

Existing failures include tenant drift, CSV ambiguity, stale/missing observations, transfer/diplomacy invalid state. K4 adds: no approved adapter, inactive subscription, adapter-version mismatch, Kingdom drift, duplicate source window, unsupported target/payload, missing stable identity quarantine, invalid batch transition, cross-tenant ID tampering.

The expected production Slice A state is **zero approved source adapters**. That is not an outage.

## 7. Recovery, replay and reconciliation

Use supported domain actions rather than editing historical/operational rows. K4 exact source-window/candidate retries should return existing identity. Disable stale subscriptions rather than rewriting captured Kingdom context. Do not guess stable IDs or manually promote candidates.

K4 replay/promotion workers remain later slices; do not improvise cron/curl/scraper/bot execution around internal actions.

## 8. Backup, restore, migration and rollback

K4 migration `2026_08_11_190000_create_kingdom_ingestion_foundation.php` creates subscription→batch→candidate dependencies and rolls them back candidate→batch→subscription. The Kingdom migration round-trip test tears K4 down before older Kingdoms tables and reapplies it after them.

Shared backup/restore/immutable-image rollback applies. After restore, validate representative K1–K3 history plus K4 ownership/state/hash context and no unintended promoted observation.

## 9. Capacity, query and performance boundaries

K1–K3 retain accepted query gates. K4-P1 has no external throughput/capacity promise and no scheduler. Manager projections are bounded recent operational state; future source frequency, batch size, queue throughput, retention/storage and backpressure require P4/P5 performance evidence before real production enablement.

## 10. External-service degradation

K4 currently has no accepted external game-data dependency. Do not add scraping/OCR/bots/unapproved provider calls as an operational workaround.

A later approved adapter must define timeout/rate/cursor/retry/schema-change/revocation behavior and safe secret/network boundaries before its degradation behavior becomes an operations contract.

## 11. Safe operator actions and stop conditions

Safe: inspect persisted lifecycle/provenance; pause/disable supported subscription; use documented K1–K3 recovery; restore database/runtime health; verify production adapter config is intentionally empty.

Stop if recovery would require unapproved source/network access, source secrets/raw-response storage, cross-tenant access, stable-ID guessing, manual observation promotion, auto roster/tracking/transfer/diplomacy, scoring/recommendation, or ad-hoc K4 background processing.

## 12. Evidence, focused runbooks and related documentation

Focused Kingdoms guides:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

K4-P1 runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed DR `31533284318`, CodeQL `31533284195`, CI `31533284398`, including image/staging/backup/scan.

Use with [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), [rollback](../../../operations/runbooks/rollback.md), and [Kingdoms security](../security/README.md).
