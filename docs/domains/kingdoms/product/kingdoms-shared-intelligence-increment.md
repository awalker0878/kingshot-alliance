# Kingdoms opt-in shared intelligence product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** In progress — `K5-P0`–`K5-P4` Complete; `K5-P5` selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline dependency:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**Implementation sequence:** [KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)  
**P0 decisions:** [KINGDOMS-005 K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**P0 exit:** [KINGDOMS-005 K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)  
**Slice A validation:** [K5-P1 validation](kingdoms-shared-intelligence-slice-a-validation.md)  
**Slice B validation:** [K5-P2 validation](kingdoms-shared-intelligence-slice-b-validation.md)  
**Slice C validation:** [K5-P3 validation](kingdoms-shared-intelligence-slice-c-validation.md)  
**Slice D validation:** [K5-P4 validation](kingdoms-shared-intelligence-slice-d-validation.md)

## 1. Purpose

`KINGDOMS-005` introduces explicit, revocable sharing of selected safe Kingdom game-Alliance observation facts between two platform Alliances operating in the same Kingdom, while preserving K1–K4 tenant ownership, K3 history/privacy rules and K4 source isolation.

Sharing is a deliberately authorized source-owned projection. It is not tenant federation, a public directory, bulk export, automatic diplomacy or a new acquisition channel.

## 2. Current governed state

`K5-P0` through `K5-P4` are Complete.

P4 runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed Dependency Review `31569202741`, CodeQL `31569202422`, and CI `31569202418`: Pint 556 files, PHPStan/Larastan 393/393 zero errors, 448 tests / 10,160 assertions, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

Current runtime includes:

- directional two-party consent/agreement state;
- explicit per-target grant/removal state;
- bounded recipient-safe current facts;
- bounded accepted history for one explicit target using encrypted target-bound continuation cursors;
- member-safe first-party current/history presentation;
- manager-only first-party invitation/agreement/grant management; and
- immediate erasure of persisted invitation hashes when invitations are accepted, declined or revoked.

`K5-P5` / Slice E is selected next for invitation/grant retention operations and realistic-volume current/history capacity hardening, but runtime P5 work cannot begin until the exact P4 Complete / P5 Current containing head is independently protected-green.

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
- current facts are bounded;
- history is bounded to 50-row pages and 250 accepted observations per traversal;
- revocation, target removal or context invalidation removes recipient access; and
- supported Kingdom drift permanently terminalizes affected agreements rather than silently retargeting/reactivating them.

## 4. Consent behavior

Source managers create one-time invitations using `kingdoms.manage` plus recent password confirmation. Tokens use 32 cryptographically random bytes encoded as 64 lowercase hexadecimal characters; only SHA-256 hashes are persisted while invitations remain pending. Default expiry is 72 hours, repository-bounded to 1–168 hours.

Recipient managers accept or decline under their own active Alliance. Acceptance requires a different Alliance, matching current/captured Kingdom and no duplicate active directional agreement. Successful acceptance consumes the token. Accept, decline and revoke erase the persisted invitation hash; recipient leave occurs only after acceptance and therefore has no invitation hash to retain. Source revoke and recipient leave are tenant-scoped terminal access-reducing actions.

The accepted P1 migration remains unchanged. P4 adds a forward nullable-hash migration so consumed/terminal hashes can be erased. Its rollback uses deterministic per-share retired placeholders solely to satisfy the historical non-null schema and its reapply path converts those terminal placeholders back to null without reconstructing an invitation credential.

Consent Audit/outbox evidence carries safe IDs/state/timestamps only; cross-tenant counterpart records use null actors where needed to avoid manager User-ID disclosure.

## 5. Explicit target, current-fact, history and presentation behavior

An active agreement exposes no observation data until the source explicitly grants one active source-owned `TrackedKingdomAlliance`.

Current/history reads authorize from the active recipient Alliance through an active agreement and active explicit grant, then verify source/recipient current Kingdom, source tracking ownership/state and captured Kingdom.

Current facts expose source Alliance ID/name, neutral/current game-Alliance name/tag, latest accepted observed name/tag, optional power/member count, capture time and K3-consistent `current|stale|missing` freshness.

History exposes only safe accepted observation values plus capture time and descriptive `current|stale` freshness. It uses deterministic `captured_at DESC, id DESC` ordering, 50-row maximum pages, a hard 250-observation traversal cap, and an encrypted continuation cursor bound to the target/fixed history snapshot.

Source invalidation removes invalidated facts from both current and history projections without exposing correction/invalidation reasons or actors. Corrected replacements appear only as their own accepted source observations.

P4 exposes these accepted projections through a member-safe Inertia page and exposes consent/grant operations through a manager-only workspace. The recipient page accepts only an explicit target plus opaque server-issued continuation cursor; it exposes no arbitrary historical `asOf` selector or equivalent older-window control.

Invitation plaintext is returned only by the authenticated creation POST and held only in component memory; it is never an Inertia/session prop.

## 6. Data excluded from sharing

K5 does not share player roster/snapshots; transfer state; diplomacy terms/history; diplomacy contacts/handles/notes; tracking notes; source tracking IDs; stable game IDs; observation IDs; correction/invalidation reasons/actors/linkage; K4 adapter/subscription/batch/candidate/cursor/source provenance; raw responses/secrets; Audit internals; private free text; scores/rankings/recommendations; or automatic decisions.

History cursors are opaque continuation state, not data-sharing credentials, and must not become public reusable access tokens.

Manager page props likewise exclude invitation hashes, observation payloads, private source notes/actors/governance metadata and K4 source provenance.

## 7. Same-Kingdom, ownership, non-copy and no-reshare rules

An agreement captures one Kingdom. Every current/history read revalidates participant Alliances plus source tracking context.

The supported Alliance→Kingdom mutation terminalizes affected active agreements and source pending invitations. Leaving and later returning to the captured Kingdom cannot resume an old share; a new collaboration requires a new invitation/agreement.

Source `TrackedKingdomAlliance`/`KingdomAllianceObservation` state remains source-owned. Recipient reads do not create local tracking or observation rows.

A recipient cannot use received source tracking/grant/history as the upstream target of its own outbound K5 share. K5 remains non-transitive.

## 8. Public integration and presentation boundary

K5 remains first-party/internal. Consent and target mutations are authenticated active-Alliance routes under the accepted authorization/password rules. Current/history projections are exposed only through authenticated first-party presentation; no public recipient data API exists.

There is no public Alliance directory, public Kingdoms API scope, inbound sharing callback, anonymous feed or external webhook schema. All K5 events remain `kingdoms.*` internal-only.

The first-party history UI uses only opaque target-bound continuation cursors and does not expose an arbitrary client-controlled history `asOf` control that could repeatedly reopen progressively older 250-record windows.

## 9. Delivery slices

- `K5-P0` — **Complete**: contract lock.
- `K5-P1` / Slice A — **Complete**: invitation/agreement consent foundation.
- `K5-P2` / Slice B — **Complete**: explicit target grants + bounded safe current-fact projection.
- `K5-P3` / Slice C — **Complete**: bounded accepted history + correction/invalidation projection semantics.
- `K5-P4` / Slice D — **Complete**: first-party source/recipient UX, safe page-prop boundary, invitation lifecycle hardening and accessibility.
- `K5-P5` / Slice E — **Current / selected pending transition-head validation**: privacy/retention/operations/capacity hardening.
- `K5-P6` — Planned: whole-increment acceptance.

## 10. Explicitly out of scope

No player/roster sharing, transfer sharing/automation, diplomacy/contact sharing/automation, public tenant/contact directory, cross-Kingdom sharing, transitive reshare, anonymous/global feed, public API/webhook sharing, source acquisition/scraping/OCR/bots, arbitrary tenant export, scoring/ranking/prediction/recommendation or AI-generated management decision is approved.

P5 does not become runtime-authorized merely because it is selected.

## 11. Acceptance rule

Every slice must preserve the P0 contract and pass both its runtime candidate gate and the exact containing evidence/status transition gate.

P5 may begin only after the exact head recording P4 Complete / P5 Current passes Dependency Review, CodeQL and full CI/recovery. Whole-increment acceptance at K5-P6 remains repository/product acceptance; production deployment/cutover remains separately governed.
