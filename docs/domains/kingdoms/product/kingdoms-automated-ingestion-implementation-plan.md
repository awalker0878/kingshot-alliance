# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** In progress — `K4-P0`–`K4-P3` Complete; `K4-P4` runtime validated and Complete when this containing evidence head is protected-green; `K4-P5` then becomes Current  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**K4-P0 exit:** [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)  
**Slice A validation:** [K4-P1 validation](kingdoms-automated-ingestion-slice-a-validation.md)  
**Slice B validation:** [K4-P2 validation](kingdoms-automated-ingestion-slice-b-validation.md)  
**Slice C validation:** [K4-P3 validation](kingdoms-automated-ingestion-slice-c-validation.md)  
**Slice D validation:** [K4-P4 validation](kingdoms-automated-ingestion-slice-d-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-004`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Sequence controlled machine ingestion into independently validated slices while preserving stable-ID identity, Alliance tenancy, append history, privacy, human decision authority, internal-event boundaries and separate real-source/cutover approval.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K4-P0` | **Complete** | Source/identity/tenancy/provenance/quarantine/automation contract locked | Pre-runtime gate |
| `K4-P1` | **Complete** | Allowlisted generic control plane: subscriptions, batches, candidates, quarantine/rejection, manager controls | Slice A |
| `K4-P2` | **Complete** | Existing-roster stable-player-ID promotion through shared snapshot action | Slice B |
| `K4-P3` | **Complete** | Existing-active-tracking stable-game-Alliance-ID factual observation promotion through K3 action | Slice C |
| `K4-P4` | **Complete when containing evidence head is green** | Generic scheduler/cursor/retry/replay/concurrency around accepted staging/promotions | Slice D |
| `K4-P5` | Blocked by P4 | Operations/review/retention/source-revocation hardening | Slice E |
| `K4-P6` | Blocked by P5 | Whole-increment acceptance | Whole increment |

## 3. `K4-P0` — Contract lock — Complete

P0 locked repository/operator source approval, Alliance/current-Kingdom ownership, stable-ID-only matching, factual-observation-only automation, quarantine/idempotency/provenance, secret/raw-response exclusion, internal-event boundaries and separate real-source approval.

## 4. `K4-P1` / Slice A — Ingestion foundation — Complete

P1 delivered the generic adapter registry, Alliance/current-Kingdom subscriptions, batches, bounded normalized candidates, deterministic source-window/candidate identity, quarantine/rejection, manager status/control, tenant/drift enforcement and safe internal evidence. Production adapter configuration remains empty.

Runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` and final evidence head `115fa47fc709a5769acc54a0971f3702e9894b71` passed protected gates.

## 5. `K4-P2` / Slice B — Player observation promotion — Complete

P2 resolves pending `player_snapshot` candidates by stable game-player ID in the captured/current Kingdom and requires exactly one existing roster relation in the owning Alliance. It never name-matches or auto-enrolls.

Promotion delegates to `RecordPlayerSnapshot` with null User actor and bounded machine provenance. Runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` and final evidence head `3ed9b442c72d386d35f7be146f90e75f4bea9856` passed protected gates.

## 6. `K4-P3` / Slice C — Game-Alliance observation promotion — Complete

P3 resolves pending `alliance_observation` candidates by stable game-Alliance ID and requires exactly one existing active owning-Alliance tracking relation. It never name/tag-matches, creates/reactivates tracking, or performs machine correction/invalidation.

Runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` and containing evidence head `5335f64602269c1a5680d5d84013b0de739413bf` passed protected gates.

## 7. `K4-P4` / Slice D — Scheduler, cursor, retry and replay

P4 adds generic acquisition scheduling only after both promotion paths are accepted. It provides transactional due claims, an isolated `kingdoms-ingestion` Horizon queue, bounded adapter poll/page contracts, unique/overlap-protected jobs, opaque cursor progression, bounded retries/backoff/circuit state, retry-exhaustion batch finalization and password-confirmed quarantined-candidate replay.

P4 invokes the accepted staging/P2/P3 promotion contracts rather than directly writing canonical history. Source-window/candidate/promoted-record idempotency remains authoritative under at-least-once delivery.

Runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed DR `31545866277`, CodeQL `31545866288`, CI `31545866249`: Pint 523, PHPStan 371/371 zero errors, 423 tests / 9,697 assertions, frontend/build, migrations, immutable image, staging, backup/restore and scan.

P4 becomes authoritative Complete only after the containing evidence head with [Slice D validation](kingdoms-automated-ingestion-slice-d-validation.md) and updated living contracts independently passes the same protected gate.

A concrete networked adapter still requires separate source approval including authorization/terms, DNS/redirect/private-address/TLS/egress controls, secret handling, schema/version policy, rate limits, cursor semantics and revocation behavior. Production adapter config remains empty.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening

After the P4 evidence gate, complete operational review, source-revocation procedures, operational batch/candidate retention/pruning, metrics/alerts, capacity/performance evidence and recovery/runbook hardening. Pruning must preserve promoted K1/K3 canonical history/provenance.

P5 does not itself approve a concrete source. Where real-source-specific controls cannot be repository-proven with an empty allowlist, record them as source-enablement prerequisites rather than inventing production evidence.

## 9. `K4-P6` — Whole-increment acceptance

Prove final source authorization boundary, tenancy/stable-ID identity, secret/network exclusion, quarantine, delegated promotion, at-least-once replay, cursor/concurrency, history integrity, migration/recovery, performance/accessibility, event/public-integration exclusion, retention and explicit non-capabilities. Record exact accepted SHA/run IDs in the K4 exit report.

Repository acceptance still does not itself approve real production source/cutover.

## 10. Continuation rule

On `continue`, read PR #54/current successor state and this plan. Stay in the current K4 gate until its implementation **and containing evidence/status head** are protected-green. Do not start a later slice to compensate for a current-slice defect.

For this evidence head, `K4-P5` becomes authorized only if the exact head containing the P4 validation/living documentation passes Dependency Review, CodeQL and full CI. Otherwise remain in P4 and repair the evidence defect.
