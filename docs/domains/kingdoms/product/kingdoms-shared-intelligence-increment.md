# Kingdoms opt-in shared intelligence product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** In progress — `K5-P0`–`K5-P1` Complete; `K5-P2` selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline dependency:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**Implementation sequence:** [KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)  
**P0 decisions:** [KINGDOMS-005 K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**P0 exit:** [KINGDOMS-005 K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)

## 1. Purpose

`KINGDOMS-005` introduces explicit, revocable sharing of selected safe Kingdom game-Alliance observation facts between two platform Alliances operating in the same Kingdom, while preserving K1–K4 tenant ownership, K3 history/privacy rules and K4 source isolation.

Sharing is a deliberately authorized read path over source-owned canonical facts. It is not tenant federation, a public directory, bulk export, automatic diplomacy or a new acquisition channel.

## 2. Current governed state

`K5-P0` and `K5-P1` are Complete.

P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions, frontend/build, clean migrations, immutable image, staging, backup/restore and scan.

The runtime now contains a **consent foundation only**: directional source→recipient invitation/agreement persistence plus accept/decline/revoke/leave actions. It still contains no shared-target table and no recipient observation/current/history read path.

`K5-P2` / Slice B is selected next, but actual data-sharing implementation may begin only after the exact P1 Complete / P2 Current transition head is independently protected-green.

## 3. Initial product boundary

The increment remains intentionally narrow:

- one source platform Alliance and one different recipient platform Alliance;
- same captured/current Kingdom;
- directional sharing; reverse direction requires another agreement;
- two-party manager opt-in;
- active agreement alone shares nothing;
- source explicitly selects each tracked game-side Alliance in P2 or later;
- recipient reads are read-only projections of source-owned accepted observations; and
- revocation/context invalidation removes access without copying source history into recipient-owned canonical tables.

## 4. Current P1 consent behavior

Source managers can create one-time invitations using `kingdoms.manage` plus recent password confirmation. Tokens use 32 cryptographically random bytes encoded as 64 lowercase hexadecimal characters; only SHA-256 hashes are persisted. Default expiry is 72 hours, repository-bounded to 1–168 hours.

Recipient managers may accept or decline under their own active Alliance context. Acceptance requires a different Alliance, matching current/captured Kingdom and no duplicate active directional agreement. Successful acceptance consumes the token. Source revoke and recipient leave are tenant-scoped terminal access-reducing actions and remain available after Kingdom drift.

Consent Audit/outbox evidence carries safe IDs/state/timestamps only. The source-side acceptance Audit record uses a null actor so the recipient manager's global User ID is not unnecessarily exposed cross-tenant.

## 5. Shared data allowed only in later accepted slices

P2 or later may expose only bounded member-safe factual game-Alliance information:

- neutral/current game-Alliance display name/tag needed for context;
- accepted observed name/tag;
- accepted power/member count when present;
- capture time;
- bounded freshness/current-stale-missing state; and
- source platform Alliance identity needed for explainability.

P1 exposes none of these facts to the recipient.

## 6. Data excluded from sharing

K5 does not share player roster/snapshots; transfer state; diplomacy terms/history; diplomacy contacts/handles/notes; tracking notes; correction/invalidation reasons/actors; K4 adapter/subscription/batch/candidate/cursor/source provenance; raw responses/secrets; audit internals; private free text; scores/rankings/recommendations; or automatic decisions.

## 7. Same-Kingdom, ownership and non-copy rules

An agreement captures one Kingdom. Later data reads must revalidate source/recipient current Kingdom plus the selected source target's Kingdom. Drift fails closed and does not silently retarget/reactivate an agreement.

Source canonical `TrackedKingdomAlliance`/`KingdomAllianceObservation` state remains source-owned. The recipient cannot edit/invalidate source facts, mutate source tracking/diplomacy/contact state, invoke K4 acquisition/replay, auto-create local tracking/history, reshare source facts, or automatically copy them into recipient canonical observation history.

## 8. Public integration boundary

K5 remains first-party only. Consent mutations are authenticated active-Alliance routes under recent password confirmation. There is no public Alliance directory, public Kingdoms API scope, inbound sharing endpoint, anonymous feed or external webhook schema.

All K5 events remain `kingdoms.*` internal-only under the existing Integrations exclusion.

## 9. Delivery slices

- `K5-P0` — **Complete**: contract lock.
- `K5-P1` / Slice A — **Complete**: hash-only invitation/agreement consent foundation; no observation sharing.
- `K5-P2` / Slice B — **Current / selected pending transition-head validation**: explicit source target selection + safe recipient current-fact projection.
- `K5-P3` / Slice C — Planned: bounded accepted history + freshness/correction semantics.
- `K5-P4` / Slice D — Planned: source/recipient UX, drift/revocation, Audit/events/accessibility.
- `K5-P5` / Slice E — Planned: privacy/retention/operations/capacity.
- `K5-P6` — Planned: whole-increment acceptance.

## 10. Explicitly out of scope

No player/roster sharing, transfer sharing/automation, diplomacy/contact sharing/automation, public tenant/contact directory, cross-Kingdom sharing, transitive reshare, anonymous/global feed, public API/webhook sharing, source acquisition/scraping/OCR/bots, arbitrary tenant export, scoring/ranking/prediction/recommendation or AI-generated management decision is approved.

## 11. Acceptance rule

Every slice must preserve the P0 contract and pass its implementation/evidence protected gates. P2 is the first slice allowed to disclose a shared observation projection and must not begin until the exact containing P1 Complete / P2 Current head passes Dependency Review, CodeQL and full CI.

Whole-increment acceptance at K5-P6 remains repository/product acceptance; production deployment/cutover remains separately governed.