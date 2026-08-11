# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** In progress — `K4-P0` Complete; `K4-P1` / Slice A runtime validated; `K4-P2` next after the Slice A evidence-head gate  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**K4-P0 exit:** [K4-P0 exit report](kingdoms-automated-ingestion-p0-exit-report.md)  
**Slice A validation:** [K4-P1 validation](kingdoms-automated-ingestion-slice-a-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-004`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

This plan sequences controlled automated ingestion into independently reversible slices while preserving K1–K3 identity, tenancy, append-history, privacy, and human-decision rules.

The entire increment is governed by these non-negotiable rules:

- explicit repository/operator source-adapter approval rather than arbitrary tenant network destinations;
- no scraping, OCR, browser/game-client automation, bots, or undocumented/unapproved APIs;
- Alliance-owned subscription/batch/candidate state with captured Kingdom context;
- stable game IDs as the only automatic target identity keys;
- quarantine before guesswork;
- deterministic at-least-once/replay identity and machine/source provenance;
- promotion only through existing Kingdoms business actions;
- no automatic roster/tracking/membership/transfer/diplomacy/scoring/recommendation behavior;
- no plaintext source secrets or canonical raw-response archive in Kingdoms state/log/audit/outbox;
- internal-only `kingdoms.*` events and no public inbound Kingdoms machine contract; and
- real source enablement/production cutover remain separate approval decisions.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K4-P0` | **Complete** | Source/identity/tenancy/provenance/quarantine/automation contract locked | Pre-runtime contract gate |
| `K4-P1` | **Validated runtime** | Adapter allowlist, subscriptions, batches, candidates, quarantine/rejection, manager control plane | Slice A |
| `K4-P2` | **Next** | Existing-roster stable-player-ID promotion through player-snapshot action | Slice B |
| `K4-P3` | Blocked by P2 | Existing-tracking stable-game-Alliance-ID promotion through K3 observation action | Slice C |
| `K4-P4` | Blocked by P3 | Scheduler/cursor/retry/replay/concurrency | Slice D |
| `K4-P5` | Blocked by P4 | Operations/review/retention/source-revocation hardening | Slice E |
| `K4-P6` | Blocked by P5 | Whole-increment acceptance | Whole increment |

`K4-P1` becomes final continuation state only when the evidence/status head containing its validation record independently passes protected Dependency Review, CodeQL, and full CI. When that gate is green, `K4-P2` is authorized automatically; no extra P1-only transition commit is required.

## 3. `K4-P0` — Contract lock — Complete

P0 locked the approved-source boundary, Alliance/current-Kingdom ownership, stable-ID-only matching, factual-observation-only automation, quarantine/idempotency/provenance rules, secret/raw-response exclusion, internal-event boundary, and later source-approval requirement.

Candidate `89a045758c449613df9d2ebbdcb0d8e0c29e3d4c` passed Dependency Review `31523541124`, CodeQL `31523541089`, and CI `31523541048`. Final P0 evidence head `ff41a7519acad7d7365669188f7e717462639367` independently passed Dependency Review `31524097319`, CodeQL `31524097356`, and CI `31524097325`.

P0 introduced no runtime code and remains historical contract evidence.

## 4. `K4-P1` / Slice A — Ingestion foundation — Validated runtime

### Delivered persistence and state

Slice A adds:

- `KingdomIngestionSubscription` with captured Alliance/Kingdom/adapter/version/state and safe cursor/health fields;
- `KingdomIngestionBatch` with deterministic source-window identity, state/counters/timing/failure code; and
- `KingdomIngestionCandidate` with target kind, stable/source identifiers, capture time, bounded normalized payload, SHA-256 payload/identity hashes, state and quarantine/rejection reason.

Target kinds are exactly `player_snapshot` and `alliance_observation`. Production `config/kingdoms.php` registers **no source adapter**.

### Delivered behavior

- adapter interface/registry definitions are code/config allowlisted;
- managers can create/enable/pause/disable only registered adapters for the active Alliance/current Kingdom;
- manager controls require `kingdoms.manage` plus recent password confirmation;
- internal batch start is deterministic per subscription/source window;
- normalized candidate staging validates target-specific fields/bounds;
- missing stable identity quarantines;
- exact candidate retries resolve to one durable candidate;
- managers can explicitly reject their own quarantined candidate;
- current-Kingdom drift blocks new automated work/re-activation without rewriting history; and
- safe audit/outbox evidence is emitted without source secrets/raw responses.

### Explicit Slice A stop line

Slice A does **not** implement an external acquisition call, scheduler/queue worker, concrete adapter, source credentials, automatic candidate promotion, roster/tracking creation, public API/webhook, or decision automation. Tests prove staging produces zero `PlayerSnapshot` and `KingdomAllianceObservation` rows.

### Protected validation

Runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed:

- Dependency Review `31533284318` — success;
- CodeQL `31533284195` — success;
- CI `31533284398` — success;
- Pint 509 files;
- PHPStan/Larastan 363/363, zero errors;
- 407 tests / 9,466 assertions;
- frontend checks/build, PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, scan, cleanup.

See [Slice A validation](kingdoms-automated-ingestion-slice-a-validation.md), [living capability](../automated-ingestion.md), [security review](../security/kingdoms-automated-ingestion-foundation-security-review.md), and [operations](../operations/kingdoms-automated-ingestion.md).

## 5. `K4-P2` / Slice B — Player observation promotion

Objective: promote only an unambiguous player fact for an **existing Alliance roster entry** whose neutral `KingdomPlayer` matches the approved stable game-player ID in the subscription/current Kingdom.

Required behavior:

- extend/compose the accepted player-snapshot action with explicit machine/source provenance rather than a fabricated User actor;
- resolve stable player ID beneath the Alliance/current-Kingdom roster boundary;
- never name-match or auto-enroll an unknown player;
- preserve snapshot value/capture bounds and append-history semantics;
- make candidate→snapshot promotion exact-retry safe across candidate identity and snapshot idempotency;
- quarantine unknown/ambiguous/out-of-context targets;
- record safe promotion outcome/provenance on the candidate without copying secrets/raw payloads; and
- keep member/manager disclosure boundaries unchanged.

P2 adds no scheduler/external source requirement; tests may continue using an approved fixture adapter.

Exit: protected full CI plus promotion-specific security/tenant/idempotency/history tests and updated living evidence.

## 6. `K4-P3` / Slice C — Game-Alliance observation promotion

Objective: promote only an unambiguous factual observation for an **existing active `TrackedKingdomAlliance`** whose neutral `KingdomAlliance` matches the approved stable game-Alliance ID.

Promotion must delegate through the accepted K3 observation action/semantics, preserve correction/history/idempotency/current-Kingdom rules, and never create tracking, change diplomacy/contacts, or derive scores/recommendations.

Unknown/inactive/stale-context/ambiguous targets quarantine. Exit requires protected full CI and explicit no-diplomacy/no-tracking/no-public-integration evidence.

## 7. `K4-P4` / Slice D — Scheduler, cursor, retry and replay

Only after both promotion paths are validated may K4 add background processing:

- isolated per-subscription acquisition/processing job contract;
- bounded scheduling/rate/timeout/backoff/circuit behavior;
- cursor/checkpoint semantics defined by the approved adapter;
- no concurrent duplicate processing of one subscription/window;
- safe replay/reconciliation of retained candidate/batch state;
- current-Kingdom drift blocking and source-disable handling; and
- operator-visible bounded failure state.

A concrete networked adapter still requires separate source approval, including DNS/redirect/private-address/metadata/TLS/egress/secret controls.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening

Complete manager batch/quarantine/replay visibility, bounded diagnostics, source disable/revocation procedures, retention/pruning rules for operational batches/candidates, metrics/alerts, capacity/performance evidence, and recovery/operator runbooks.

Retention must preserve promoted K1/K3 business history while allowing operational ingestion scaffolding to be pruned safely according to documented policy.

## 9. `K4-P6` — Whole-increment acceptance

Whole-increment acceptance must prove the final code/doc state across source authorization, tenancy/stable-ID identity, secret/SSRF boundaries, quarantine, promotion delegation, at-least-once replay, cursor/concurrency, history integrity, migration rollback/reapply, query/performance, accessibility, event/public-integration exclusion, retention/recovery, and explicit non-capabilities.

The accepted implementation SHA and protected workflow IDs must be recorded in a K4 exit report. Whole-increment repository acceptance still does not prove real production ingress/egress/secrets/operators/alerts/capacity/source permission.

## 10. Continuation rule

On `continue`, read PR #54/current successor branch state and this plan. Stay in the current K4 gate until its implementation **and** containing evidence/status head are protected-green. Do not start a later slice to compensate for a current-slice defect.

For the present state, `K4-P2` may begin only after the Slice A evidence head is green. Then Slice B remains limited to existing-roster player-snapshot promotion; `K4-P3` stays blocked.
