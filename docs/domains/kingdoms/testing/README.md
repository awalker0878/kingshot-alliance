# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 stable-ID promotion isolation/idempotency/history, realistic-volume query bounds, and explicit public/decision-automation non-capabilities  
**P5 evidence decision:** Living suite map retains the frozen DCP-P5 evidence contract while adding governed K4 slice evidence

## 1. Critical claims and validation ownership

Kingdoms tests prove neutral identity is not tenant authority, automatic identity uses stable IDs only, historical observations are append-oriented, human transfer/diplomacy decisions remain explicit, and K4 promotion cannot bypass tenant/source/history boundaries.

## 2. Executable suite mapping

Architecture, Feature, Integration, Performance, TenantIsolation, and Unit suites remain material. K4-P2 adds focused feature coverage while reusing existing snapshot, authorization, audit/outbox, integration-exclusion, and migration evidence.

## 3. Architecture and domain-boundary validation

Architecture guards retain no public Kingdoms API/wildcard webhook, no scoring/automatic diplomacy/transfer behavior, and no ingestion sub-surface under the historical K3 game-Alliance routes. Production adapter configuration remains empty.

## 4. Authorization, tenancy, security and privacy validation

P2 tests prove stable neutral player identity does not permit cross-Alliance snapshot mutation, an Alliance without the roster relation is quarantined rather than auto-enrolled, manager/member provenance disclosure remains bounded, and Alliance-Kingdom drift/source revocation fail before mutation.

## 5. Feature, interface and integration validation

Focused P2 coverage validates successful existing-roster promotion, machine-origin null actor plus bounded source provenance, candidate promoted-record linkage, manager history provenance, and internal audit/outbox promotion evidence. No new HTTP promotion endpoint exists.

## 6. Idempotency, concurrency and asynchronous validation

Exact retry of a promoted candidate returns the same snapshot and does not duplicate promotion evidence. A later candidate capture appends a second snapshot. K4 still has no autonomous scheduler/worker; P4 must independently validate concurrency/backoff/cursor/replay behavior.

## 7. Persistence, migration, rollback and recovery evidence

`2026_08_11_200000_add_ingestion_provenance_to_player_snapshots.php` is migration-tested down/up and passed clean PostgreSQL CI migration. It makes snapshot User actor nullable for machine origin, adds bounded source provenance/Alliance source-identity uniqueness, and adds safe candidate promotion references.

Canonical snapshots deliberately have no FK to operational candidate/batch/subscription rows, preserving history across later operational pruning.

## 8. Performance, query and capacity evidence

Accepted K1/K2/K3 query gates remain unchanged. K4-P2 adds no production-source throughput claim. Scheduler/source/batch capacity and retention evidence remain P4/P5 requirements.

## 9. Accessibility and frontend evidence

K4-P2 introduces no new frontend component. Existing Kingdoms accessibility checks and full frontend quality/build remain protected-green on the runtime candidate.

## 10. Historical accepted evidence

K1, K2, and K3 accepted implementation evidence remains immutable. K4-P0 and K4-P1 accepted evidence remains recorded in their exit/validation records.

K4-P2 runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed DR `31538958810`, CodeQL `31538958745`, CI `31538958920`: Pint 512 files; PHPStan/Larastan 364/364 zero errors; 412 tests / 9,564 assertions; image/staging/backup/scan success.

## 11. Evidence identity, retention and supersession

Historical SHAs/run IDs remain immutable evidence. Current truth follows code/tests/living contracts. Each K4 slice requires a protected-green runtime candidate and then a protected-green containing evidence/status head before continuation.

## 12. Gaps, non-capabilities and related documentation

Current validation does not prove a real source/network, credentials, scheduler/worker/cursor/retry loop, game-Alliance promotion, production throughput/retention, or cutover. Those are later gates.

Related: [Automated ingestion](../automated-ingestion.md), [Player snapshots](../snapshots.md), [Slice B validation](../product/kingdoms-automated-ingestion-slice-b-validation.md), [Slice B security review](../security/kingdoms-automated-ingestion-player-promotion-security-review.md), [K4 operations](../operations/kingdoms-automated-ingestion.md), [testing/evidence standard](../../../product/testing-evidence-standard.md), [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md).
