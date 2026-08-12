# KINGDOMS-005 Slice D validation

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Status:** Complete  
**Scope:** `K5-P4` / Slice D — first-party sharing presentation and accessible management UX  
**Runtime candidate:** `9a095ae62e9b913ece6d619c3744574f0b91fd6f`

## 1. Delivered behavior

Slice D exposes the accepted P1–P3 sharing contracts through first-party Inertia pages without widening source ownership, recipient mutation authority, history bounds or public integration scope.

Delivered runtime behavior:

- member-safe `Alliance/KingdomSharing` page presents accepted P2 current facts and P3 bounded history;
- manager-only `Alliance/KingdomSharingManage` presents consent/agreement state and explicit source target grants;
- managers can create one-time invitations, accept/decline invitations, revoke outbound agreements, leave inbound agreements and add/remove explicit source-owned targets through the already-governed K5 mutations;
- member presentation reuses `SharedKingdomIntelligenceCurrentQuery` and `SharedKingdomIntelligenceHistoryQuery` directly rather than widening their projections;
- manager presentation uses a dedicated bounded query limited to 100 agreements and 250 active source-owned trackable targets;
- history navigation accepts only an explicit share-target ID plus the P3 opaque encrypted continuation cursor;
- no client-controlled historical `asOf` selector or equivalent older-window control is exposed;
- history remains capped at 50 rows per page and 250 accepted observations per traversal;
- management state remains manager-only while accepted current/history facts are member-safe;
- invitation plaintext is returned only by the authenticated creation POST and held only in Vue component memory until cleared/navigation;
- invitation plaintext and invitation hashes are never Inertia/session page props;
- source agreement/grant state is not materialized into recipient-owned tracking/observation rows; and
- P4 adds no public API, webhook, external credential, tenant directory/search, cross-Kingdom sharing, recipient copy/reshare or scoring/automatic-decision surface.

## 2. Member-safe presentation evidence

`Alliance/KingdomSharing` exposes only:

- active Alliance display context;
- `canManage` presentation flag;
- accepted safe current-fact projections; and
- one optionally selected bounded history projection.

Focused page-prop tests prove the member page excludes:

- invitation token/hash material;
- source manager notes;
- source tracking IDs;
- observation actor IDs;
- correction/invalidation reason/linkage;
- K4 adapter/subscription/batch/candidate/source provenance;
- management agreement/grant state; and
- arbitrary client-supplied historical `asOf` values.

The page does not create recipient canonical tracking/observation records.

## 3. Manager-only presentation evidence

`Alliance/KingdomSharingManage` requires existing `kingdom.manage` authorization and presents only bounded consent/grant/display metadata needed to operate K5 sharing.

The manager query is bounded to:

- at most 100 outbound agreements;
- at most 100 inbound agreements; and
- at most 250 active source-owned trackable targets.

Manager props exclude invitation hashes, observation payloads, manager notes, observation actors, correction/invalidation metadata, K4 provenance/raw-source material and recipient-private data.

A normal member is forbidden from the manager workspace. The member-safe page's `canManage` flag follows the same existing Kingdom management authorization.

## 4. One-time invitation secret evidence

Invitation creation continues to require recent password confirmation and `kingdom.manage`.

The manager page performs the authenticated JSON creation POST using the app CSRF meta token. The plaintext invitation token:

- is returned only in the creation response;
- is stored only in Vue component memory;
- can be copied and explicitly cleared from the page;
- is never flashed through session state;
- is never rendered as an Inertia prop; and
- is not present on subsequent manager-page loads.

Protected validation exposed a lifecycle hardening defect: consumed/terminal invitations still retained the one-way invitation hash in the database. P4 fixes that by clearing `invitation_token_hash` on accept, decline and revoke.

## 5. Forward schema repair and recovery evidence

The accepted P1 migration created `invitation_token_hash` as a unique non-nullable 64-character column. Rewriting accepted P1 history would invalidate prior slice evidence, so P4 adds the forward migration:

`2026_08_12_030000_make_kingdom_intelligence_share_invitation_hash_nullable.php`

The migration:

- makes `invitation_token_hash` nullable so terminal/consumed secrets can be erased;
- leaves pending invitations uniquely hash-addressable;
- does not alter accepted P1/P2 historical migrations;
- on rollback, fills null hashes with deterministic per-share retired placeholders before restoring the old non-null constraint; and
- on reapply, recognizes those retired placeholders for terminal rows and restores them to null.

Focused migration evidence proves an active terminal share survives `down()` → `up()` with state and recipient unchanged while the placeholder is removed again after reapply. The migration is also included in the full Kingdoms dependency-order rollback/reapply test.

## 6. Lifecycle and fail-closed evidence

Foundation tests prove:

- accepted invitation tokens cannot be reused;
- self-accept fails closed;
- wrong-Kingdom acceptance fails closed without binding a recipient;
- declined invitations become terminal and erase the stored invitation hash;
- revoked pending/active agreements become terminal and erase the stored invitation hash;
- accepted invitations erase the stored invitation hash;
- recipient leave uses the existing terminal `Declined` state and does not restore the secret;
- suspended recipient membership blocks acceptance; and
- recipient Kingdom drift blocks acceptance.

These are lifecycle/privacy hardening changes only; they do not create new sharing capability.

## 7. History-window and cursor evidence

P4 preserves the accepted P3 anti-extraction boundary.

The member page exposes no historical date picker, `asOf` field or free-form cursor control. History links carry only:

- the explicit share-target identity selected from already-authorized current facts; and
- an opaque encrypted continuation cursor returned by the server.

Each continuation request re-runs the accepted P3 authorization chain. UI presentation therefore cannot reopen progressively older 250-record windows through an arbitrary historical start parameter.

## 8. Accessibility and frontend evidence

The existing Kingdoms architecture/accessibility suite now covers both P4 pages.

Evidence includes:

- semantic `<main>` and heading structure;
- labelled invitation/target controls;
- native buttons/select/input controls;
- table captions;
- horizontally scrollable table containers;
- status/alert semantics for invitation creation; and
- source-level absence of an `asOf` history control.

The protected frontend job passed ESLint, the repository's locked Prettier/Tailwind formatting check, Vue TypeScript validation and production Vite build.

## 9. Public integration and ownership boundary

P4 is first-party presentation only.

It adds no:

- public or anonymous sharing endpoint;
- external API credential;
- webhook/event delivery contract;
- cross-Kingdom agreement;
- tenant-directory discovery;
- roster/player, transfer or diplomacy/contact sharing;
- recipient mutation of source observations;
- recipient copy/reshare action; or
- ranking/recommendation/automatic decision behavior.

Source ownership of tracking and observation history remains unchanged.

## 10. Protected validation evidence

Runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed:

- Dependency Review `31569202741` — success;
- CodeQL `31569202422` — success;
- CI `31569202418` — success;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations, including the forward nullable-hash migration — success;
- Pint — **556 files**;
- PHPStan/Larastan — **393/393, 0 errors**;
- ParaTest/PHPUnit — **448 tests, 10,160 assertions**;
- frontend dependency audit, ESLint, locked Prettier/Tailwind check, Vue TypeScript check and production build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## 11. Gate decision

`K5-P4` / Slice D is **Complete** at runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f`.

`K5-P5` may be selected next for invitation/grant retention operations and realistic-volume current/history capacity hardening, but P5 runtime work is writable only after the exact containing evidence/status head that records P4 Complete / P5 Current independently passes Dependency Review, CodeQL and full CI/recovery.
