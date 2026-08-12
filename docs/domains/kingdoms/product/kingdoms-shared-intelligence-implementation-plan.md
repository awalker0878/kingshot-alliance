# KINGDOMS-005 implementation plan

[← Kingdoms opt-in shared intelligence product increment](kingdoms-shared-intelligence-increment.md)

**Status:** In progress — `K5-P0` Current; no runtime implementation authorized yet  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**K5-P0 decisions:** [K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**Important:** These are implementation phases inside `KINGDOMS-005`; they are not historical Phase 0–6 or DCP phases.

## 1. Purpose

Deliver opt-in cross-Alliance sharing of selected safe game-Alliance intelligence without weakening the Alliance tenant boundary, K3 append-history/privacy rules, K4 source isolation or existing public-integration exclusions.

The plan deliberately separates consent/grant mechanics from data projection. No shared observation read path is introduced until the two-party authorization model is independently accepted.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K5-P0` | **Current** | Consent, tenancy, same-Kingdom, data-classification, revocation and reshare contract lock | Pre-runtime gate |
| `K5-P1` | Planned | Directional two-party sharing agreement foundation | Slice A |
| `K5-P2` | Planned | Explicit shared-target selection + safe current-fact recipient projection | Slice B |
| `K5-P3` | Planned | Bounded accepted shared history + freshness/correction semantics | Slice C |
| `K5-P4` | Planned | Source/recipient UX, drift/revocation, audit/events and accessibility | Slice D |
| `K5-P5` | Planned | Privacy, retention, operations and capacity hardening | Slice E |
| `K5-P6` | Planned | Whole-increment acceptance | Whole increment |

## 3. `K5-P0` — Contract lock

P0 must lock, before runtime schema/routes/UI:

- directional source→recipient ownership;
- two-party manager consent;
- invitation-token secret handling;
- same-current-Kingdom requirement;
- explicit per-target sharing rather than tenant-wide exposure;
- exact safe shared field set and excluded private field classes;
- recipient read-only/non-copy semantics;
- correction/invalidation projection behavior;
- drift/revocation fail-closed behavior;
- no transitive reshare;
- manager/member authorization split;
- internal-only audit/outbox event boundary;
- token/agreement retention principles; and
- explicit non-capabilities.

### P0 exit gate

P0 is Complete only when scope, plan, design decisions and security/privacy review agree on the same contract, navigation labels K5 as planning/no-runtime, no living runtime document claims K5 exists, and the exact containing P0 evidence/status head passes Dependency Review, CodeQL and full CI.

P0 acceptance authorizes Slice A only.

## 4. `K5-P1` / Slice A — Sharing agreement foundation

### Runtime outcome

Introduce tenant-owned directional sharing agreement persistence and manager flows for invitation creation, redemption/acceptance, decline and revocation.

### Required behavior

- source manager creates an unguessable one-time invitation secret;
- only a cryptographic hash is persisted;
- invitation has a bounded expiry and single-use semantics;
- recipient manager redeems under their active Alliance context;
- source and recipient must be different Alliances;
- both Alliances must resolve to the same current Kingdom at activation;
- accepted agreement captures that Kingdom context;
- reverse sharing requires a separate agreement;
- revocation/decline is always allowed as an access-reducing action;
- invitation/consent state is tenant-private and manager-only;
- no observation data is shared in Slice A; and
- no platform-Alliance directory/search surface is added.

### Evidence

Feature/tenant-isolation tests must cover token hashing/single-use/expiry, same-Kingdom rejection, self-share rejection, cross-tenant ID substitution, password confirmation, revocation, idempotent terminal transitions and absence of shared-data read paths.

## 5. `K5-P2` / Slice B — Explicit target selection and current facts

### Runtime outcome

Allow a source manager to select individual existing source-owned `TrackedKingdomAlliance` targets for one active share, then expose a recipient member-safe current-fact projection.

### Required behavior

- source target must belong to the source Alliance and captured Kingdom;
- only active accepted source observations participate;
- shared fields are restricted to the locked safe factual projection;
- recipient query begins from active recipient Alliance + active share authorization;
- no source manager notes/diplomacy/contacts/actors/K4 provenance leak;
- recipient cannot mutate source data;
- recipient cannot automatically create local tracking/history from the share;
- item removal immediately removes access; and
- recipient cannot reshare the item.

### Evidence

Tenant isolation must prove a recipient sees only explicitly shared targets from the bound source and that unrelated source/private data is inaccessible even when neutral `KingdomAlliance` identity is shared globally.

## 6. `K5-P3` / Slice C — Bounded history and correction semantics

### Runtime outcome

Add bounded shared accepted observation history and freshness semantics without materializing recipient-owned copies.

### Required behavior

- history is bounded/paginated;
- only accepted/non-invalidated source observations appear;
- source correction/invalidation affects recipient projection immediately;
- capture-time ordering remains authoritative;
- missing values remain distinct from zero;
- recipient receives no invalidation reason/actor;
- freshness is descriptive only;
- source ownership/provenance boundary remains explainable without exposing K4 operational provenance; and
- revoked/removed shares/items stop both current and history reads.

### Evidence

Tests cover source correction/invalidation after sharing, stale/fresh/missing projections, bounded query behavior, exact recipient authorization and no copied recipient canonical observations.

## 7. `K5-P4` / Slice D — UX, drift, audit and accessibility

### Runtime outcome

Complete first-party source/recipient management and recipient read experiences, plus fail-closed context drift and internal evidence.

### Required behavior

- source manager sees pending/active/revoked agreements and explicitly shared targets;
- recipient manager sees invitations/accepted sources and may leave/decline;
- recipient members see only the safe active shared-intelligence view;
- captured-Kingdom drift blocks access and cannot silently retarget;
- drift recovery requires a new deliberate agreement or other explicitly locked P0 recovery path;
- material consent/item changes create attributable audit/internal outbox evidence with safe metadata;
- invitation secrets/private data never enter logs/audit/outbox;
- all K5 events remain external-webhook ineligible; and
- all first-party surfaces pass source-level accessibility gates.

### Evidence

Feature/architecture/accessibility/integration tests cover both source and recipient perspectives, drift, revocation, event exclusion and private-field disclosure.

## 8. `K5-P5` / Slice E — Privacy, retention, operations and capacity

### Runtime outcome

Harden expired invitation cleanup, agreement history, read-path performance and operator diagnostics without creating shared payload replicas.

### Required behavior

- expired/used invitation secret material is removed or irreversibly unusable under bounded retention;
- revoked agreement metadata may remain for audit/history while shared observation payload access is absent;
- operator diagnostics use safe IDs/states/counts only;
- no raw/shared observation payload is copied into general logs;
- realistic-volume recipient projections remain bounded and N+1 resistant;
- revocation/drift access checks remain authoritative under concurrency; and
- backup/restore preserves consent history without restoring unauthorized recipient access.

### Evidence

Add realistic-volume query gates, retention tests, migration rollback/reapply coverage, recovery checks and focused security/privacy review.

## 9. `K5-P6` — Whole-increment acceptance

P6 must revalidate the complete cross-tenant seam in one acceptance scenario:

1. source creates an invitation;
2. recipient accepts under same-Kingdom context;
3. source explicitly shares one tracked game Alliance;
4. recipient sees only safe current/history facts;
5. unrelated source/private fields remain inaccessible;
6. source correction/invalidation changes the recipient projection without copying data;
7. recipient cannot mutate or reshare source intelligence;
8. revocation removes access immediately while consent audit evidence remains; and
9. another Alliance cannot use the same invitation/agreement/item IDs to cross the boundary.

Whole-increment evidence must include exact implementation SHA plus Dependency Review, CodeQL, complete CI, migrations, static analysis, full tests, frontend/accessibility, immutable image, staging, backup/restore and vulnerability scan.

## 10. Continuation rule

On `continue`, remain at the current K5 gate until both implementation/evidence and the exact containing status head are protected-green.

Do not start a later slice to compensate for a current-slice defect. Do not widen K5 to player/roster sharing, diplomacy/contact sharing, cross-Kingdom sharing, public APIs/webhooks, reshare, scoring or automatic decisions without a separately reviewed scope change.