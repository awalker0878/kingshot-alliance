# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** In progress — `K4-P0` Complete; `K4-P1` Complete; `K4-P2` Complete when this containing evidence head is protected-green; `K4-P3` then becomes Current  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**K4-P0 exit:** [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)  
**Slice A validation:** [K4-P1 validation](kingdoms-automated-ingestion-slice-a-validation.md)  
**Slice B validation:** [K4-P2 validation](kingdoms-automated-ingestion-slice-b-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-004`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Sequence controlled machine ingestion into independently validated slices while preserving stable-ID identity, Alliance tenancy, append history, privacy, human decision authority, internal-event boundaries, and separate real-source/cutover approval.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K4-P0` | **Complete** | Source/identity/tenancy/provenance/quarantine/automation contract locked | Pre-runtime gate |
| `K4-P1` | **Complete** | Allowlisted generic control plane: subscriptions, batches, candidates, quarantine/rejection, manager controls | Slice A |
| `K4-P2` | **Complete when containing evidence head is green** | Existing-roster stable-player-ID promotion through shared snapshot action | Slice B |
| `K4-P3` | **Next after P2 evidence gate** | Existing-tracking stable-game-Alliance-ID promotion through K3 observation action | Slice C |
| `K4-P4` | Blocked by P3 | Scheduler/cursor/retry/replay/concurrency | Slice D |
| `K4-P5` | Blocked by P4 | Operations/review/retention/source-revocation hardening | Slice E |
| `K4-P6` | Blocked by P5 | Whole-increment acceptance | Whole increment |

## 3. `K4-P0` — Contract lock — Complete

P0 locked repository/operator source approval, Alliance/current-Kingdom ownership, stable-ID-only matching, factual-observation-only automation, quarantine/idempotency/provenance, secret/raw-response exclusion, internal-event boundaries, and separate real-source approval.

Candidate `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c` and final evidence head `ff41a7519acad7d7365669188f7e717462639367` passed their protected gates.

## 4. `K4-P1` / Slice A — Ingestion foundation — Complete

P1 delivered the generic adapter registry, Alliance/current-Kingdom subscriptions, batches, bounded normalized candidates, deterministic source-window/candidate identity, quarantine/rejection, manager status/control, tenant/drift enforcement, and safe internal evidence. Production adapter configuration remains empty and P1 creates no business observation history.

Runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` and final evidence head `115fa47fc709a5769acc54a0971f3702e9894b71` passed protected Dependency Review, CodeQL, and full CI.

## 5. `K4-P2` / Slice B — Player observation promotion — Complete when evidence head is green

P2 resolves a pending `player_snapshot` candidate by stable game-player ID in the captured/current Kingdom, then requires exactly one existing roster relation in the owning Alliance. It never name-matches or auto-enrolls.

Successful promotion delegates to `RecordPlayerSnapshot` with `source=ingestion`, null User actor, and bounded machine provenance. Manual/CSV idempotency remains unchanged. Exact candidate retry returns the same snapshot; later capture remains append history. Unknown/ambiguous/out-of-context/revoked-source/invalid candidates quarantine before business mutation.

Canonical snapshots carry bounded provenance without FK dependence on operational candidate rows, preserving accepted history across future operational pruning.

Runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed DR `31538958810`, CodeQL `31538958745`, CI `31538958920`: Pint 512, PHPStan 364/364 zero errors, 412 tests / 9,564 assertions, frontend/build, migrations, immutable image, staging, backup/restore, scan, cleanup.

P2 becomes authoritative as Complete only after the evidence/status head containing [Slice B validation](kingdoms-automated-ingestion-slice-b-validation.md) and updated living contracts independently passes the same protected gate.

## 6. `K4-P3` / Slice C — Game-Alliance observation promotion

Once P2 evidence is green, implement only stable-game-Alliance-ID promotion for an **existing active `TrackedKingdomAlliance`**. Delegate through accepted K3 observation semantics; preserve correction/history/idempotency/current-Kingdom rules; never create tracking or mutate diplomacy/contacts/scores/recommendations.

Unknown/inactive/stale-context/ambiguous targets quarantine. Exit requires a protected runtime candidate plus a separately protected evidence head.

## 7. `K4-P4` / Slice D — Scheduler, cursor, retry and replay

Only after both promotion paths are accepted may K4 add background acquisition/scheduling: isolated per-subscription work, bounded rate/timeout/backoff/circuit behavior, adapter-owned cursor semantics, duplicate-work prevention, safe replay/reconciliation, source-disable/drift handling, and operator-visible bounded failure state.

A concrete networked adapter still requires separate source approval including DNS/redirect/private-address/TLS/egress/secret controls.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening

Complete manager replay visibility, bounded diagnostics, source revocation procedures, operational batch/candidate retention/pruning, metrics/alerts, capacity/performance evidence, and recovery runbooks. Pruning must preserve promoted K1/K3 canonical history/provenance.

## 9. `K4-P6` — Whole-increment acceptance

Prove final source authorization, tenancy/stable-ID identity, secret/network boundaries, quarantine, delegated promotion, at-least-once replay, cursor/concurrency, history integrity, migration/recovery, performance/accessibility, event/public-integration exclusion, retention, and explicit non-capabilities. Record exact accepted SHA/run IDs in the K4 exit report. Repository acceptance still does not itself approve real production source/cutover.

## 10. Continuation rule

On `continue`, read PR #54/current successor branch state and this plan. Stay in the current K4 gate until its implementation **and containing evidence/status head** are protected-green. Do not start a later slice to compensate for a current-slice defect.

For this evidence head, `K4-P3` becomes authorized only if the exact head containing the P2 validation/living documentation passes Dependency Review, CodeQL, and full CI. Otherwise remain in P2 and repair the evidence defect.
