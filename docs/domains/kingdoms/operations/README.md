# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/snapshot/import intelligence, transfer planning, and Alliance-intelligence/diplomacy state with accepted domain-specific recovery/query evidence

## 1. Operational purpose and runtime shape

Kingdoms is synchronous Laravel/PostgreSQL functionality for accepted K1–K3 behavior. It adds no dedicated scheduler, queue worker, crawler, OCR service, bot or external game-data poller. Supported mutations record audit/outbox evidence through the shared platform contracts.

## 2. Persistent state and ownership

Kingdoms owns global neutral Kingdom/player/game-Alliance reference state plus Alliance-owned roster entries, snapshots, imports, transfer plans/participants/groups/readiness/completion, tracked game alliances, observations, diplomacy and manager-private contacts. Global references never imply cross-Alliance sharing of tenant-owned state.

## 3. Configuration and runtime dependencies

Primary dependency is PostgreSQL plus the shared request/tenant/audit/outbox runtime. Kingdoms introduces no accepted game-data provider, scraper or ingestion configuration. Shared Redis/queue availability matters only to downstream outbox consumers, not to the synchronous source transaction itself.

## 4. Normal flow and background processing

Roster/import, snapshot, transfer and Alliance-intelligence workflows execute synchronously under tenant/authorization rules. Descriptive intelligence is calculated from persisted observations/history. The shared outbox publisher processes committed internal events; current Kingdoms events remain internal-only and do not create a public API/webhook contract.

## 5. Health, observability and diagnostics

Use request/trace IDs, audit/outbox IDs, Alliance/Kingdom/reference IDs, roster/snapshot freshness/counts, import checksum/status counts, transfer lifecycle/readiness/blocker state, observation/diplomacy timestamps and the accepted query-budget signals. The three focused guides below contain capability-specific diagnostics.

## 6. Failure modes and diagnosis

Typical failures are tenant-context drift, stale/ambiguous CSV preview, rejected import rows, stale/missing snapshot history, inconsistent transfer lifecycle/readiness, inactive/current-Kingdom drift, invalid diplomacy/contact mutation, or PostgreSQL failure. Missing observations are not zero values and stale data must remain visible rather than silently inferred.

## 7. Recovery, replay and reconciliation

Use supported fresh preview/observation/lifecycle actions rather than editing historical rows. Exact accepted-observation retries and accepted import identities are idempotent according to the capability contracts. If tenant/current-Kingdom context changes, preserve history and re-enter through supported active-context flows instead of force-linking stale state.

## 8. Backup, restore, migration and rollback

Kingdoms durable state is PostgreSQL-backed and follows the shared backup/restore and immutable-image rollback procedures. K1–K3 migrations have dependency-ordered round-trip evidence for development/test. After restore, verify representative Kingdom association, roster/snapshot/import provenance, transfer state and Alliance-intelligence/diplomacy data before acceptance.

## 9. Capacity, query and performance boundaries

Accepted performance gates are query-count/N+1 regression evidence, not production capacity benchmarks. Roster intelligence, transfer and Alliance-intelligence views use bounded/batched query patterns at their accepted fixture volumes. Production sizing still requires accountable load/capacity evidence.

## 10. External-service degradation

Kingdoms has no accepted external game-data dependency. Do not introduce scraping, OCR, bots or unofficial provider calls as an operational recovery shortcut. Downstream shared outbox/notification failures do not authorize replaying the originating Kingdoms mutation.

## 11. Safe operator actions and stop conditions

Safe actions are inspect persisted lifecycle/provenance, create a fresh import preview, append a later supported observation/correction, restore database/runtime health and follow the accepted domain guides. Stop if recovery would require mutating append-oriented history, auto-merging by names/tags, inferring diplomacy, exposing manager-private contacts, crossing tenants or enabling unapproved ingestion/automation.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, request/trace/audit/outbox IDs, affected Alliance/Kingdom/reference IDs, relevant freshness/checksum/lifecycle counts and recovery validation results.

Accepted focused operations evidence retained by P3:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)

Use these with [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), [rollback](../../../operations/runbooks/rollback.md), and the [Kingdoms security profile](../security/README.md).
