# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral identity, tenant-owned Kingdoms workflows, K4 stable-ID promotion isolation/idempotency/history, realistic-volume query bounds, and explicit public/decision-automation non-capabilities  
**P5 evidence decision:** Living suite map retains the frozen DCP-P5 evidence contract while adding governed K4 slice evidence

## 1. Critical claims and validation ownership

Kingdoms tests prove neutral identity is not tenant authority, automatic identity uses stable IDs only, historical observations are append-oriented, human transfer/diplomacy/correction decisions remain explicit, and K4 promotion cannot bypass tenant/source/history boundaries or create missing tenant relationships.

## 2. Executable suite mapping

Architecture, Feature, Integration, Performance, TenantIsolation, and Unit suites remain material. K4-P3 adds focused feature/migration coverage while reusing accepted K3 observation, authorization, audit/outbox, integration-exclusion, security, operations, and migration evidence.

## 3. Architecture and domain-boundary validation

Architecture guards retain no public Kingdoms API/wildcard webhook, no scoring/automatic diplomacy/transfer behavior, and no ingestion sub-surface under the historical K3 game-Alliance routes. Production adapter configuration remains empty.

K4 promotion is internal application/domain behavior, not a new public route or cross-domain machine contract.

## 4. Authorization, tenancy, security and privacy validation

P2 proves cross-tenant player/roster isolation. P3 proves a shared neutral game Alliance does not permit cross-Alliance observation mutation, an Alliance without active tracking is quarantined rather than auto-tracked/reactivated, manager/member provenance disclosure remains bounded, and revoked-source/current-context checks fail before mutation.

The living [Kingdoms security profile](../security/README.md) and focused P3 security review own current threat/control interpretation.

## 5. Feature, interface and integration validation

Focused P3 coverage validates successful existing-active-tracking promotion, machine-origin null actor plus bounded source provenance, candidate promoted-record linkage, manager history provenance, accepted neutral-current-name synchronization, and internal audit/outbox promotion evidence. No new HTTP promotion endpoint exists.

The current first-party/public-machine boundary is recorded in [Kingdoms interfaces](../interfaces/README.md).

## 6. Idempotency, concurrency and asynchronous validation

Exact retry of a promoted game-Alliance candidate returns the same observation and does not duplicate promotion evidence. A later candidate capture appends a second observation. Machine promotion cannot request K3 correction/invalidation.

K4 still has no autonomous scheduler/worker; P4 must independently validate queue isolation, duplicate-work prevention, backoff/circuit behavior, cursor ownership, and safe replay.

## 7. Persistence, migration, rollback and recovery evidence

P2 player-provenance migration and P3 `2026_08_11_210000_add_ingestion_provenance_to_alliance_observations.php` are focused down/up tested and pass clean PostgreSQL CI migration. Canonical promoted observations store copied bounded provenance with no FK to operational candidate/batch/subscription rows.

Recovery/operator interpretation remains in [Kingdoms operations](../operations/README.md).

## 8. Performance, query and capacity evidence

Accepted K1/K2/K3 query gates remain unchanged. P2/P3 add no production-source throughput claim. Scheduler/source/batch capacity, queue/backpressure, replay, and retention evidence remain P4/P5 requirements.

## 9. Accessibility and frontend evidence

K4-P3 introduces no new frontend component. Existing Kingdoms accessibility checks and full frontend quality/build remain protected-green on the runtime candidate. Manager history only extends existing private rows with bounded provenance.

## 10. Historical accepted evidence

K1, K2, and K3 accepted implementation evidence remains immutable. K4 P0/P1/P2 accepted evidence remains recorded in their exit/validation records.

K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, CI `31541291501`: Pint 515 files; PHPStan/Larastan 365/365 zero errors; 417 tests / 9,628 assertions; image/staging/backup/scan success.

## 11. Evidence identity, retention and supersession

Historical SHAs/run IDs remain immutable evidence. Current truth follows code/tests/living contracts. Each K4 slice requires a protected-green runtime candidate and then a protected-green containing evidence/status head before continuation.

## 12. Gaps, non-capabilities and related documentation

Current validation does not prove a real source/network, credentials, scheduler/worker/cursor/retry/replay loop, production throughput/retention, or cutover. Machine tracking lifecycle, K3 correction/invalidation, diplomacy/contacts, scoring/ranking/recommendations remain explicitly outside K4 automation.

Related documentation:

- [Kingdoms domain](../README.md)
- [Kingdoms security](../security/README.md)
- [Kingdoms operations](../operations/README.md)
- [Kingdoms interfaces](../interfaces/README.md)
- [Automated ingestion](../automated-ingestion.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)
- [Slice C validation](../product/kingdoms-automated-ingestion-slice-c-validation.md)
- [Slice C security review](../security/kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Testing/evidence standard](../../../product/testing-evidence-standard.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
