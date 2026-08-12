# KINGDOMS-005 Slice C shared-history security review

[← Kingdoms security profile](README.md)

**Scope:** `K5-P3` / Slice C — bounded accepted shared history  
**Status:** Complete  
**Runtime candidate:** `70739d320caab059d2102feda081be33754b77ec`

## 1. Review purpose

Slice C extends the accepted P2 cross-tenant read boundary from one current fact to a bounded source-owned observation timeline. The additional risks are unbounded extraction, cursor tampering, stale continuation after authorization loss, correction/private-provenance leakage and accidental recipient history materialization.

P3 is acceptable because every page remains recipient-first authorized, accepted-only, explicitly target-bound, bounded to the accepted K3 history window and safe-field projected.

## 2. Authorization boundary

Every history page requires:

- active recipient Alliance;
- active directional agreement whose recipient matches the active tenant;
- source and recipient Alliances active in the captured Kingdom;
- active explicit target grant;
- target tracking still owned by the source Alliance;
- source tracking active in the captured Kingdom; and
- neutral game-Alliance reference still in that Kingdom.

The share target is the authorization anchor. A cursor alone never authorizes data.

## 3. Cursor integrity and anti-enumeration controls

P3 does not use a plain offset, observation ID or client-editable timestamp cursor.

`SharedKingdomIntelligenceHistoryCursor` encrypts and authenticates:

- cursor version;
- bound share-target ID;
- fixed history `asOf` snapshot;
- last accepted capture timestamp;
- last accepted observation ordering ID; and
- accepted record count already emitted.

Cursor decoding rejects malformed ciphertext, invalid JSON/schema, wrong target binding, impossible timestamps or `seen` outside 1–250.

The opaque cursor therefore does not disclose source observation IDs/target identity in plaintext and cannot be altered to skip the 250-record limit.

## 4. Bounded extraction controls

Page size is capped at 50. A single history traversal is capped at 250 accepted observations, matching the existing K3 source-history bound.

The encrypted cursor authenticates the cumulative `seen` count. Even when the source owns more than 250 accepted observations, the P3 traversal ends at 250.

The first request's `asOf` becomes fixed in the continuation cursor, preventing newly appended source observations from reshuffling an in-progress traversal.

P4 must not expose an arbitrary client-controlled historical `asOf` selector that could be used to start repeated windows over progressively older history without a separately reviewed expansion.

## 5. Accepted-only correction/invalidation semantics

History selects only rows with `invalidated_at IS NULL` and orders by `captured_at DESC, id DESC`.

A source invalidation or correction immediately removes the invalidated row from subsequent recipient pages. A corrected replacement participates only as its own accepted source observation.

Recipient output excludes correction linkage, correction/invalidation reason and actor identity. The source's private human governance history therefore does not cross tenants.

## 6. Safe-field projection

History items expose only accepted observed name/tag, optional power/member count, capture time and descriptive freshness.

Safe surrounding context is limited to source Alliance ID/name and neutral/current game-Alliance name/tag.

Excluded fields include observation IDs, source tracking IDs, stable game IDs, actor IDs, correction/invalidation metadata, manager notes, diplomacy/contact data, roster/transfer data, K4 provenance/raw source data/secrets, Audit/outbox internals and private free text.

P3 explicitly constructs arrays from selected columns rather than serializing source models.

## 7. Authorization loss and stale-cursor behavior

Each page re-runs live recipient/share/grant/context authorization before using the cursor position.

Target removal, agreement revocation or persistent Kingdom-context invalidation makes history unavailable immediately. Returning later to the captured Kingdom does not restore the terminal agreement.

An otherwise valid old cursor cannot bypass these state checks.

## 8. No-copy and no-reshare controls

P3 history is a read projection only. It writes no recipient-owned `TrackedKingdomAlliance` or `KingdomAllianceObservation` rows.

No P3 mutation accepts a received observation/cursor as an upstream source target. P2 source-owned target resolution continues to prohibit transitive/recursive reshare.

The encrypted cursor is a continuation token for one recipient-authorized read, not a portable sharing credential.

## 9. Query and denial-of-service posture

A page uses one bounded authorization/context query and one accepted-observation keyset query. The focused 260-observation fixture proves no more than two SELECTs per page.

Page size and total-window limits cap response construction and database row materialization. Keyset pagination avoids increasingly expensive offset scans.

P3 does not claim a production throughput SLO. Realistic-volume workload/caching/diagnostic hardening remains P5 and must preserve live authorization.

## 10. Logging, events and public exposure

P3 adds no mutation event because it introduces no new business mutation. Existing consent/grant/context events remain internal `kingdoms.*` events.

History payloads and cursors must not be copied into general logs/Audit/outbox evidence. The query introduces no public API, webhook, anonymous feed or external credential.

Complete first-party source/recipient presentation remains P4.

## 11. Residual risk and P4 gate

P4 will expose the accepted current/history query contracts through first-party pages. That increases presentation/privacy risks even though the underlying data boundary is accepted.

Before P4 acceptance, tests/review must verify members/managers see only the intended safe views, management state remains manager-only, cursors are passed opaquely, no arbitrary `asOf` history-window control is exposed, drift/revocation is clear, no private IDs/fields leak into page props, and accessibility/source-level UI gates pass.

## 12. Validation evidence

Runtime candidate `70739d320caab059d2102feda081be33754b77ec` passed Dependency Review `31564263865`, CodeQL `31564263863`, and CI `31564263891`: Pint 553 files, PHPStan/Larastan 392/392 zero errors, 443 tests / 10,086 assertions, clean migrations, frontend/build, immutable image, staging, backup/restore, image scan and cleanup.

Focused tests prove encrypted target-bound cursor behavior, safe accepted pagination, correction/invalidation privacy, hard 250-record traversal cap, 50-row page cap, no more than two SELECTs per page, no recipient canonical copy and immediate fail-closed history access after remove/revoke/drift.

See [Slice C validation](../product/kingdoms-shared-intelligence-slice-c-validation.md) and [living shared-intelligence contract](../shared-intelligence.md).
