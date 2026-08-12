# KINGDOMS-005 opt-in shared Kingdom intelligence exit report

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Scope:** `KINGDOMS-005`  
**Acceptance phase:** `K5-P6` — whole-increment acceptance  
**Status:** **Accepted**  
**Validated implementation SHA:** `6f84b51ab27941f0fec2abce71f1f2f6325560e4`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` through `KINGDOMS-004`

## 1. Acceptance decision

`KINGDOMS-005` is **Accepted** as a repository/product capability for directional, two-party opt-in sharing of explicitly selected safe game-Alliance intelligence between two platform Alliances operating in the same current/captured Kingdom.

The accepted increment provides hash-only one-time invitation consent; explicit per-target grants; bounded safe current facts; bounded accepted source history; correction/invalidation propagation; complete first-party member/manager presentation; immediate fail-closed authorization loss; bounded operational retention; realistic-volume capacity evidence; and whole-seam tenant-isolation/no-copy/no-reshare proof.

Acceptance does **not** approve player/roster sharing, transfer sharing/automation, diplomacy/contact sharing, cross-Kingdom sharing, tenant-directory discovery, transitive reshare, public API/webhook sharing, arbitrary historical-window reopening, scoring/ranking/recommendations, automatic decisions, recipient canonical copies, or recipient mutation of source facts.

## 2. Accepted consent and one-time-secret boundary

The whole-increment review confirms:

- sharing is directional; reverse sharing requires another agreement;
- source and recipient must be different platform Alliances;
- activation requires both Alliances' current Kingdoms to equal the captured invitation Kingdom;
- source invitation creation requires the accepted manager/password-confirmed boundary;
- invitations use 32 cryptographically random bytes encoded as 64 lowercase hexadecimal characters;
- only the SHA-256 token hash is persisted while the invitation remains pending;
- plaintext is returned once through the authenticated creation response and may exist only in first-party component memory;
- acceptance is single-use and cannot be replayed;
- accept, decline and revoke erase the persisted invitation hash immediately; and
- retention is not delayed secret cleanup.

The P6 acceptance test starts with a pending invitation, proves the persisted hash corresponds to the issued one-time token, accepts it, and verifies the agreement becomes Active with the hash cleared before any target is granted.

## 3. Accepted explicit-target and current-fact boundary

An active agreement alone exposes no observation data. Source managers must explicitly grant each active source-owned `TrackedKingdomAlliance`.

Current reads re-authorize from active recipient Alliance → active agreement → active explicit target grant → active source-owned tracking/context. Neutral game-Alliance identity alone grants no access.

The safe current projection remains bounded to 250 rows and exposes only:

- opaque share-target ID;
- source Alliance `{id,name}`;
- neutral/current game-Alliance `{name,tag}`;
- `current|stale|missing` freshness; and
- latest accepted observed `{observedName,observedTag,power,memberCount,capturedAt}` or null.

Source tracking IDs, stable game IDs, observation IDs, manager notes, correction/invalidation metadata, actors, K4 provenance and private free text remain excluded.

The P6 acceptance test proves no current visibility before the explicit grant, one safe projection after the grant, zero current rows for an unrelated same-Kingdom Alliance, and immediate loss after target removal/revocation.

## 4. Accepted bounded history and correction/invalidation behavior

Shared history is a source-owned accepted-observation projection, not a recipient copy.

Accepted behavior remains:

- deterministic `captured_at DESC, id DESC` ordering;
- accepted/non-invalidated observations only;
- page size capped at 50;
- one traversal capped at 250 accepted observations;
- encrypted/authenticated target-bound continuation cursor;
- fixed internal history snapshot across continuation pages;
- live recipient/share/grant/source-context authorization repeated on every page;
- no arbitrary client-visible `asOf`, offset, observation ID or unrestricted historical export control;
- safe history item fields only; and
- source correction/invalidation removes the invalidated original while an accepted replacement appears only as its own observation.

The P6 acceptance test records an original observation, a corrected replacement carrying private correction reason, and a later accepted observation. It verifies shared history includes only the later accepted rows, excludes the invalidated original/private correction metadata/IDs, and denies the unrelated Alliance.

## 5. Accepted first-party presentation and accessibility boundary

K5 exposes only authenticated first-party presentation:

- `Alliance/KingdomSharing` — member-safe current facts and optional bounded history; and
- `Alliance/KingdomSharingManage` — manager-only invitation/agreement/grant management.

History navigation accepts only an explicit target plus opaque server-issued cursor. An arbitrary request `asOf` parameter is ignored and never becomes a page-prop contract.

Member page props exclude manager sharing state, invitation secret material, private tracking/correction fields and K4 provenance. Manager page props expose bounded consent/grant/display metadata but not invitation hashes/plaintext, observation payloads, manager notes, correction metadata, actors or K4 source internals.

Accepted P4 accessibility evidence remains in force: semantic main/heading structure, native controls, labels, captions, table-overflow handling and status/alert semantics are architecture-tested.

The P6 acceptance test exercises both member and manager first-party pages and re-proves the safe prop boundary end to end.

## 6. Same-Kingdom drift, removal, revoke and fail-closed behavior

Agreement/grant validity is rechecked against current participant/tracking context rather than assumed from historical acceptance.

The supported Alliance→Kingdom mutation terminalizes affected active agreements/source pending invitations in the same transaction. Returning later to the captured Kingdom does not reactivate the old agreement.

Target removal immediately removes current/history visibility. Deliberate re-grant may restore access only while the underlying agreement/context remains active. Source revoke or recipient leave terminalizes the relationship and removes access immediately.

A stale otherwise-valid history cursor cannot bypass any later authorization loss because every page repeats live authorization.

P6 explicitly removes and re-grants a target, then revokes the share and proves current becomes empty and history fails closed immediately.

## 7. Tenant isolation, no-copy, no-reshare and no-mutation review

The whole-increment boundary remains recipient-first and non-transitive:

- source tracking/observations remain source-owned canonical rows;
- recipient reads create zero recipient `TrackedKingdomAlliance` rows;
- recipient reads create zero recipient `KingdomAllianceObservation` rows;
- an unrelated Alliance receives no current facts and cannot resolve history for the target;
- recipient/unrelated managers cannot mutate the source-owned share/target by substituting source IDs into their own tenant context; and
- received intelligence cannot become an upstream target for another outbound K5 share.

The P6 acceptance test creates source, recipient and unrelated same-Kingdom Alliances, proves zero recipient/unrelated canonical copies, and proves source target mutation attempts from recipient/unrelated tenant contexts return not found.

## 8. Retention, privacy and durable-evidence independence

Accepted P5 operational retention uses one total per-run work budget, default 500 and clamped to 1–2000.

Eligible cleanup order is:

1. pending invitations older than the configured post-expiry window;
2. declined/revoked agreements older than the configured terminal window; then
3. removed target grants older than the configured removal window.

Repository defaults remain 30 days after invitation expiry, 180 days for terminal agreements and 90 days for removed grants.

Candidate IDs are not unconditional delete authority: destructive statements repeat state/cutoff predicates, preserving a row that became non-eligible before deletion.

Active shares/grants and canonical source tracking/observations are never retention-eligible. Audit/outbox business evidence remains outside K5 operational cleanup.

The P6 acceptance test ages the revoked agreement under a shortened test-only retention window, deletes the terminal operational agreement/target, proves all original/corrected/latest canonical source observations survive, proves revoke Audit/outbox evidence survives, and proves the next retention run is an idempotent no-op.

## 9. Security, privacy and explicit non-capabilities

Whole-increment acceptance confirms K5 does not introduce:

- public/anonymous shared-intelligence routes;
- public API/webhook sharing;
- tenant directory/search;
- cross-Kingdom sharing;
- player/roster/snapshot sharing;
- transfer sharing/automation;
- diplomacy/contact sharing;
- private manager/source notes or K4 provenance disclosure;
- recipient canonical intelligence materialization;
- recipient mutation/reshare of source facts;
- arbitrary historical-window reopening;
- score/rank/threat/desirability/recommendation output; or
- automatic management decisions.

All K5 business events remain internal `kingdoms.*` events and external-webhook ineligible. Invitation plaintext/hash material, history cursors and shared observation payload bodies are excluded from normal Audit/outbox/logging contracts.

P5/P6 introduce no authorization cache/materialized recipient read store; live authorization remains the only recipient read gate.

## 10. Query, response and capacity hardening review

Accepted P5 realistic-volume evidence proves:

- 300 active explicit grants still return exactly the 250-row current cap;
- current projection uses no more than two SELECTs;
- reviewed encoded current fixture remains at or below 160,000 bytes;
- 1,000 source observations still expose only five 50-row history pages / 250 accepted observations;
- each history page uses no more than two SELECTs, exactly 10 across the five-page fixture;
- reviewed encoded history page remains at or below 50,000 bytes; and
- recipient canonical observation count remains zero.

Manager query bounds remain 100 outbound agreements, 100 inbound agreements and 250 active source-owned trackable targets.

Retention is independently bounded to one total 1–2000 rows/run.

These values are repository regression/capacity evidence, not production concurrency/latency/availability SLOs.

## 11. Migration, backup and recovery review

The accepted K5 migration chain includes:

1. `010000` sharing agreement/invitation foundation;
2. `020000` explicit share-target grants; and
3. `030000` forward nullable invitation-hash repair.

P4 deliberately did not rewrite accepted P1 history. `030000` permits consumed/terminal hashes to be erased. Its rollback uses deterministic per-share retired placeholders solely to satisfy the historical non-null schema; reapply recognizes terminal placeholders and restores null without reconstructing an invitation credential.

Focused migration tests preserve dependency order and terminal state/recipient binding through down→up round trip. Full CI applies the complete PostgreSQL schema from clean state.

P5 adds no migration. Shared immutable-image, ephemeral-staging and backup/restore gates remain part of P6 acceptance. After restore, live agreement/grant/Kingdom/tracking authorization remains authoritative; restored old operational rows may be reprocessed only through bounded retention after normal restore validation.

## 12. Exact protected validation evidence

Exact validated whole-increment implementation SHA:

`6f84b51ab27941f0fec2abce71f1f2f6325560e4`

Protected runs:

- Dependency Review `31573301975` — **success**;
- CodeQL `31573301988` — **success**;
- CI `31573301977` — **success**.

CI evidence:

- PHP 8.5.9;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations — success through `2026_08_12_030000_make_kingdom_intelligence_share_invitation_hash_nullable`;
- Pint — **560 files**;
- PHPStan/Larastan — **394/394, 0 errors**;
- ParaTest/PHPUnit — **452 tests, 10,322 assertions**;
- whole-increment `KingdomSharedIntelligenceAcceptanceTest` — included and passing;
- frontend dependency audit — success;
- ESLint/locked Prettier/Tailwind/Vue-TypeScript — success;
- production frontend build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

The immediately preceding P5→P6 transition head `0eb0444ee195fd3a09c9c9c07cdb2b1ddcb92873` independently passed Dependency Review `31572748561`, CodeQL `31572748558`, and CI `31572748595`, proving the governed P5 Complete / P6 Current transition before P6 work began.

The superseded transition head `51c4e4397a47688fce61b51868d8c9cb0c61c211` correctly failed its CI architecture test because a frozen `**P4 inventory decision:**` documentation marker had been renamed. That marker regression was repaired without changing K5 runtime behavior before the accepted transition was revalidated.

## 13. Residual operational/governance notes

Repository/product acceptance does not eliminate normal environment-specific operational governance.

Before production operation, normal deployment/change controls still govern environment configuration, backup/restore readiness, scheduler ownership and any changes to retention windows/work budget. Such changes do not authorize new data classes or public/cross-Kingdom sharing.

Any future proposal for public API/webhook exposure, tenant discovery, cross-Kingdom sharing, additional private data classes, recipient materialization/cache authorization, roster/transfer/diplomacy sharing, scoring/ranking/recommendations or automatic decisions requires a separately reviewed increment/scope change.

## 14. Final disposition

`KINGDOMS-005` is **Accepted** for repository/product purposes.

Accepted capability now includes directional same-Kingdom two-party consent, explicit source-owned target grants, bounded safe current facts, bounded accepted source history with correction/invalidation propagation, complete first-party member/manager presentation, immediate one-time-secret minimization, fail-closed removal/revoke/drift behavior, bounded operational retention, durable-evidence/canonical-history preservation and realistic-volume capacity evidence under the established Alliance tenancy/privacy/security boundaries.

Public sharing integrations, cross-Kingdom or additional data-class sharing, recipient canonical materialization/reshare/mutation, automated decisions and broader production policy changes remain separately **not approved** and are not implied by this acceptance.
