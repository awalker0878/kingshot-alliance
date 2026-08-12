# KINGDOMS-005 Slice A validation

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Status:** Complete  
**Scope:** `K5-P1` / Slice A — directional two-party sharing consent foundation  
**Runtime candidate:** `9ef1d46b1db69708d575e82d8548145cf7769e68`

## Delivered behavior

Slice A establishes only the K5 sharing-agreement and invitation consent boundary. It does not expose shared targets, current facts or observation history.

Delivered runtime behavior:

- `kingdom_intelligence_shares` persists source/recipient/captured-Kingdom consent state only;
- source managers create one-time invitations under `kingdoms.manage` + recent password confirmation;
- invitation secrets use 32 cryptographically random bytes represented as 64 lowercase hex characters;
- only SHA-256 token hashes are persisted and hidden from normal model serialization;
- invitation TTL defaults to 72 hours and is clamped to 1–168 hours;
- recipient managers accept or decline under their own active Alliance context;
- acceptance rejects self-share, different-Kingdom state, expired/used token and duplicate active directional source→recipient agreement;
- acceptance locks source/recipient Alliance rows in deterministic ID order;
- source revocation supports pending/active agreements;
- active recipients can leave;
- decline/revoke/leave remain valid after Kingdom drift because they only reduce access;
- terminal declined/revoked agreements never reactivate through these actions;
- consent actions produce safe internal Audit/outbox evidence; and
- the source tenant does not receive the recipient manager's User ID as an acceptance actor.

## No-data-sharing boundary

Slice A intentionally has no `kingdom_intelligence_share_targets` table and no shared observation payload/history columns.

There is no sharing GET/list/current/history endpoint, no recipient shared-intelligence projection, no source target-selection endpoint, no tenant directory/search, and no automatic recipient tracking/history creation.

P1 therefore establishes the authorization/consent bridge only. P2 is the first slice permitted to introduce an explicit shared target and safe recipient read projection, subject to its own gate.

## Token and consent evidence

Focused tests verify:

- creation returns a 64-character lowercase-hex plaintext token once;
- persisted value equals SHA-256(token), never plaintext;
- token hash is hidden from model array serialization;
- expiry is future and bounded by the configured 72-hour default;
- plaintext token is absent from outbox payloads;
- stale password confirmation blocks consent mutations;
- a member without `kingdoms.manage` is forbidden;
- successful same-Kingdom acceptance binds the recipient and consumes the token;
- exact token replay fails;
- a second active directional agreement fails;
- self-share fails;
- different-Kingdom acceptance fails without consuming the token;
- a different-Kingdom recipient may still decline the invitation;
- expired invitation cannot activate; and
- unrelated tenants cannot revoke or leave another agreement by submitted share ID.

## Terminal and drift behavior

Source revoke and recipient leave are row-locked, tenant-scoped and access-reducing. They do not depend on the captured Kingdom still matching because forcing a stale agreement to remain active would be less safe than permitting termination.

Tests deliberately change source/recipient Kingdom context before revoke/leave and verify those terminal transitions remain available. Repeating a terminal revoke/leave remains terminal and does not reactivate the agreement.

## Audit and privacy evidence

Invitation-created, accepted, declined, revoked and left events remain `kingdoms.*` internal events.

Audit/outbox payloads contain safe share/source/recipient/Kingdom/state/timing metadata only. Invitation plaintext and observation/K4/private data are excluded.

On acceptance, the recipient's own tenant Audit entry records its manager actor. The corresponding source-tenant acceptance record uses a null actor so the recipient manager's cross-tenant User ID is not disclosed to the source Alliance.

## Migration and rollback evidence

The new `2026_08_12_010000_create_kingdom_intelligence_shares` migration is included in clean PostgreSQL CI and in the full Kingdoms migration rollback/reapply test.

The round-trip explicitly drops the K5 share table before the K4/K3/K2/K1 dependency chain and reapplies it after K4 scheduling. The restored table is asserted to contain invitation-token, recipient-Alliance and captured-Kingdom columns.

## Protected validation evidence

Runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed:

- Dependency Review `31559012856` — success;
- CodeQL `31559012854` — success;
- CI `31559012861` — success;
- Pint — 541 files;
- PHPStan/Larastan — 384/384, zero errors;
- ParaTest/PHPUnit — 434 tests / 9,911 assertions;
- Composer audit — no security vulnerability advisories;
- frontend dependency audit/checks/build — success;
- clean PostgreSQL migrations — success;
- immutable production image — success;
- ephemeral staging — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## Gate decision

`K5-P1` / Slice A is **Complete** at runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68`.

`K5-P2` / Slice B may be selected next, but actual shared-target/current-fact implementation is writable only after the exact containing evidence/status head that records P1 Complete / P2 Current passes Dependency Review, CodeQL and full CI.
