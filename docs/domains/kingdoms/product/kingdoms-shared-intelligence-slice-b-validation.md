# KINGDOMS-005 Slice B validation

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Status:** Complete  
**Scope:** `K5-P2` / Slice B — explicit shared targets and safe recipient current facts  
**Runtime candidate:** `1a022e909cd246197510449a761a4856ce12b118`

## 1. Delivered behavior

Slice B introduces the first K5 cross-tenant intelligence read path while preserving the P0/P1 consent boundary.

Delivered runtime behavior:

- `kingdom_intelligence_share_targets` records explicit source-selected target grants per active directional sharing agreement;
- grants are `active|removed` and unique per share/tracking pair;
- removed targets require another deliberate source-manager action before becoming active again;
- add/remove target mutations are authenticated first-party routes under recent password confirmation and domain-level `kingdoms.manage`;
- add requires an active source-owned sharing agreement, active source/recipient Alliances, current Kingdoms equal to the captured sharing Kingdom, and an active source-owned `TrackedKingdomAlliance` in that Kingdom;
- recipient reads begin from the active recipient Alliance and resolve only active/context-valid agreements plus explicit active target grants;
- the recipient projection is bounded to 250 current rows;
- latest accepted observation selection reuses K3 semantics: `invalidated_at IS NULL`, `captured_at <= as-of`, ordered `captured_at DESC, id DESC`;
- freshness reuses the existing K3 30-day boundary and returns `current|stale|missing`;
- target removal, agreement revocation or context invalidation immediately removes recipient visibility;
- supported Alliance→Kingdom changes terminalize affected active agreements and source pending invitations so leaving and later returning cannot resume old consent; and
- P2 creates no bounded history endpoint/query; history remains P3.

## 2. Safe recipient projection

The P2 internal current-fact projection returns only:

- opaque `shareTargetId`;
- source platform Alliance ID/name;
- neutral/current game-Alliance name/tag;
- latest accepted observed name/tag;
- optional power and member count;
- observation capture timestamp; and
- `current|stale|missing` freshness.

It intentionally excludes source tracking IDs, stable game IDs, manager notes, diplomacy/contact data, player/roster/transfer data, observation actors, correction/invalidation reasons, K4 adapter/subscription/batch/candidate/cursor/source provenance, raw source data/secrets, Audit/outbox internals and observation history.

## 3. Source ownership, no-copy and no-reshare evidence

Recipient reads do not create recipient-owned `TrackedKingdomAlliance` or `KingdomAllianceObservation` rows.

A recipient cannot use its own outbound K5 agreement to re-grant a source-owned tracking ID because target selection resolves tracking beneath the outbound source Alliance. This prevents transitive/recursive reshare through the K5 grant path.

The source remains the canonical owner. A recipient receives a live authorization projection only.

## 4. Correction, invalidation and freshness evidence

Focused tests create an older accepted observation plus a newer accepted observation, then invalidate the newer source observation.

Before invalidation the recipient sees the newer fact as `current`. After source invalidation the projection immediately falls back to the older still-accepted fact, which becomes `stale` under the existing 30-day K3 freshness boundary. The private invalidation reason never appears in the recipient payload.

An explicitly shared target with no accepted observation returns `freshness = missing` and `latestObservation = null`; missing is not converted to zero.

## 5. Explicit-target-only and tenant-isolation evidence

Tests prove:

- an unshared source tracking relationship is absent from recipient results;
- recipient and unrelated Alliances cannot add a source target through submitted share/tracking IDs;
- recipient cannot remove a source-owned target grant;
- source can remove its grant and recipient visibility immediately becomes empty;
- a deliberately re-granted target becomes visible again while the share is valid;
- share revocation removes visibility immediately; and
- another tenant receives no data unless it has its own active agreement plus its own source-owned explicit grant.

## 6. Persistent Kingdom-drift fail-closed behavior

P0 prohibited silent retarget/reactivation after Kingdom drift. P2 makes that invariant durable in the supported Alliance→Kingdom mutation path.

When an Alliance changes Kingdom:

- active agreements sourced by that Alliance are revoked;
- its pending source invitations are revoked;
- active agreements where it is recipient become declined;
- safe internal `kingdoms.shared_intelligence_context_invalidated` evidence is recorded for both sides without cross-tenant manager identity leakage; and
- returning later to the original Kingdom does not reactivate the terminal agreement.

A new collaboration requires a new invitation/agreement.

## 7. Concurrency and lock-order evidence

Consent/data-grant actions align locking around Alliance rows before share/target rows where concurrent Kingdom drift matters.

Acceptance and target grant lock source/recipient Alliances in deterministic ID order before locking the agreement/grant state. This avoids the prior potential lock cycle between Alliance→Kingdom changes and K5 accept/grant operations.

Authorization remains database/domain authoritative; a stale cache is not part of P2 authorization.

## 8. Query and capacity evidence

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250.

A focused test creates 12 explicit targets with observations, then verifies the current projection returns all 12 using no more than two SELECT queries:

1. recipient-first active agreement/target/context projection; and
2. bounded latest accepted observation lookup for the selected source tracking set.

P2 makes no realistic-volume production capacity SLO claim; broader capacity hardening remains P5.

## 9. Migration and rollback evidence

The new `2026_08_12_020000_create_kingdom_intelligence_share_targets` migration is included in clean PostgreSQL CI.

The complete Kingdom migration round-trip drops the target table before the parent K5 sharing table and reapplies it after the sharing table. The focused K3 tracking rollback also temporarily drops/reapplies the P2 target table because it FK-depends on `tracked_kingdom_alliances`.

The restored target table and key grant columns are explicitly asserted.

## 10. Protected validation evidence

Runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed:

- Dependency Review `31562753429` — success;
- CodeQL `31562753422` — success;
- CI `31562753430` — success;
- PHP 8.5.9;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations through `2026_08_12_020000_create_kingdom_intelligence_share_targets` — success;
- Pint — **550 files**;
- PHPStan/Larastan — **390/390, 0 errors**;
- ParaTest/PHPUnit — **440 tests, 10,025 assertions**;
- frontend dependency audit/checks/build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## 11. Gate decision

`K5-P2` / Slice B is **Complete** at runtime candidate `1a022e909cd246197510449a761a4856ce12b118`.

`K5-P3` / Slice C may be selected next, but bounded shared-history implementation is writable only after the exact containing evidence/status head that records P2 Complete / P3 Current passes Dependency Review, CodeQL and full CI.
