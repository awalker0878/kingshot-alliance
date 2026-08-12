# KINGDOMS-005 Slice C validation

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Status:** Complete  
**Scope:** `K5-P3` / Slice C — bounded accepted shared history  
**Runtime candidate:** `70739d320caab059d2102feda081be33754b77ec`

## 1. Delivered behavior

Slice C adds bounded recipient history for one explicitly shared active target without changing source ownership or recipient mutation authority.

Delivered runtime behavior:

- internal `SharedKingdomIntelligenceHistoryQuery` authorizes from active recipient Alliance → active sharing agreement → active explicit target grant → active source-owned tracking/current captured-Kingdom context;
- only accepted/non-invalidated source `KingdomAllianceObservation` rows participate;
- history is ordered deterministically by `captured_at DESC, id DESC`;
- default and maximum page size is 50;
- one history traversal is capped at 250 accepted observations, matching the existing K3 history bound;
- continuation state uses an encrypted target-bound cursor with fixed `asOf`, last capture time/observation position, and authenticated `seen` count;
- the cursor cannot be applied to another share target and cannot be modified to walk beyond the 250-observation window;
- recipient history includes only safe observation facts plus source/game-Alliance display context;
- source correction/invalidation removes invalidated facts from recipient history automatically; accepted replacements appear as their own accepted observations;
- target removal, agreement revocation or Kingdom-context invalidation immediately prevents history access;
- recipient history creates no recipient-owned tracking or observation rows; and
- P3 adds no HTTP/public API/UI surface or new mutation authority.

## 2. Safe history projection

Each history response contains:

- opaque `shareTargetId`;
- source platform Alliance `{id,name}`;
- neutral/current game-Alliance `{name,tag}`;
- bounded `items`; and
- opaque `nextCursor` when another page exists within the accepted 250-record window.

Each item contains only:

- accepted observed name/tag;
- optional power/member count;
- capture time; and
- descriptive `current|stale` freshness based on the existing K3 30-day boundary.

The projection excludes observation IDs, source tracking IDs, stable game IDs, observation actors, correction/invalidation reasons, `corrects_observation_id`, K4 adapter/subscription/batch/candidate/cursor/source provenance, source secrets/raw responses, manager notes, diplomacy/contact data, roster/transfer state and Audit/outbox internals.

## 3. Pagination and bounded-window evidence

`SharedKingdomIntelligenceHistoryQuery` uses:

- `DEFAULT_PAGE_SIZE = 50`;
- `MAX_PAGE_SIZE = 50`; and
- `HISTORY_LIMIT = 250`, inherited from the accepted K3 observation-history bound.

The first page fixes one `asOf` snapshot. Subsequent pages use an encrypted cursor containing the target identity, fixed `asOf`, last accepted observation position and accepted-record count already emitted.

A focused fixture with 260 accepted source observations proves:

- exactly five 50-row pages are returned;
- exactly 250 accepted observations can be traversed;
- the cursor ends at the 250-record boundary even when older accepted source history exists; and
- each page uses no more than two SELECT queries.

## 4. Cursor integrity evidence

`SharedKingdomIntelligenceHistoryCursor` uses Laravel encrypted-string protection rather than a client-readable offset/counter.

Cursor validation requires:

- supported version;
- exact share-target binding using constant-time comparison;
- valid fixed `asOf` and last-captured timestamps;
- last observation position;
- accepted `seen` count between 1 and 250; and
- cursor capture time not after the fixed `asOf`.

Malformed/tampered/decryption-failed cursors produce a bounded `cursor` validation error. A cursor created for one share target is rejected for another target.

## 5. Accepted-only and correction/invalidation evidence

Focused tests create accepted source observations including one later corrected observation.

The source correction action invalidates the original source row and writes a new accepted replacement. Recipient history:

- excludes the invalidated original;
- includes the accepted replacement as its own observation;
- preserves deterministic accepted ordering;
- excludes the private correction reason; and
- exposes no correction linkage or actor metadata.

This preserves K3 append/correction semantics without materializing a recipient copy.

## 6. Authorization-loss evidence

History authorization is re-evaluated on every page.

Tests prove history becomes unavailable after:

- explicit target removal;
- source agreement revocation;
- recipient Kingdom drift through the supported Alliance→Kingdom workflow; and
- returning later to the original Kingdom after that persistent terminalization.

A stale continuation cursor does not bypass current agreement/grant/context authorization.

## 7. No-copy and no-reshare boundary

Recipient history queries create no recipient-owned `TrackedKingdomAlliance` or `KingdomAllianceObservation` rows.

P3 does not add any action that turns received history into a source tracking relationship or outbound target grant. Existing P2 source-owned target resolution continues to prevent transitive/recursive reshare.

## 8. Query and capacity evidence

For each page the query shape is bounded to:

1. recipient/share/grant/source-context authorization and safe display context; and
2. accepted source observation retrieval using keyset pagination.

The 260-observation fixture verifies no more than two SELECTs per 50-row page.

This is a slice-level bounded-query gate, not a production throughput SLO. Realistic-volume current/history capacity and any authorization-safe caching remain P5 work.

## 9. Public integration and UI boundary

P3 adds no route, controller, public API, webhook, anonymous feed, external credential or Vue page.

The history query is an internal domain query intended for the later P4 first-party recipient experience. All K5 public-integration exclusions remain unchanged.

## 10. Protected validation evidence

Runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed:

- Dependency Review `31564263865` — success;
- CodeQL `31564263863` — success;
- CI `31564263891` — success;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations — success;
- Pint — **553 files**;
- PHPStan/Larastan — **392/392, 0 errors**;
- ParaTest/PHPUnit — **443 tests, 10,086 assertions**;
- frontend dependency audit/checks/build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## 11. Gate decision

`K5-P3` / Slice C is **Complete** at runtime candidate `70739d320caab059d2102feda081be33754b77ec`.

`K5-P4` / Slice D may be selected next, but source/recipient UX, full sharing-state presentation and accessibility implementation are writable only after the exact containing evidence/status head that records P3 Complete / P4 Current passes Dependency Review, CodeQL and full CI.
