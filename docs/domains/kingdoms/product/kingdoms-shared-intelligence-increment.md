# Kingdoms opt-in shared intelligence product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** **Accepted** — `K5-P0`–`K5-P6` Complete  
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
**Slice E validation:** [K5-P5 validation](kingdoms-shared-intelligence-slice-e-validation.md)  
**Whole-increment exit:** [K5-P6 exit report](kingdoms-shared-intelligence-exit-report.md)

## 1. Purpose

`KINGDOMS-005` introduces explicit, revocable sharing of selected safe Kingdom game-Alliance observation facts between two platform Alliances operating in the same Kingdom, while preserving K1–K4 tenant ownership, K3 history/privacy rules and K4 source isolation.

Sharing is a deliberately authorized source-owned projection. It is not tenant federation, a public directory, bulk export, automatic diplomacy or a new acquisition channel.

## 2. Current governed state

`KINGDOMS-005` is **Accepted**. `K5-P0` through `K5-P6` are Complete.

Whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`: Pint 560 files, PHPStan/Larastan 394/394 zero errors, 452 tests / 10,322 assertions, frontend lint/format/type/build, clean migrations, immutable image, staging, backup/restore, scan and cleanup.

Accepted runtime includes:

- directional two-party consent/agreement state;
- explicit per-target grant/removal state;
- bounded recipient-safe current facts;
- bounded accepted history for one explicit target using encrypted target-bound continuation cursors;
- member-safe first-party current/history presentation;
- manager-only first-party invitation/agreement/grant management;
- immediate erasure of persisted invitation hashes when invitations are accepted, declined or revoked;
- bounded scheduled cleanup of eligible old K5 operational consent/grant rows while preserving active/canonical/Audit/outbox state;
- realistic-volume current/history read evidence without a new cache/materialization boundary; and
- whole-increment acceptance evidence proving unrelated-tenant failure, correction-safe projection, no copy/reshare/mutation and immediate fail-closed authorization loss.

The accepted behavior and exact evidence are recorded in the [whole-increment exit report](kingdoms-shared-intelligence-exit-report.md).

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
- current facts are bounded to 250 rows;
- history is bounded to 50-row pages and 250 accepted observations per traversal;
- revocation, target removal or context invalidation removes recipient access;
- supported Kingdom drift permanently terminalizes affected agreements rather than silently retargeting/reactivating them; and
- operational retention can remove only eligible old pending/terminal/removed K5 metadata, never active shares/grants or canonical source history.

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

P5 proves the accepted current/history limits at larger fixture volume: 300 active grants still yield only 250 current facts within two SELECTs, and 1,000 source observations still yield only five 50-row history pages / 250 accepted observations within two SELECTs per page. Recipient canonical observation count remains zero.

P6 composes the accepted seams end to end and re-proves safe current/history presentation, correction/invalidation propagation, unrelated-tenant denial, no copy/reshare/mutation and immediate access loss after target removal or share revocation.

## 6. Retention and operational behavior

P5 adds `EnforceKingdomIntelligenceSharingRetention` for old K5 operational metadata only.

One invocation has one total work budget, default 500 and clamped to 1–2000. It processes, in order:

1. pending invitations older than 30 days after expiry by default;
2. declined/revoked agreements older than 180 days by default; then
3. removed target grants older than 90 days by default.

The delete statement repeats state/cutoff eligibility so stale candidate IDs cannot delete a row that became active/re-granted/non-eligible before deletion.

Active agreements/grants, source tracking/observations, Audit events and outbox messages are never eligible through this action. The command `kingdoms:enforce-sharing-retention --limit=500` runs daily at 04:30 on one server without overlap.

Immediate invitation-hash erasure remains P4 runtime behavior and is not deferred to retention.

P6 re-proves terminal operational cleanup while preserving canonical observations and durable revoke Audit/outbox evidence, followed by an idempotent no-op retention run.

## 7. Data excluded from sharing

K5 does not share player roster/snapshots; transfer state; diplomacy terms/history; diplomacy contacts/handles/notes; tracking notes; source tracking IDs; stable game IDs; observation IDs; correction/invalidation reasons/actors/linkage; K4 adapter/subscription/batch/candidate/cursor/source provenance; raw responses/secrets; Audit internals; private free text; scores/rankings/recommendations; or automatic decisions.

History cursors are opaque continuation state, not data-sharing credentials, and must not become public reusable access tokens.

Manager page props likewise exclude invitation hashes, observation payloads, private source notes/actors/governance metadata and K4 source provenance.

## 8. Same-Kingdom, ownership, non-copy and no-reshare rules

An agreement captures one Kingdom. Every current/history read revalidates participant Alliances plus source tracking context.

The supported Alliance→Kingdom mutation terminalizes affected active agreements and source pending invitations. Leaving and later returning to the captured Kingdom cannot resume an old share; a new collaboration requires a new invitation/agreement.

Source `TrackedKingdomAlliance`/`KingdomAllianceObservation` state remains source-owned. Recipient reads do not create local tracking or observation rows.

A recipient cannot use received source tracking/grant/history as the upstream target of its own outbound K5 share. K5 remains non-transitive.

Retention does not change those ownership rules and never creates/reactivates sharing state.

## 9. Public integration and presentation boundary

K5 remains first-party/internal. Consent and target mutations are authenticated active-Alliance routes under the accepted authorization/password rules. Current/history projections are exposed only through authenticated first-party presentation; no public recipient data API exists.

There is no public Alliance directory, public Kingdoms API scope, inbound sharing callback, anonymous feed or external webhook schema. All K5 events remain `kingdoms.*` internal-only.

The first-party history UI uses only opaque target-bound continuation cursors and does not expose an arbitrary client-controlled history `asOf` control that could repeatedly reopen progressively older 250-record windows.

The P5 retention command is an internal operator surface, not a public integration contract.

## 10. Delivery slices

- `K5-P0` — **Complete**: contract lock.
- `K5-P1` / Slice A — **Complete**: invitation/agreement consent foundation.
- `K5-P2` / Slice B — **Complete**: explicit target grants + bounded safe current-fact projection.
- `K5-P3` / Slice C — **Complete**: bounded accepted history + correction/invalidation projection semantics.
- `K5-P4` / Slice D — **Complete**: first-party source/recipient UX, safe page-prop boundary, invitation lifecycle hardening and accessibility.
- `K5-P5` / Slice E — **Complete**: bounded operational retention + realistic-volume current/history capacity hardening.
- `K5-P6` — **Complete**: whole-increment acceptance.

## 11. Explicitly out of scope

No player/roster sharing, transfer sharing/automation, diplomacy/contact sharing/automation, public tenant/contact directory, cross-Kingdom sharing, transitive reshare, anonymous/global feed, public API/webhook sharing, source acquisition/scraping/OCR/bots, arbitrary tenant export, scoring/ranking/prediction/recommendation or AI-generated management decision is approved.

Repository/product acceptance does not itself approve production policy changes or a broader public integration surface.

## 12. Acceptance rule

`KINGDOMS-005` is accepted only on the exact whole-increment candidate and evidence recorded in the [exit report](kingdoms-shared-intelligence-exit-report.md).

Future changes must preserve the accepted P0–P6 boundary. Any widening of data classes, tenant scope, public interfaces, materialization/caching authorization, reshare or decision automation requires separate review and acceptance.
