# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** In progress — `K4-P0`, `K4-P1`, and `K4-P2` Complete; `K4-P3` Complete when this containing evidence head is protected-green; `K4-P4` then becomes Current  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**K4-P0 exit:** [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)  
**Slice A validation:** [K4-P1 validation](kingdoms-automated-ingestion-slice-a-validation.md)  
**Slice B validation:** [K4-P2 validation](kingdoms-automated-ingestion-slice-b-validation.md)  
**Slice C validation:** [K4-P3 validation](kingdoms-automated-ingestion-slice-c-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-004`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Sequence controlled machine ingestion into independently validated slices while preserving stable-ID identity, Alliance tenancy, append history, privacy, human decision authority, internal-event boundaries, and separate real-source/cutover approval.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K4-P0` | **Complete** | Source/identity/tenancy/provenance/quarantine/automation contract locked | Pre-runtime gate |
| `K4-P1` | **Complete** | Allowlisted generic control plane: subscriptions, batches, candidates, quarantine/rejection, manager controls | Slice A |
| `K4-P2` | **Complete** | Existing-roster stable-player-ID promotion through shared snapshot action | Slice B |
| `K4-P3` | **Complete when containing evidence head is green** | Existing-active-tracking stable-game-Alliance-ID factual observation promotion through K3 action | Slice C |
| `K4-P4` | **Next after P3 evidence gate** | Scheduler/cursor/retry/replay/concurrency | Slice D |
| `K4-P5` | Blocked by P4 | Operations/review/retention/source-revocation hardening | Slice E |
| `K4-P6` | Blocked by P5 | Whole-increment acceptance | Whole increment |

## 3. `K4-P0` — Contract lock — Complete

P0 locked repository/operator source approval, Alliance/current-Kingdom ownership, stable-ID-only matching, factual-observation-only automation, quarantine/idempotency/provenance, secret/raw-response exclusion, internal-event boundaries, and separate real-source approval.

## 4. `K4-P1` / Slice A — Ingestion foundation — Complete

P1 delivered the generic adapter registry, Alliance/current-Kingdom subscriptions, batches, bounded normalized candidates, deterministic source-window/candidate identity, quarantine/rejection, manager status/control, tenant/drift enforcement, and safe internal evidence. Production adapter configuration remains empty.

Runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` and final evidence head `115fa47fc709a5769acc54a0971f3702e9894b71` passed protected gates.

## 5. `K4-P2` / Slice B — Player observation promotion — Complete

P2 resolves a pending `player_snapshot` candidate by stable game-player ID in the captured/current Kingdom and requires exactly one existing roster relation in the owning Alliance. It never name-matches or auto-enrolls.

Promotion delegates to `RecordPlayerSnapshot` with null User actor and bounded machine provenance. Exact retry returns the same snapshot; later capture appends history. Runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` and final evidence head `3ed9b442c72d386d35f7be146f90e75f4bea9856` passed protected gates.

## 6. `K4-P3` / Slice C — Game-Alliance observation promotion — Complete when evidence head is green

P3 resolves a pending `alliance_observation` candidate by stable game-Alliance ID in the captured/current Kingdom and requires exactly one existing **active** `TrackedKingdomAlliance` relationship in the owning Alliance. It never name/tag-matches, creates tracking, or reactivates archived tracking.

Successful promotion delegates to `RecordKingdomAllianceObservation` with `source=ingestion`, null User actor, and bounded machine provenance. Machine promotion is append-only: candidate schema and recorder reject correction/invalidation semantics, which remain human K3 governance.

Unknown/ambiguous/inactive/out-of-context/revoked-source/invalid candidates quarantine before K3 history mutation. Canonical observations copy bounded provenance without FK dependence on operational candidate rows.

Runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, CI `31541291501`: Pint 515, PHPStan 365/365 zero errors, 417 tests / 9,628 assertions, frontend/build, migrations, immutable image, staging, backup/restore, scan, cleanup.

P3 becomes authoritative as Complete only after the evidence/status head containing [Slice C validation](kingdoms-automated-ingestion-slice-c-validation.md) and updated living contracts independently passes the same protected gate.

## 7. `K4-P4` / Slice D — Scheduler, cursor, retry and replay

Only after both promotion paths are accepted may K4 add background acquisition/scheduling. P4 must implement isolated per-subscription work, bounded schedule/frequency, duplicate-work prevention, adapter-owned cursor semantics, timeout/rate/backoff/circuit behavior, safe retry/replay/reconciliation, drift/source-disable handling, and operator-visible bounded failure state.

P4 must invoke the already accepted staging/promotion contracts rather than directly writing canonical history. At-least-once delivery must not duplicate candidate/promoted records or re-run human business mutations.

A concrete networked adapter still requires separate source approval including authorization/terms, DNS/redirect/private-address/TLS/egress controls, secret handling, schema/version policy, rate limits, cursor semantics, and revocation behavior. Production adapter config may remain empty while generic scheduler mechanics are validated.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening

Complete manager replay visibility, bounded diagnostics, source revocation procedures, operational batch/candidate retention/pruning, metrics/alerts, capacity/performance evidence, and recovery runbooks. Pruning must preserve promoted K1/K3 canonical history/provenance.

## 9. `K4-P6` — Whole-increment acceptance

Prove final source authorization, tenancy/stable-ID identity, secret/network boundaries, quarantine, delegated promotion, at-least-once replay, cursor/concurrency, history integrity, migration/recovery, performance/accessibility, event/public-integration exclusion, retention, and explicit non-capabilities. Record exact accepted SHA/run IDs in the K4 exit report. Repository acceptance still does not itself approve real production source/cutover.

## 10. Continuation rule

On `continue`, read PR #54/current successor branch state and this plan. Stay in the current K4 gate until its implementation **and containing evidence/status head** are protected-green. Do not start a later slice to compensate for a current-slice defect.

For this evidence head, `K4-P4` becomes authorized only if the exact head containing the P3 validation/living documentation passes Dependency Review, CodeQL, and full CI. Otherwise remain in P3 and repair the evidence defect.
