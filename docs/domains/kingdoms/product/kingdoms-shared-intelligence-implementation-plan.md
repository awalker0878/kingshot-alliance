# KINGDOMS-005 implementation plan

[← Kingdoms opt-in shared intelligence product increment](kingdoms-shared-intelligence-increment.md)

**Status:** In progress — `K5-P0`–`K5-P3` Complete; `K5-P4` Current / selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**K5-P0 decisions:** [K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**K5-P0 exit:** [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Slice B validation:** [K5-P2 validation](kingdoms-shared-intelligence-slice-b-validation.md)  
**Slice C validation:** [K5-P3 validation](kingdoms-shared-intelligence-slice-c-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-005`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Deliver opt-in cross-Alliance sharing of selected safe game-Alliance intelligence without weakening Alliance tenancy, K3 append-history/privacy rules, K4 source isolation or public-integration exclusions.

Consent, explicit current facts and bounded accepted history are now independently accepted. P4 is the first slice allowed to expose those accepted contracts through complete first-party source/recipient pages.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K5-P0` | **Complete** | Consent, tenancy, same-Kingdom, data-classification, revocation and reshare contract locked | Pre-runtime gate |
| `K5-P1` | **Complete** | Directional two-party sharing agreement/invitation foundation | Slice A |
| `K5-P2` | **Complete** | Explicit shared-target grants + safe recipient current-fact projection | Slice B |
| `K5-P3` | **Complete** | Bounded accepted shared history + correction/invalidation semantics | Slice C |
| `K5-P4` | **Current / selected pending transition-head validation** | Source/recipient UX, drift/revocation presentation, audit/events and accessibility | Slice D |
| `K5-P5` | Planned | Privacy, retention, operations and capacity hardening | Slice E |
| `K5-P6` | Planned | Whole-increment acceptance | Whole increment |

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

## 7. `K5-P4` / Slice D — UX, drift, audit and accessibility

### Runtime outcome

Expose the accepted K5 consent/grant/current/history contracts through complete first-party source-manager and recipient-member/manager pages without widening the accepted data boundary.

### Required behavior

- add a manager sharing workspace for source invitation creation, outbound agreement state, explicit target grant/removal and revoke;
- add recipient manager consent flows for token accept/decline and active-share leave;
- add a member-safe recipient shared-intelligence page showing only P2 safe current facts;
- add member-safe bounded history navigation using only P3 opaque continuation cursors;
- **do not expose an arbitrary client-controlled history `asOf` selector** or other mechanism for repeatedly opening progressively older 250-record windows;
- manager agreement/grant state remains manager-only; safe shared current/history facts may be available under `alliance.view`;
- drift/revocation/removed/terminal state is presented clearly and fail closed;
- invitation plaintext is shown only immediately after source creation and must not be placed in persistent page props/history, logs or events;
- page props exclude private source tracking IDs/stable game IDs/actors/reasons/K4 provenance/private text;
- no public tenant directory/search is introduced;
- existing target/context events remain internal-only; any P4 presentation-oriented events must remain safe `kingdoms.*` internal evidence only;
- no recipient mutation/copy/reshare controls are added; and
- all new source/recipient pages/components pass the repository's accessibility/source-level frontend gates.

### Evidence

Feature, tenant-isolation, architecture, frontend and accessibility tests must cover source manager, recipient manager and recipient member perspectives; invitation one-time disclosure; current/history safe props; opaque cursor navigation; no arbitrary historical-window control; drift/revoke/remove terminal presentation; permission/password boundaries; no private/K4 disclosure; no public API/webhook/directory; and source-level accessibility.

### Entry gate

Actual P4 runtime/UI code may begin only after the exact containing status/evidence head that records P3 Complete / P4 Current passes Dependency Review, CodeQL and full CI.

## 8. `K5-P5` / Slice E — Privacy, retention, operations and capacity

Harden invitation cleanup, agreement/grant history, authorization-safe diagnostics/caching, realistic-volume current/history query gates, backup/restore and retention without materializing recipient copies of source observation history.

## 9. `K5-P6` — Whole-increment acceptance

P6 must prove one complete cross-tenant seam: consent → explicit target → safe current/history reads → complete first-party presentation → private-field exclusion → correction/invalidation propagation → no copy/reshare/mutation → immediate authorization loss → unrelated-tenant failure.

Whole-increment evidence must record the exact implementation SHA and protected Dependency Review, CodeQL, full CI, migrations, static analysis, frontend/accessibility, image, staging, backup/restore and scan results.

## 10. Continuation rule

On `continue`, remain at the current K5 gate until both runtime/evidence and the exact containing status head are protected-green.

For this transition, `K5-P4` becomes writable only if the exact head containing P3 Complete / P4 Current passes Dependency Review, CodeQL and full CI. Otherwise remain at the P3 transition and repair only that defect.

Do not widen K5 to player/roster sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, transitive reshare, scoring/ranking or automatic decisions without a separately reviewed scope change.
