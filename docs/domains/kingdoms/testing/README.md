# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current — `KINGDOMS-004` Accepted  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 ingestion-control/promotion/scheduler/operations isolation and idempotency, realistic-volume query bounds, and explicit public/decision-automation non-capabilities

**P5 evidence decision:** Current proof is mapped by executable suite/evidence class and immutable candidate identities; accepted P6 adds one whole-increment seam test without replacing the focused P1–P5 evidence.

## 1. Critical claims and validation ownership

Kingdoms validation proves identity/membership/game-reference separation; Alliance ownership; stable-ID-only automatic identity; append history; explicit human transfer/diplomacy behavior; disclosure boundaries; and K4 source/secret/quarantine/promotion/scheduler/replay/retention/revocation limits.

Whole-increment acceptance additionally proves those controls compose correctly across acquisition, both supported promotion paths, exact-window retry, source revocation and operational pruning.

## 2. Executable suite mapping

All six PHPUnit groups remain material: Architecture, Feature, Integration, Performance, TenantIsolation and Unit. K4-P1–P6 coverage is additive to accepted K1–K3 evidence.

Architecture protects ownership/non-capabilities/accessibility; Feature protects manager/member/control/promotion/scheduler/operations/acceptance workflows; Integration protects persistence/outbox/cross-domain contracts; Performance protects accepted realistic-volume query gates; TenantIsolation protects Alliance state around shared neutral references; Unit protects deterministic parsing/projection/value/state behavior.

## 3. Architecture and domain-boundary validation

Architecture guards protect neutral references vs tenant state, no name/tag/handle auto-merge, no public Kingdoms API/wildcard webhook exposure, and no scoring/automatic diplomacy/transfer behavior.

K4 permits separately governed `/alliance/kingdom-ingestion` manager routes and generic scheduled/maintenance work while preserving no public machine/source route. Production adapter configuration remains empty.

## 4. Authorization, tenancy, security and privacy validation

Tests cover `alliance.view`, `kingdoms.manage`, recent password confirmation, submitted-ID re-resolution, shared-neutral-reference privacy, Alliance-Kingdom drift, cross-tenant subscription/candidate tampering, source-version revocation and existing roster/tracking relationship requirements.

K4 additionally proves no manager URL/header/credential configuration, bounded normalized candidate/source scheduling state, stable-ID quarantine, null machine actor, no direct canonical-write shortcut, no raw exception text persisted as diagnostics, and operational retention that does not delete canonical promoted history.

The P6 acceptance test starts from an empty production adapter allowlist and uses only a test-local adapter so whole-increment evidence does not imply real-source approval.

## 5. Feature, interface and integration validation

Feature coverage spans all K1–K3 workspaces plus K4 manager adapter/subscription/batch/candidate status/control, player/game-Alliance promotion, replay, retention, source reconciliation, health behavior and whole-increment acceptance. Scheduler/acquisition tests use test-only adapters; production config remains empty.

Integration/Audit/outbox evidence ensures K4 human mutations and internal batch/candidate/promotion/replay events remain tenant-correlated/internal without public API/webhook eligibility.

`KingdomIngestionAcceptanceTest.php` exercises one complete source window containing both supported target kinds, validates resulting canonical provenance and then continues through retry, revocation and retention boundaries.

## 6. Idempotency, concurrency and asynchronous validation

Accepted K1/K3 exact observation retries and transfer/import idempotency remain protected. K4 source-window uniqueness, deterministic candidate identity and promoted-history identity protect exact retry.

P4 proves transactional due claims, queue uniqueness/overlap controls, cursor advancement only after successful/partial batches, completed-window replay with matching next cursor, bounded retries/backoff/circuit behavior and retry-exhaustion finalization.

P5 proves source reconciliation is idempotent: once approval removal disables the subscription, a repeated reconciliation makes no additional transition. Queue/cache controls remain additive; database/domain idempotency remains authoritative.

P6 replays the exact accepted test source window and proves the repository still has one batch, two candidates and one promoted canonical record per target. It then removes adapter approval and proves one disable transition followed by an idempotent reconciliation no-op.

## 7. Persistence, migration, rollback and recovery evidence

The full Kingdom migration round-trip includes the K4 foundation, player/game-Alliance provenance and scheduling migrations. CI clean PostgreSQL migration and shared backup/restore remain required.

