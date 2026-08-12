# KINGDOMS-005 implementation plan

[← Kingdoms opt-in shared intelligence product increment](kingdoms-shared-intelligence-increment.md)

**Status:** In progress — `K5-P0`–`K5-P1` Complete; `K5-P2` Current / selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**K5-P0 decisions:** [K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**K5-P0 exit:** [K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Important:** These are implementation phases inside `KINGDOMS-005`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Deliver opt-in cross-Alliance sharing of selected safe game-Alliance intelligence without weakening the Alliance tenant boundary, K3 append-history/privacy rules, K4 source isolation or existing public-integration exclusions.

The sequence deliberately proves the consent/grant boundary before any source target or observation data can cross tenants.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K5-P0` | **Complete** | Consent, tenancy, same-Kingdom, data-classification, revocation and reshare contract locked | Pre-runtime gate |
| `K5-P1` | **Complete** | Directional two-party sharing agreement/invitation foundation; no shared observations | Slice A |
| `K5-P2` | **Current / selected pending transition-head validation** | Explicit shared-target selection + safe current-fact recipient projection | Slice B |
| `K5-P3` | Planned | Bounded accepted shared history + freshness/correction semantics | Slice C |
| `K5-P4` | Planned | Source/recipient UX, drift/revocation, audit/events and accessibility | Slice D |
| `K5-P5` | Planned | Privacy, retention, operations and capacity hardening | Slice E |
| `K5-P6` | Planned | Whole-increment acceptance | Whole increment |

## 3. `K5-P0` — Contract lock — Complete

P0 locked directional source→recipient ownership, two-party manager consent, hash-only invitation-secret handling, same-current-Kingdom scope, explicit per-target sharing, safe/excluded data classes, recipient read-only/non-copy semantics, correction/invalidation projection rules, fail-closed drift/revocation, no reshare, manager/member authorization split, internal event boundaries and retention principles.

Validated candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac` passed Dependency Review `31557697685`, CodeQL `31557697793`, and CI `31557697725`: Pint 529 files, PHPStan/Larastan 374/374 zero errors, 429 tests / 9,809 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore and scan.

## 4. `K5-P1` / Slice A — Sharing agreement foundation — Complete

P1 delivered one directional consent table plus source invitation creation, recipient acceptance/decline, source revoke and recipient leave.

Accepted invariants include:

- 32 cryptographically random invitation bytes represented as 64 lowercase hex characters;
- SHA-256 hash-only persistence and hidden token hash serialization;
- repository-bounded expiry, default 72 hours;
- `kingdoms.manage` plus recent password confirmation for every consent mutation;
- self-share, different-Kingdom activation, expired/used token and duplicate-active agreement failure;
- deterministic source/recipient row locking during acceptance;
- single-use token semantics;
- source/recipient tenant scoping for terminal actions;
- drift-tolerant access-reducing decline/revoke/leave;
- safe internal Audit/outbox evidence with no plaintext token or observation data; and
- null source-side Audit actor on acceptance to avoid recipient-manager identity leakage.

P1 deliberately adds no shared-target table and no recipient observation/current/history read path.

Runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore, image scan and cleanup.

See [Slice A validation](kingdoms-shared-intelligence-slice-a-validation.md), [Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md), and [living shared-intelligence contract](../shared-intelligence.md).

## 5. `K5-P2` / Slice B — Explicit target selection and current facts

### Runtime outcome

Allow a source manager to select individual existing source-owned `TrackedKingdomAlliance` targets for one active share, then expose a recipient member-safe **current-fact** projection. P2 is the first K5 slice permitted to disclose observation data.

### Required behavior

- target selection requires source `kingdoms.manage` plus recent password confirmation;
- share must be active and source-owned;
- source and recipient current Kingdoms must still equal the captured sharing Kingdom;
- source target must be active, source-owned and in the captured Kingdom;
- no wildcard “share all” mode;
- recipient reads begin from active recipient Alliance, active/context-valid share, then explicitly shared item;
- current fact comes from the latest accepted/non-invalidated source observation under accepted K3 semantics;
- safe fields only: source Alliance identity, neutral/current game-Alliance name/tag, accepted observed name/tag, power/member count when present, capture time and bounded freshness state;
- source manager notes, diplomacy/contact data, observation actor/correction reason and all K4 provenance remain excluded;
- recipient cannot mutate source state, create/reactivate local tracking, copy the fact into recipient canonical history or reshare it;
- item removal, agreement revocation or Kingdom drift immediately removes recipient access; and
- P2 does not yet expose bounded observation history; that remains P3.

### Evidence

Tenant-isolation and feature tests must prove explicit-target-only visibility, safe field projection, unrelated/private source data exclusion, no local canonical copy, no reshare/mutation path, current-observation correction/invalidation behavior, item removal/revocation/drift failure and bounded current-list query behavior.

### Entry gate

Actual P2 code may begin only after the exact containing status/evidence head that records P1 Complete / P2 Current passes Dependency Review, CodeQL and full CI.

## 6. `K5-P3` / Slice C — Bounded history and correction semantics

Add bounded/paginated accepted source observation history for explicitly shared targets. Only accepted/non-invalidated observations appear; source correction/invalidation changes the recipient projection immediately; missing remains distinct from zero; private invalidation reason/actor stays source-private; freshness remains descriptive only; and no recipient canonical copy is created.

## 7. `K5-P4` / Slice D — UX, drift, audit and accessibility

Complete source/recipient management/read experiences, explicit drift/revocation presentation, safe Audit/internal-outbox evidence and source-level accessibility. All K5 events remain external-webhook ineligible and invitation/private data stays out of logs/evidence.

## 8. `K5-P5` / Slice E — Privacy, retention, operations and capacity

Harden expired/used invitation cleanup, agreement history, authorization-safe caching/diagnostics, realistic-volume query gates, backup/restore behavior and retention without materializing recipient copies of source observation history.

## 9. `K5-P6` — Whole-increment acceptance

P6 must prove one complete cross-tenant seam: create invitation → same-Kingdom acceptance → explicit target selection → safe current/history read → private-field exclusion → correction/invalidation propagation → no recipient mutation/reshare/copy → immediate revoke loss of access → unrelated-tenant ID/token failure.

Whole-increment evidence must record the exact implementation SHA and protected Dependency Review, CodeQL, complete CI, migration, static-analysis, frontend/accessibility, image, staging, backup/restore and scan results.

## 10. Continuation rule

On `continue`, remain at the current K5 gate until both runtime/evidence and the exact containing status head are protected-green.

For this transition, `K5-P2` becomes writable only if the exact head containing the P1 Complete / P2 Current state passes Dependency Review, CodeQL and full CI. Otherwise remain at the P1 transition and repair only that defect.

Do not widen K5 to player/roster sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, transitive reshare, scoring/ranking or automatic decisions without a separately reviewed scope change.