# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion-control/promotion/scheduler isolation and idempotency, realistic-volume query bounds, and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable accepted candidate identities; this profile does not create one Markdown record per test.

## 1. Critical claims and validation ownership

Kingdoms validation proves identity/membership/game-reference separation; Alliance ownership; stable-ID-only automatic identity; append history; explicit human transfer/diplomacy behavior; disclosure boundaries; and K4 source/secret/quarantine/promotion/scheduler/replay limits.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K4-P1–P4 feature/architecture coverage is additive to accepted K1–K3 evidence.

Architecture protects ownership/non-capabilities/accessibility; Feature protects manager/member/control/promotion/scheduler workflows; Integration protects persistence/outbox/cross-domain contracts; Performance protects accepted realistic-volume query gates; TenantIsolation protects Alliance state around shared neutral references; Unit protects deterministic parsing/projection/value/state behavior.

## 3. Architecture and domain-boundary validation

Architecture guards protect neutral references vs tenant state, no name/tag/handle auto-merge, no public Kingdoms API/wildcard webhook exposure, and no scoring/automatic diplomacy/transfer behavior.

K4 permits separately governed `/alliance/kingdom-ingestion` manager routes and generic scheduled work while preserving no ingestion under the K3 `/alliance/kingdom-alliances` surface. Production adapter configuration remains empty and there is no public machine/source route.

## 4. Authorization, tenancy, security and privacy validation

Tests cover `alliance.view`, `kingdoms.manage`, recent password confirmation, submitted-ID re-resolution, shared-neutral-reference privacy, Alliance-Kingdom drift, cross-tenant subscription/candidate tampering, source-version revocation and existing roster/tracking relationship requirements.

K4 additionally proves no manager URL/header/credential configuration, bounded normalized candidate/source scheduling state, stable-ID quarantine, null machine actor, no direct canonical-write shortcut, and no raw exception text persisted as scheduler diagnostics.

## 5. Feature, interface and integration validation

Feature coverage spans all K1–K3 workspaces plus K4 manager adapter/subscription/batch/candidate status/control, player/game-Alliance promotion and replay. Scheduler tests use a test-only acquisition adapter; production config remains empty.

Integration/Audit/outbox evidence ensures K4 human mutations and internal batch/candidate/promotion/replay events remain tenant-correlated/internal without public API/webhook eligibility.

## 6. Idempotency, concurrency and asynchronous validation

Accepted K1/K3 exact observation retries and transfer/import idempotency remain protected. K4 source-window uniqueness, deterministic candidate identity and promoted-history identity protect exact retry.

P4 proves transactional due claims, queue uniqueness/overlap controls, cursor advancement only after successful/partial batches, completed-window replay with matching next cursor, bounded retries/backoff/circuit behavior and retry-exhaustion finalization. Queue/cache controls are additive; database/domain idempotency remains authoritative.

## 7. Persistence, migration, rollback and recovery evidence

The full Kingdom migration round-trip includes the K4 foundation, player/game-Alliance provenance, and P4 scheduling migration at the newest dependency boundary. P4 is rolled down before earlier K4/K3/K2/K1 tables and reapplied after them; restrictive Kingdom FKs remain intact.

CI clean PostgreSQL migration and shared backup/restore remain required. Restore verification distinguishes operational cursor/candidate state from canonical promoted history.

## 8. Performance, query and capacity evidence

Accepted query gates remain:

- K1: 150 tracked players / 450 snapshots with bounded intelligence query shape;
- K2: 150 transfer participants / 20 groups with bounded planning projections;
- K3: 120 tracked game Alliances / 600 observations / 120 diplomacy relationships / 60 contacts with manager intelligence at ≤10 SELECTs.

P4 adds safety bounds—250 records/page, poll interval 60–86,400 seconds, 120-second job timeout, bounded retry/circuit state and low dedicated queue concurrency—but no real-source throughput SLO. P5 owns capacity/retention/alert evidence before source enablement.

## 9. Accessibility and frontend evidence

The Kingdom accessibility architecture suite includes `KingdomIngestionManage.vue`, requiring main landmark/primary heading/native controls/labels/table overflow semantics. The final P4 candidate passed ESLint, Prettier, Vue/TypeScript and production frontend build.

## 10. Historical and current evidence

Accepted whole increments remain K1/K2/K3 with immutable evidence in their exit records. K4 completed-gate evidence includes P0 through P3 in the product index.

K4-P4 final runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed DR `31545866277`, CodeQL `31545866288`, CI `31545866249`: Pint 523 files; PHPStan 371/371 zero errors; 423 tests / 9,697 assertions; frontend/build, migrations, immutable image, staging, backup/restore and scan success.

Initial P4 implementation `becf10656aecf4071976813eabb3cc535439a9f3` had only a Prettier frontend failure; backend/runtime tests were green. Diagnostic PR #56 was closed unmerged after producing the exact formatter mutation.

## 11. Evidence identity, retention and supersession

Historical accepted SHAs/run IDs remain immutable evidence. Current behavior follows current code/tests/living contracts. Each K4 slice records the exact runtime candidate and validates the containing evidence/status head before continuation.

Operational K4 retention/pruning is intentionally deferred to P5; canonical promoted history/provenance is independent of operational-row retention.

## 12. Gaps, non-capabilities and related documentation

Current K4 validation does not prove a real production source/network/credential path, provider terms/authorization, real-source rate/schema behavior, production capacity/alerts, final operational retention/pruning, or production cutover. Those are explicit later/separate gates.

Related: [Automated ingestion](../automated-ingestion.md), [Slice D validation](../product/kingdoms-automated-ingestion-slice-d-validation.md), [Slice D security review](../security/kingdoms-automated-ingestion-scheduler-security-review.md), [Operations](../operations/kingdoms-automated-ingestion.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md).