P5 retention tests prove terminal normalized payloads redact before terminal candidate/batch deletion; subscriptions survive operational pruning; quarantined rows receive the longer review window; and batch pruning waits until candidate rows are gone. Promoted canonical history/provenance remains structurally independent of operational K4 retention.

P6 re-proves the critical retention seam end-to-end: terminal normalized payloads are redacted, operational candidate/batch rows are later pruned, the subscription remains, and promoted `PlayerSnapshot`/`KingdomAllianceObservation` records retain copied source subscription/batch/adapter/version/record/hash provenance after their operational rows are gone.

## 8. Performance, query and capacity evidence

Accepted query gates remain:

- K1: 150 tracked players / 450 snapshots with bounded intelligence query shape;
- K2: 150 transfer participants / 20 groups with bounded planning projections;
- K3: 120 tracked game Alliances / 600 observations / 120 diplomacy relationships / 60 contacts with manager intelligence at ≤10 SELECTs; and
- K4: 250 subscriptions / 40 failed batches / 110 candidates with the operational-health snapshot at ≤8 SELECTs.

K4 safety bounds remain 250 records/page, poll interval 60–86,400 seconds, 120-second job timeout, bounded retry/circuit state and low dedicated queue concurrency. The aggregate query gate is repository-level operations capacity evidence, not a real-source throughput SLO.

Focused tests verify a clean health snapshot does not require attention and a source-revoked subscription is counted and sets `attentionRequired`. The realistic-volume fixture validates expected counts for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches. Monitoring output is aggregate/payload-free.

## 9. Accessibility and frontend evidence

The Kingdom accessibility architecture suite includes `KingdomIngestionManage.vue`, requiring main landmark/primary heading/native controls/labels/table overflow semantics. P6 adds no new public/manager UI surface; the whole-increment candidate passed frontend dependency audit, ESLint, pinned Prettier, Vue/TypeScript and production frontend build.

## 10. Historical accepted evidence

K1, K2, K3 and K4 retain their accepted increment/exit evidence under the Kingdoms product directory. Historical SHAs/run IDs remain immutable evidence even as current living contracts evolve.

K4 whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed:

- Dependency Review `31556412455` — success;
- CodeQL `31556412413` — success;
- CI `31556412468` — success;
- Pint — 529 files;
- PHPStan/Larastan — 374/374, zero errors;
- ParaTest/PHPUnit — 429 tests / 9,799 assertions;
- frontend/build and clean PostgreSQL migrations — success; and
- immutable image, ephemeral staging, backup/restore, image scan and cleanup — success.

Focused K4 evidence includes `KingdomIngestionOperationsHardeningTest.php`, `KingdomIngestionOperationsPerformanceTest.php`, scheduler/promotion/foundation suites and the final `KingdomIngestionAcceptanceTest.php`.

The preceding P5→P6 transition head `482b3b8b3eb07bc9211ba4e7c30e1cceff6c2303` independently passed Dependency Review `31556128860`, CodeQL `31556128870`, and CI `31556128858` before P6 work began.

## 11. Evidence identity, retention and supersession

Historical accepted SHAs/run IDs remain immutable evidence. Current behavior follows current code/tests/living contracts. Each K4 slice records the exact runtime candidate and validates the containing evidence/status head before continuation.

K4 operational retention changes the lifetime of ingestion scaffolding, not the retention/supersession rules for acceptance evidence or promoted canonical business history.

The final K4 exit report records the accepted implementation candidate; the exact containing acceptance/status documentation head must independently remain protected-green before the increment is considered closed.

## 12. Gaps, non-capabilities and related documentation

K4 validation does not prove a real production source/network/credential path, provider terms/authorization, real-source rate/schema/cursor behavior, provider-side revocation semantics, or production cutover. Those remain explicit source-enablement/separate approval gates.

Related: [Automated ingestion](../automated-ingestion.md), [K4 exit report](../product/kingdoms-automated-ingestion-exit-report.md), [Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md), [Slice E security review](../security/kingdoms-automated-ingestion-operations-security-review.md), [Security profile](../security/README.md), [Operations profile](../operations/README.md), [Automated ingestion operations](../operations/kingdoms-automated-ingestion.md), [Interfaces](../interfaces/README.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 testing/evidence coverage matrix](../../../product/testing-evidence-coverage-matrix.md).
