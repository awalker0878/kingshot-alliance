# Kingdoms opt-in shared intelligence product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** In progress — `K5-P0`–`K5-P2` Complete; `K5-P3` selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline dependency:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**Implementation sequence:** [KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)  
**P0 decisions:** [KINGDOMS-005 K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**P0 exit:** [KINGDOMS-005 K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Slice B validation:** [K5-P2 validation](kingdoms-shared-intelligence-slice-b-validation.md)

## 1. Purpose

`KINGDOMS-005` introduces explicit, revocable sharing of selected safe Kingdom game-Alliance observation facts between two platform Alliances operating in the same Kingdom, while preserving K1–K4 tenant ownership, K3 history/privacy rules and K4 source isolation.

Sharing is a deliberately authorized source-owned projection. It is not tenant federation, a public directory, bulk export, automatic diplomacy or a new acquisition channel.

## 2. Current governed state

`K5-P0`, `K5-P1`, and `K5-P2` are Complete.

P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint 550 files, PHPStan/Larastan 390/390 zero errors, 440 tests / 10,025 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

Current runtime includes:

- directional two-party consent/agreement state;
- explicit per-target grant/removal state; and
- an internal recipient-safe current-fact projection over source-owned accepted observations.

Current runtime still has **no bounded shared observation history** and no complete source/recipient K5 page set. `K5-P3` / Slice C is selected next but cannot begin until the exact P2 Complete / P3 Current containing head is independently protected-green.

## 3. Product boundary

The increment remains intentionally narrow:

- one source platform Alliance and one different recipient platform Alliance;
- same captured/current Kingdom;
- directional sharing; reverse direction requires another agreement;
- two-party manager opt-in;
- active agreement alone shares nothing;
- source explicitly selects each tracked game-side Alliance;
- recipient reads are read-only projections of source-owned accepted observations;
- source observations remain canonical and are not copied into recipient history;
- revocation, target removal or context invalidation removes recipient access; and
- supported Kingdom drift permanently terminalizes affected agreements rather than silently retargeting/reactivating them.

## 4. Consent behavior

Source managers create one-time invitations using `kingdoms.manage` plus recent password confirmation. Tokens use 32 cryptographically random bytes encoded as 64 lowercase hexadecimal characters; only SHA-256 hashes are persisted. Default expiry is 72 hours, repository-bounded to 1–168 hours.

Recipient managers accept or decline under their own active Alliance. Acceptance requires a different Alliance, matching current/captured Kingdom and no duplicate active directional agreement. Successful acceptance consumes the token. Source revoke and recipient leave are tenant-scoped terminal access-reducing actions.

Consent Audit/outbox evidence carries safe IDs/state/timestamps only; cross-tenant counterpart records use null actors where needed to avoid manager User-ID disclosure.

## 5. Explicit target and current-fact behavior

An active agreement exposes no observation data until the source explicitly grants one active source-owned `TrackedKingdomAlliance`.

P2 current reads authorize from the recipient Alliance through the active agreement and active explicit grant, then verify source/recipient current Kingdom, source tracking ownership/state and captured Kingdom.

The bounded current projection exposes only:

- source platform Alliance ID/name;
- neutral/current game-Alliance name/tag;
- latest accepted observed name/tag;
- optional power/member count;
- capture time; and
- K3-consistent `current|stale|missing` freshness.

Latest accepted observation selection excludes invalidated facts and follows K3 `captured_at DESC, id DESC` ordering. Source invalidation immediately changes the recipient projection without copying correction reasons/actors.

## 6. Data excluded from sharing

K5 does not share player roster/snapshots; transfer state; diplomacy terms/history; diplomacy contacts/handles/notes; tracking notes; source tracking IDs; stable game IDs in the P2 recipient payload; correction/invalidation reasons/actors; K4 adapter/subscription/batch/candidate/cursor/source provenance; raw responses/secrets; Audit internals; private free text; scores/rankings/recommendations; or automatic decisions.

## 7. Same-Kingdom, ownership, non-copy and no-reshare rules

An agreement captures one Kingdom. Current reads revalidate both participant Alliances plus source tracking context.

The supported Alliance→Kingdom mutation terminalizes affected active agreements and source pending invitations. Leaving and later returning to the captured Kingdom cannot resume an old share; a new collaboration requires a new invitation/agreement.

Source `TrackedKingdomAlliance`/`KingdomAllianceObservation` state remains source-owned. Recipient reads do not create local tracking or observation rows.

A recipient cannot use a received source tracking/grant as the upstream target of its own outbound K5 share because target grants resolve tracking beneath the outbound source Alliance. K5 remains non-transitive.

## 8. Public integration boundary

K5 remains first-party/internal. Consent and target mutations are authenticated active-Alliance routes under recent password confirmation. The current safe recipient projection is an internal query; no public recipient data API is introduced.

There is no public Alliance directory, public Kingdoms API scope, inbound sharing callback, anonymous feed or external webhook schema. All K5 events remain `kingdoms.*` internal-only.

## 9. Delivery slices

- `K5-P0` — **Complete**: contract lock.
- `K5-P1` / Slice A — **Complete**: invitation/agreement consent foundation.
- `K5-P2` / Slice B — **Complete**: explicit target grants + bounded safe current-fact projection.
- `K5-P3` / Slice C — **Current / selected pending transition-head validation**: bounded accepted history + correction/invalidation projection semantics.
- `K5-P4` / Slice D — Planned: complete source/recipient UX, drift/revocation presentation, Audit/events/accessibility.
- `K5-P5` / Slice E — Planned: privacy/retention/operations/capacity hardening.
- `K5-P6` — Planned: whole-increment acceptance.

## 10. Explicitly out of scope

No player/roster sharing, transfer sharing/automation, diplomacy/contact sharing/automation, public tenant/contact directory, cross-Kingdom sharing, transitive reshare, anonymous/global feed, public API/webhook sharing, source acquisition/scraping/OCR/bots, arbitrary tenant export, scoring/ranking/prediction/recommendation or AI-generated management decision is approved.

P3 does not become runtime-authorized merely because it is selected.

## 11. Acceptance rule

Every slice must preserve the P0 contract and pass both its runtime candidate gate and the exact containing evidence/status transition gate.

P3 may begin only after the exact head recording P2 Complete / P3 Current passes Dependency Review, CodeQL and full CI. Whole-increment acceptance at K5-P6 remains repository/product acceptance; production deployment/cutover remains separately governed.