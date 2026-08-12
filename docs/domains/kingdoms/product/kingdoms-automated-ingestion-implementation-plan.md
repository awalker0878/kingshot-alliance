# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** **Complete / Accepted** — `K4-P0`–`K4-P6` Complete  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**K4-P0 exit:** [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)  
**Slice A validation:** [K4-P1 validation](kingdoms-automated-ingestion-slice-a-validation.md)  
**Slice B validation:** [K4-P2 validation](kingdoms-automated-ingestion-slice-b-validation.md)  
**Slice C validation:** [K4-P3 validation](kingdoms-automated-ingestion-slice-c-validation.md)  
**Slice D validation:** [K4-P4 validation](kingdoms-automated-ingestion-slice-d-validation.md)  
**Slice E validation:** [K4-P5 validation](kingdoms-automated-ingestion-slice-e-validation.md)  
**Whole-increment exit:** [K4-P6 exit report](kingdoms-automated-ingestion-exit-report.md)  
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
| `K4-P4` | **Complete** | Generic scheduler/cursor/retry/replay/concurrency around accepted staging/promotions | Slice D |
| `K4-P5` | **Complete** | Operations/review/retention/source-revocation/health/capacity hardening | Slice E |
| `K4-P6` | **Complete / Accepted** | Whole-increment acceptance | Whole increment |

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

## 7. `K4-P4` / Slice D — Scheduler, cursor, retry and replay — Complete

P4 added generic acquisition scheduling after both promotion paths were accepted: transactional due claims, isolated `kingdoms-ingestion` Horizon queue, bounded adapter poll/page contracts, unique/overlap-protected jobs, opaque cursor progression, bounded retries/backoff/circuit state, retry-exhaustion batch finalization and password-confirmed quarantined-candidate replay.

Runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed DR `31545866277`, CodeQL `31545866288`, CI `31545866249`. Repaired containing evidence head `3bf795e12a99a98c5ad71e570744743056cedd14` independently passed DR `31547224197`, CodeQL `31547224301`, and CI `31547224414`.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening — Complete

P5 added repository-controlled source-revocation reconciliation, bounded operational retention/pruning, payload-free aggregate health/attention signals, scheduled maintenance commands, recovery/runbook hardening and realistic-volume operations capacity evidence.

Default retention redacts promoted/rejected normalized candidate payloads after 30 days, purges terminal promoted/rejected candidates after 90 days, retains quarantined candidates for 180 days, purges terminal batches after 90 days only when candidate-free, and compacts disabled-subscription scheduling/failure state after 30 days while preserving the subscription row.

Revoked/missing adapter approval disables active/paused subscriptions with bounded `source_unapproved` state. The health projection is aggregate and performance-gated at 250 subscriptions, 40 failed batches and 110 candidates with no more than eight SELECT queries.

Runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed DR `31552113152`, CodeQL `31552113044`, CI `31552113042`: Pint 528 files, PHPStan/Larastan 374/374 zero errors, 428 tests / 9,736 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore and scan.

P5 still does not approve a concrete source. Real-source-specific authorization/terms, network/DNS/redirect/private-address/TLS/egress, secret, rate/timeout, schema/version, cursor, monitoring and revocation evidence remain source-enablement prerequisites while production adapter configuration is empty.

## 9. `K4-P6` — Whole-increment acceptance — Complete

P6 revalidated the complete generic repository increment across source authorization and empty-production-allowlist defaults; Alliance/current-Kingdom tenancy and stable-ID-only identity; secret/raw-response/network exclusion; quarantine/rejection and delegated existing-target-only promotion; at-least-once replay/cursor/concurrency/retry behavior; source-revocation fail-closed behavior; operational retention with canonical-history/provenance independence; migration/backup/recovery; realistic-volume query evidence; accessibility/frontend; internal-event/public-integration exclusion; and explicit decision-automation non-capabilities.

The dedicated whole-increment acceptance scenario promotes both supported target kinds, replays the exact source window idempotently, revokes source approval, verifies operational attention, redacts/prunes K4 operational rows and proves promoted canonical history/provenance remains intact.

Validated whole-increment runtime candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed:

- Dependency Review `31556412455` — success;
- CodeQL `31556412413` — success;
- CI `31556412468` — success;
- Pint — 529 files;
- PHPStan/Larastan — 374/374, zero errors;
- ParaTest/PHPUnit — 429 tests / 9,799 assertions; and
- frontend/build, clean migrations, immutable image, staging, backup/restore and image scan — success.

See the [K4 exit report](kingdoms-automated-ingestion-exit-report.md). Repository acceptance does not approve real production source/cutover; those remain separately governed.

## 10. Continuation rule

`KINGDOMS-004` is closed at `K4-P6` once the exact containing acceptance/evidence status head is protected-green. Future work must begin as a separately governed increment or source-enablement/cutover approval; do not reopen K4 acceptance to smuggle in a concrete source, cross-Alliance sharing or decision automation.
