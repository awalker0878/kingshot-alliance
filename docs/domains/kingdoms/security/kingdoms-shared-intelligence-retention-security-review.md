# KINGDOMS-005 Slice E retention and capacity security review

[← Kingdoms security profile](README.md)

**Scope:** `K5-P5` / Slice E — bounded operational retention and realistic-volume capacity hardening  
**Status:** Complete  
**Runtime candidate:** `b47f639a275652590304fccef051f78997a0153c`

## 1. Review purpose

Slice E adds destructive maintenance around K5 operational consent/grant rows and introduces realistic-volume evidence for recipient current/history reads. The primary security risks are deleting active authorization state, using retention as delayed secret cleanup, deleting canonical source observations, losing durable Audit/outbox evidence, widening extraction through performance shortcuts, introducing authorization-unsafe caching, and allowing a stale candidate set to delete newly reactivated/re-granted rows.

P5 is acceptable because cleanup is bounded and predicate-rechecked, active/canonical state is ineligible, secret erasure remains immediate, durable business evidence remains outside cleanup, and the read path gains only tests rather than a new cache/materialization boundary.

## 2. Retention data classification

Retention applies only to K5 operational sharing metadata:

- expired pending `kingdom_intelligence_shares`;
- old `Declined`/`Revoked` `kingdom_intelligence_shares`; and
- old `Removed` `kingdom_intelligence_share_targets`.

It does not delete:

- active sharing agreements;
- active target grants;
- source `TrackedKingdomAlliance` rows;
- source `KingdomAllianceObservation` rows;
- Audit events; or
- transactional outbox messages.

This distinction preserves the accepted source-owned canonical intelligence boundary.

## 3. Secret-minimization boundary

P5 retention is not responsible for consuming or erasing one-time invitation credentials.

P4 remains authoritative: invitation plaintext is never persisted and the stored SHA-256 hash is cleared immediately on accept, decline and revoke. P5 may later delete an expired pending invitation row only after its expiry is older than the configured retention window.

This prevents an operational maintenance schedule from extending the lifetime of a secret-derived lookup value that is no longer required.

## 4. Active-state preservation

Retention predicates are intentionally state-specific:

- pending + sufficiently old expiry for invitation cleanup;
- declined/revoked + sufficiently old terminal timestamp for terminal-share cleanup; and
- removed + sufficiently old `removed_at` for target cleanup.

Active agreements and active grants are not candidates.

The action selects bounded IDs and then repeats the state/cutoff predicate in the destructive statement. A row that changes to a non-eligible state between selection and deletion therefore fails the second predicate and survives.

This avoids treating a previously observed candidate ID as unconditional delete authority.

## 5. Bounded destructive-work control

One invocation uses one total work budget, default 500 and clamped to 1–2000.

That budget is consumed in priority order rather than multiplied across cleanup classes. An operator cannot request an unbounded sweep through the supported command.

The scheduled invocation uses `--limit=500`, runs daily at 04:30, is `onOneServer()` and `withoutOverlapping(60)`.

These controls reduce lock/IO amplification and make maintenance progress observable without turning cleanup into a bulk tenant-data erasure primitive.

## 6. Canonical source-history preservation

Deleting K5 operational consent/grant rows does not delete the source's canonical tracking or observation history.

Focused tests prove source tracking and observations survive:

- deletion of an old terminal agreement and its K5 target rows;
- deletion of an old removed target grant; and
- retention runs around an active agreement/grant.

Recipient reads remain projections over source-owned rows; no recipient canonical observation copy exists to clean up or synchronize.

## 7. Audit and outbox preservation

K5 retention does not purge `audit_events` or `outbox_messages`.

Focused evidence verifies revoke Audit/outbox records remain after the old operational agreement row is deleted. Retention therefore does not erase the durable business evidence used to establish that a consent/access-reducing event occurred.

The preserved evidence remains metadata-only under the accepted K5 rules; invitation plaintext/hash material, history cursors and shared observation payload bodies are not introduced into Audit/outbox records by P5.

## 8. Capacity and anti-extraction boundary

P5 adds realistic-volume evidence but does not widen read limits.

At 300 active explicit grants, the current query still returns only 250 safe rows and remains within two SELECTs. At 1,000 source observations for one target, history still permits only five 50-row pages and 250 accepted observations in one traversal, with no more than two SELECTs per page.

The existing encrypted target-bound cursor, fixed traversal snapshot and cumulative accepted-record count remain unchanged. P5 does not expose an arbitrary `asOf`, offset, observation ID or unrestricted historical export control.

Performance hardening therefore does not become a data-extraction shortcut.

## 9. Response-size and projection boundary

The capacity fixture asserts bounded encoded response sizes:

- current facts: at most 160,000 bytes for the reviewed 250-row fixture; and
- history: at most 50,000 bytes per reviewed 50-row fixture page.

These are regression ceilings, not production network/body-size security guarantees or throughput SLOs. They provide evidence that the accepted safe-field projection has not silently expanded under realistic row counts.

Private source/K4/governance fields remain excluded by the accepted P2–P4 projection tests.

## 10. No new cache or materialization trust boundary

P5 introduces no cache, replica-specific authorization layer, recipient materialized view, search index or diagnostic copy.

Every current/history request continues to use the accepted live authorization chain. Existing immediate fail-closed behavior on revoke, target removal, membership/Kingdom context loss and source invalidation therefore requires no new cache invalidation mechanism.

If a future increment introduces caching/materialization, it requires separate review and must preserve those live fail-closed semantics.

## 11. Backup/restore and recovery security

P5 adds no database migration. Operational K5 rows, canonical source history and Audit/outbox evidence remain PostgreSQL-owned and covered by the existing backup/restore procedure.

A restore may reintroduce rows that were later purged by retention. That does not restore sharing authority by itself: state, grant and same-Kingdom authorization checks remain authoritative on every recipient read.

After normal restore validation, the bounded retention command may safely be rerun. Operators must not directly edit states, timestamps, source/recipient IDs, target ownership or canonical observations to make a restored dataset appear current.

## 12. Public exposure and residual risk

P5 adds no public API/webhook, external credential, tenant directory, cross-Kingdom sharing, recipient copy/reshare, player/roster sharing, transfer/diplomacy/contact sharing, ranking or automated decision surface.

Residual risk is primarily operational: a misconfigured retention window could keep operational rows longer or make eligible rows purge sooner than intended. Runtime clamps and state predicates prevent the configuration from authorizing new access or deleting active/canonical state, but retention-window changes remain governed operational changes and should be reviewed before production use.

Whole-increment P6 must re-prove the complete seam, including unrelated-tenant failure and immediate authorization loss.

## 13. Validation evidence

Runtime candidate `b47f639a275652590304fccef051f78997a0153c` passed:

- Dependency Review `31570931190`;
- CodeQL `31570931290`;
- CI `31570931267`;
- Pint 559 files;
- PHPStan/Larastan 394/394 with zero errors;
- 451 tests / 10,230 assertions;
- clean migrations;
- frontend dependency audit, lint, locked formatting, Vue typecheck and production build;
- immutable image build;
- ephemeral staging deployment;
- backup/restore demonstration;
- image vulnerability scan; and
- cleanup.

Focused tests prove bounded/idempotent retention, active-state preservation, canonical-history preservation, durable Audit/outbox evidence, 300-target current-read bounds, 1,000-observation history bounds and zero recipient canonical observation copies.

See [Slice E validation](../product/kingdoms-shared-intelligence-slice-e-validation.md), [retention operations runbook](../operations/kingdoms-shared-intelligence-retention.md), and [living shared-intelligence contract](../shared-intelligence.md).
