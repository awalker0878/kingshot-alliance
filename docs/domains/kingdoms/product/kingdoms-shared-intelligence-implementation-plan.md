# KINGDOMS-005 implementation plan

[← Kingdoms opt-in shared intelligence product increment](kingdoms-shared-intelligence-increment.md)

**Status:** **Accepted** — `K5-P0`–`K5-P6` Complete  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**K5-P0 decisions:** [K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**K5-P0 exit:** [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Slice B validation:** [K5-P2 validation](kingdoms-shared-intelligence-slice-b-validation.md)  
**Slice C validation:** [K5-P3 validation](kingdoms-shared-intelligence-slice-c-validation.md)  
**Slice D validation:** [K5-P4 validation](kingdoms-shared-intelligence-slice-d-validation.md)  
**Slice E validation:** [K5-P5 validation](kingdoms-shared-intelligence-slice-e-validation.md)  
**Whole-increment exit:** [K5-P6 exit report](kingdoms-shared-intelligence-exit-report.md)  
**Important:** These are implementation phases inside `KINGDOMS-005`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Deliver opt-in cross-Alliance sharing of selected safe game-Alliance intelligence without weakening Alliance tenancy, K3 append-history/privacy rules, K4 source isolation or public-integration exclusions.

Consent, explicit current facts, bounded accepted history, complete first-party presentation, bounded retention, realistic-volume capacity hardening and the complete cross-tenant acceptance seam are accepted. P6 added acceptance evidence only and did not widen sharing scope.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K5-P0` | **Complete** | Consent, tenancy, same-Kingdom, data-classification, revocation and reshare contract locked | Pre-runtime gate |
| `K5-P1` | **Complete** | Directional two-party sharing agreement/invitation foundation | Slice A |
| `K5-P2` | **Complete** | Explicit shared-target grants + safe recipient current-fact projection | Slice B |
| `K5-P3` | **Complete** | Bounded accepted shared history + correction/invalidation semantics | Slice C |
| `K5-P4` | **Complete** | First-party source/recipient UX, safe page-prop boundary, invitation lifecycle hardening and accessibility | Slice D |
| `K5-P5` | **Complete** | Bounded operational retention + realistic-volume current/history capacity hardening | Slice E |
| `K5-P6` | **Complete** | Whole-increment cross-tenant acceptance | Whole increment |

## 3. `K5-P0` — Contract lock — Complete

P0 locked directional ownership, two-party consent, hash-only invitation handling, same-current-Kingdom scope, explicit per-target sharing, safe/excluded data classes, recipient read-only/non-copy semantics, correction/invalidation projection rules, fail-closed drift/revocation, no reshare, authorization split, internal events and retention principles.

Candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed Dependency Review `31557697685`, CodeQL `31557697793`, and CI `31557697725`.

## 4. `K5-P1` / Slice A — Sharing agreement foundation — Complete

P1 delivered directional hash-only invitation/agreement consent with source create/revoke, recipient accept/decline/leave, same-Kingdom activation, single-use token semantics and safe internal evidence.

Runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions plus frontend, migrations, image, staging, backup/restore and scan.

## 5. `K5-P2` / Slice B — Explicit target selection and current facts — Complete

P2 delivered explicit source target grants/removal plus a bounded recipient-safe current-fact query.

Runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint 550 files, PHPStan/Larastan 390/390 zero errors, 440 tests / 10,025 assertions plus frontend/build, clean migrations, image, staging, backup/restore, scan and cleanup.

Accepted P2 behavior remains explicit-only, recipient-first, same-Kingdom, safe-field-only, non-copying, non-transitive and immediately fail-closed on target removal/revoke/drift.

## 6. `K5-P3` / Slice C — Bounded history and correction semantics — Complete

P3 delivered one internal bounded history query for an explicitly shared active target.

Accepted behavior includes:

- recipient/share/grant/source-context authorization on every page;
- accepted/non-invalidated source observations only;
- deterministic `captured_at DESC, id DESC` ordering;
- encrypted target-bound cursor with fixed `asOf`, keyset position and authenticated accepted-record count;
- default/max page size 50;
- hard 250 accepted-observation traversal cap matching K3 history;
- safe history item fields only;
- no observation/source tracking/stable game/private/K4 identifiers or correction metadata;
- source correction/invalidation propagation without recipient copies;
- immediate fail-closed history after target removal/share revoke/Kingdom drift;
- no access resume after returning to the old Kingdom;
- no recipient canonical history rows; and
- no P3 route/UI/public integration surface.

Runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed Dependency Review `31564263865`, CodeQL `31564263863`, and CI `31564263891`: Pint **553 files**, PHPStan/Larastan **392/392 zero errors**, **443 tests / 10,086 assertions**, frontend/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

See [Slice C validation](kingdoms-shared-intelligence-slice-c-validation.md), [Slice C security review](../security/kingdoms-shared-intelligence-history-security-review.md), and [living shared-intelligence contract](../shared-intelligence.md).

## 7. `K5-P4` / Slice D — UX, drift, audit and accessibility — Complete

P4 exposes the accepted K5 consent/grant/current/history contracts through complete first-party source-manager and recipient-member/manager pages without widening the accepted data boundary.

Accepted behavior includes:

- manager sharing workspace for source invitation creation, outbound agreement state, explicit target grant/removal and revoke;
- recipient manager token accept/decline and active-share leave flows;
- member-safe shared-intelligence page for P2 current facts and P3 bounded history;
- history navigation using only explicit target + P3 opaque continuation cursor;
- no arbitrary client-controlled history `asOf` selector or equivalent older-window control;
- manager agreement/grant state manager-only while safe current/history facts remain member-safe;
- invitation plaintext only in the authenticated creation response and Vue component memory, never Inertia/session props;
- page-prop exclusion of private source/K4/governance metadata;
- accessible semantic controls, labels, captions and status/alert behavior;
- immediate erasure of persisted invitation hashes on accept, decline and revoke; and
- a forward nullable-hash migration with deterministic rollback/reapply compatibility evidence, leaving accepted P1/P2 migrations unchanged.

Runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed Dependency Review `31569202741`, CodeQL `31569202422`, and CI `31569202418`: Pint **556 files**, PHPStan/Larastan **393/393 zero errors**, **448 tests / 10,160 assertions**, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

See [Slice D validation](kingdoms-shared-intelligence-slice-d-validation.md), [Slice D presentation security review](../security/kingdoms-shared-intelligence-presentation-security-review.md), and [living shared-intelligence contract](../shared-intelligence.md).

## 8. `K5-P5` / Slice E — Privacy, retention, operations and capacity — Complete

P5 hardens the accepted sharing capability around operational metadata lifetime and realistic read volume without widening sharing or materializing recipient copies.

Accepted behavior includes:

- `EnforceKingdomIntelligenceSharingRetention` with one total per-run budget clamped to 1–2000 rows and defaulting to 500;
- cleanup priority of expired pending invitations, old terminal agreements, then old removed grants;
- configurable defaults of 30 days after invitation expiry, 180 days for terminal agreements and 90 days for removed grants;
- state/cutoff rechecks in the destructive statement so stale candidate IDs cannot delete reactivated/re-granted rows;
- active agreements and active grants remain ineligible;
- canonical `TrackedKingdomAlliance` / `KingdomAllianceObservation` state remains ineligible;
- Audit/outbox evidence remains outside K5 operational cleanup;
- command `kingdoms:enforce-sharing-retention {--limit=500}` scheduled daily at 04:30 on one server without overlap;
- immediate P4 invitation-hash erasure remains unchanged and is not deferred to retention;
- realistic-volume current evidence at 300 active grants still capped to 250 rows and no more than two SELECTs;
- realistic-volume history evidence at 1,000 source observations still capped to five 50-row pages / 250 accepted observations and no more than two SELECTs per page;
- reviewed encoded response-size ceilings remain bounded in the fixture; and
- no cache/materialized recipient projection was introduced, so accepted live authorization/fail-closed behavior remains the only read authorization seam.

Runtime candidate `b47f639a275652590304fccef051f78997a0153c` passed Dependency Review `31570931190`, CodeQL `31570931290`, and CI `31570931267`: Pint **559 files**, PHPStan/Larastan **394/394 zero errors**, **451 tests / 10,230 assertions**, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

See [Slice E validation](kingdoms-shared-intelligence-slice-e-validation.md), [Slice E security review](../security/kingdoms-shared-intelligence-retention-security-review.md), [retention operations runbook](../operations/kingdoms-shared-intelligence-retention.md), and [living shared-intelligence contract](../shared-intelligence.md).

## 9. `K5-P6` — Whole-increment acceptance — Complete

P6 re-proved one complete cross-tenant seam without adding a feature slice:

consent → explicit target → safe current/history reads → complete first-party presentation → bounded operational retention → private-field exclusion → correction/invalidation propagation → no copy/reshare/mutation → immediate authorization loss → unrelated-tenant failure.

Exact whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed:

- Dependency Review `31573301975` — success;
- CodeQL `31573301988` — success;
- CI `31573301977` — success;
- Pint — **560 files**;
- PHPStan/Larastan — **394/394, zero errors**;
- ParaTest/PHPUnit — **452 tests / 10,322 assertions**;
- `KingdomSharedIntelligenceAcceptanceTest` — passing;
- frontend dependency audit/lint/locked-format/type/build — success;
- clean PostgreSQL migrations — success;
- immutable production image — success;
- ephemeral staging — success;
- backup/restore — success;
- image scan — success; and
- staging cleanup — success.

The immediately preceding transition head `0eb0444ee195fd3a09c9c9c07cdb2b1ddcb92873` independently passed Dependency Review `31572748561`, CodeQL `31572748558`, and CI `31572748595`, proving the P5 Complete / P6 Current transition before P6 work began.

The accepted whole-increment boundary and residual non-capabilities are recorded in the [K5-P6 exit report](kingdoms-shared-intelligence-exit-report.md).

## 10. Continuation rule

`KINGDOMS-005` is closed as **Accepted**. Future work must preserve the accepted K5 boundary and must not reopen or widen K5 implicitly.

Do not widen K5 to player/roster sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, transitive reshare, scoring/ranking or automatic decisions without a separately reviewed scope change.
