# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion-control isolation/idempotency, realistic-volume query bounds, and explicit public/automation non-capabilities

## 1. Critical claims and validation ownership

Kingdoms validation proves identity/membership/game-reference separation; Alliance ownership of all tenant workflows; stable-ID-only automatic identity; append history; explicit human transfer/diplomacy behavior; disclosure boundaries; and current K4 source/secret/quarantine/no-promotion limits.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation, Unit. K4-P1 feature/architecture coverage is additive to accepted K1–K3 evidence.

Architecture protects ownership/non-capabilities/accessibility; Feature protects manager/member workflows and K4 controls; Integration protects persistence/outbox/cross-domain contracts; Performance protects accepted realistic-volume query gates; TenantIsolation protects Alliance state around shared neutral references; Unit protects deterministic parsing/projection/value/state behavior.

## 3. Architecture and domain-boundary validation

Architecture guards protect neutral references vs tenant state, no name/tag/handle auto-merge, no public Kingdoms API/wildcard webhook exposure, and no scoring/automatic diplomacy/transfer behavior.

K4 updates the historical no-ingestion guard narrowly: K3 `/alliance/kingdom-alliances` routes still contain no ingestion sub-surface, while separately governed `/alliance/kingdom-ingestion` manager routes are permitted. Production adapter configuration remains empty and Slice A introduces no public machine/source route.

## 4. Authorization, tenancy, security and privacy validation

Tests cover `alliance.view`, `kingdoms.manage`, recent password confirmation, submitted-ID re-resolution, shared-neutral-reference privacy, manager-private fields, Alliance-Kingdom drift, and K4 cross-tenant subscription/candidate tampering.

K4 tests additionally prove no URL/endpoint/header/credential/secret/token/cookie/raw-payload schema columns, manager-only safe status presentation, target-specific payload bounds, stable-ID quarantine, and no business observation promotion.

## 5. Feature, interface and integration validation

Feature coverage spans all K1–K3 workspaces plus K4 manager adapter/subscription/batch/candidate status/control behavior. The K4 test fixture registers an adapter only inside tests; production config stays empty.

Integration/Audit/outbox evidence ensures K4 human mutations and internal batch/candidate events remain tenant-correlated/internal without introducing public API/webhook eligibility.

## 6. Idempotency, concurrency and asynchronous validation

Accepted K1/K3 exact observation retries and transfer/import idempotency remain protected. K4 source-window uniqueness and deterministic candidate identity prove exact retry safety; completed batch outcomes are immutable.

K4 currently has no autonomous scheduler/ingestion worker. Later async processing must be validated separately for queue isolation, concurrency, backoff/cursor/replay and must not replay the originating business mutation incorrectly.

## 7. Persistence, migration, rollback and recovery evidence

The full Kingdom migration round-trip now includes `2026_08_11_190000_create_kingdom_ingestion_foundation.php` at the newest dependency boundary: K4 is rolled down before K3/K2/K1 tables and reapplied after them. Restrictive Kingdom FKs remain intact.

CI clean PostgreSQL migration and shared backup/restore remain required. K4 restore verification must distinguish operational candidate state from canonical promoted history.

## 8. Performance, query and capacity evidence

Accepted query gates remain:

- K1: 150 tracked players / 450 snapshots with bounded intelligence query shape;
- K2: 150 transfer participants / 20 groups with bounded planning projections;
- K3: 120 tracked game Alliances / 600 observations / 120 diplomacy relationships / 60 contacts with manager intelligence at ≤10 SELECTs.

K4-P1 adds no production source throughput/capacity benchmark. P4/P5 must add realistic batch/candidate/scheduler/storage evidence before source enablement.

## 9. Accessibility and frontend evidence

The Kingdom accessibility architecture suite now includes `KingdomIngestionManage.vue`, requiring main landmark/primary heading/native controls/labels/table overflow semantics. `npm run check` on the validated candidate passed ESLint, Prettier, Vue/TypeScript and production build.

## 10. Historical and current evidence

Whole accepted increments:

- K1 implementation `7f743507b70865692290f517cd2de494ec54abae` — DR `31288932532`, CodeQL `31288932537`, CI `31288932560`.
- K2 implementation `64189559c66e15dc56ec31f9b340284c89c30e6c` — DR `31337595942`, CodeQL `31337595933`, CI `31337595937`.
- K3 implementation `068c4086744f71d33453734f1f1b05fe1430cbff` — DR `31430279647`, CodeQL `31430279652`, CI `31430279638`.

K4-P0 candidate `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c` and final evidence head `ff41a7519acad7d7365669188f7e717462639367` are protected-green. K4-P1 runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed DR `31533284318`, CodeQL `31533284195`, CI `31533284398`: Pint 509; PHPStan 363/363 zero errors; 407 tests / 9,466 assertions; image/staging/backup/scan success.

## 11. Evidence identity, retention and supersession

Historical accepted SHAs/run IDs remain immutable evidence. Current behavior follows current code/tests/living contracts. Each K4 slice records the exact runtime candidate and validates the containing evidence/status head before continuation.

Temporary diagnostic PR #55 was closed without merge; standard composer/npm checks are restored and no diagnostic commands/sentinel files remain in the K4 runtime candidate.

## 12. Gaps, non-capabilities and related documentation

Current K4 validation does not prove a real source/network, source credentials, scheduler/worker/cursor/retry loop, candidate promotion, production rate/capacity/retention, or production cutover. Those are explicit later gates, not silently missing test coverage.

Related: [Automated ingestion](../automated-ingestion.md), [Slice A validation](../product/kingdoms-automated-ingestion-slice-a-validation.md), [Security review](../security/kingdoms-automated-ingestion-foundation-security-review.md), [Operations](../operations/kingdoms-automated-ingestion.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md).
