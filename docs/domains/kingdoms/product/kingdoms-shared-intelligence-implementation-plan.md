# KINGDOMS-005 implementation plan

[← Kingdoms opt-in shared intelligence product increment](kingdoms-shared-intelligence-increment.md)

**Status:** In progress — `K5-P0`–`K5-P2` Complete; `K5-P3` Current / selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**K5-P0 decisions:** [K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**K5-P0 exit:** [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Slice B validation:** [K5-P2 validation](kingdoms-shared-intelligence-slice-b-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-005`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Deliver opt-in cross-Alliance sharing of selected safe game-Alliance intelligence without weakening Alliance tenancy, K3 append-history/privacy rules, K4 source isolation or public-integration exclusions.

The sequence proves consent first, then explicit current-fact sharing, before any bounded history crosses tenants.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K5-P0` | **Complete** | Consent, tenancy, same-Kingdom, data-classification, revocation and reshare contract locked | Pre-runtime gate |
| `K5-P1` | **Complete** | Directional two-party sharing agreement/invitation foundation | Slice A |
| `K5-P2` | **Complete** | Explicit shared-target grants + safe recipient current-fact projection | Slice B |
| `K5-P3` | **Current / selected pending transition-head validation** | Bounded accepted shared history + correction/invalidation semantics | Slice C |
| `K5-P4` | Planned | Source/recipient UX, drift/revocation, audit/events and accessibility | Slice D |
| `K5-P5` | Planned | Privacy, retention, operations and capacity hardening | Slice E |
| `K5-P6` | Planned | Whole-increment acceptance | Whole increment |

## 3. `K5-P0` — Contract lock — Complete

P0 locked directional ownership, two-party consent, hash-only invitation handling, same-current-Kingdom scope, explicit per-target sharing, safe/excluded data classes, recipient read-only/non-copy semantics, correction/invalidation projection rules, fail-closed drift/revocation, no reshare, authorization split, internal events and retention principles.

Candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed Dependency Review `31557697685`, CodeQL `31557697793`, and CI `31557697725`.

## 4. `K5-P1` / Slice A — Sharing agreement foundation — Complete

P1 delivered directional hash-only invitation/agreement consent with source create/revoke, recipient accept/decline/leave, same-Kingdom activation, single-use token semantics and safe internal evidence.

Runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions plus frontend, migrations, image, staging, backup/restore and scan.

## 5. `K5-P2` / Slice B — Explicit target selection and current facts — Complete

P2 delivered the first cross-tenant observation projection while preserving source ownership.

Accepted behavior includes:

- explicit `kingdom_intelligence_share_targets` grants; no wildcard share-all mode;
- source-only add/remove under `kingdoms.manage` plus recent password confirmation;
- active source-owned agreement, active source/recipient Alliance, captured/current Kingdom and active source-owned tracking checks;
- recipient-first authorization through active agreement + explicit active grant + live context;
- safe current projection bounded to 250 rows;
- latest accepted K3 observation semantics (`invalidated_at IS NULL`, `captured_at DESC, id DESC`);
- K3 30-day `current|stale|missing` freshness;
- no source tracking ID/stable game ID/private/K4/history leakage;
- no recipient canonical copy;
- no reshare through received tracking/grant state;
- immediate removal/revoke/context-invalidated visibility loss;
- persistent agreement terminalization on supported Alliance→Kingdom change, preventing access resume after leaving and returning; and
- focused current projection bounded to no more than two SELECTs for 12 explicit targets.

Runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint **550 files**, PHPStan/Larastan **390/390 zero errors**, **440 tests / 10,025 assertions**, frontend/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

See [Slice B validation](kingdoms-shared-intelligence-slice-b-validation.md), [Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md), and [living shared-intelligence contract](../shared-intelligence.md).

## 6. `K5-P3` / Slice C — Bounded history and correction semantics

### Runtime outcome

Add a bounded recipient history projection for one explicitly shared active target using the source's accepted K3 observation history. P3 adds no recipient canonical copy and no new mutation authority.

### Required behavior

- history request begins from the active recipient Alliance and resolves an active/context-valid share plus active explicit target grant;
- target remains source-owned, active and in the captured Kingdom;
- source and recipient Alliances remain active in the captured Kingdom;
- only accepted/non-invalidated source observations participate;
- ordering is deterministic by `captured_at DESC, id DESC`;
- history is bounded/paginated and never exports an unbounded source timeline;
- history fields are restricted to the same safe factual observation values as P2 plus capture time/freshness context needed for presentation;
- correction/invalidation reason, actor, source tracking ID, stable game ID and all K4 provenance remain excluded;
- source invalidation removes the invalidated fact from recipient history immediately;
- a corrected accepted replacement appears only as its own accepted observation;
- missing remains distinct from zero;
- target removal/share revocation/Kingdom-context invalidation immediately prevents history access;
- recipient reads create no recipient-owned history rows;
- received history cannot be re-shared or used as an upstream K5 target; and
- P3 adds no automatic decisions or public API/webhook surface.

### Evidence

Feature/tenant-isolation/query tests must prove bounded pagination, accepted-only ordering, source invalidation/correction propagation, safe-field projection, no private/K4 disclosure, no recipient canonical copy, no reshare, immediate revoke/remove/drift loss of history access and bounded query behavior.

### Entry gate

Actual P3 runtime code may begin only after the exact containing status/evidence head that records P2 Complete / P3 Current passes Dependency Review, CodeQL and full CI.

## 7. `K5-P4` / Slice D — UX, drift, audit and accessibility

Complete source/recipient management/read pages, explicit sharing state/drift/revocation presentation, safe Audit/internal-outbox evidence and source-level accessibility. P4 does not widen K5 data classes or public integration exposure.

## 8. `K5-P5` / Slice E — Privacy, retention, operations and capacity

Harden invitation cleanup, agreement/grant history, authorization-safe diagnostics/caching, realistic-volume current/history query gates, backup/restore and retention without materializing recipient copies of source observation history.

## 9. `K5-P6` — Whole-increment acceptance

P6 must prove one complete cross-tenant seam: consent → explicit target → safe current/history reads → private-field exclusion → correction/invalidation propagation → no copy/reshare/mutation → immediate authorization loss → unrelated-tenant failure.

Whole-increment evidence must record the exact implementation SHA and protected Dependency Review, CodeQL, full CI, migrations, static analysis, frontend/accessibility, image, staging, backup/restore and scan results.

## 10. Continuation rule

On `continue`, remain at the current K5 gate until both runtime/evidence and the exact containing status head are protected-green.

For this transition, `K5-P3` becomes writable only if the exact head containing P2 Complete / P3 Current passes Dependency Review, CodeQL and full CI. Otherwise remain at the P2 transition and repair only that defect.

Do not widen K5 to player/roster sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, transitive reshare, scoring/ranking or automatic decisions without a separately reviewed scope change.