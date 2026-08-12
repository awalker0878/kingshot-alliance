# KINGDOMS-005 Slice E validation

[← KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)

**Status:** Complete  
**Scope:** `K5-P5` / Slice E — privacy, retention, operations and capacity hardening  
**Runtime candidate:** `b47f639a275652590304fccef051f78997a0153c`

## 1. Delivered behavior

Slice E hardens the accepted P1–P4 shared-intelligence capability for bounded operational retention and realistic read volume without widening the sharing contract.

Delivered runtime behavior:

- bounded `EnforceKingdomIntelligenceSharingRetention` operational cleanup;
- one shared per-run work budget, clamped to 1–2000 rows and defaulting to 500;
- cleanup priority of expired pending invitations, then old terminal agreements, then old removed grants;
- default retention windows of 30 days after invitation expiry, 180 days for terminal agreements and 90 days for removed grants;
- delete-time state/cutoff rechecks so stale candidate IDs cannot delete a share/grant that changed state before deletion;
- active agreements and active grants are never retention-eligible;
- canonical source tracking and `KingdomAllianceObservation` history are never retention-eligible;
- Audit/outbox evidence is outside the K5 operational-row cleanup boundary;
- operator command `kingdoms:enforce-sharing-retention {--limit=500}`;
- daily 04:30 single-server, non-overlapping schedule;
- realistic-volume capacity evidence for current and history reads; and
- no cache or diagnostic materialization layer, so no new authorization/cache invalidation seam was introduced.

P5 preserves immediate invitation-hash erasure from P4. Retention is not used as delayed secret cleanup.

## 2. Retention eligibility and ordering

One invocation owns one total work budget. The action consumes that budget in this order:

1. pending invitations whose `invitation_expires_at` is older than the configured expired-invitation window;
2. `Declined` or `Revoked` agreements whose terminal timestamp is older than the configured terminal-share window; then
3. removed target grants whose `removed_at` is older than the configured removed-target window.

The default windows in `config/kingdoms.php` are:

- `shared_intelligence_retention.expired_invitation_days = 30`;
- `shared_intelligence_retention.terminal_share_days = 180`; and
- `shared_intelligence_retention.removed_target_days = 90`.

The terminal-share window is never allowed below the expired-invitation window at runtime. All windows are clamped to at least one day.

## 3. Bounded and race-safe cleanup evidence

The action first selects a bounded ordered set of candidate IDs and then repeats the state/cutoff predicate in the delete statement.

That second predicate is security/consistency significant: an operational row that was reactivated, re-granted or otherwise changed between candidate selection and deletion no longer matches the destructive predicate and is preserved.

The command applies the same overall 1–2000 bound before invoking the action. The scheduled command uses `--limit=500`.

Focused tests prove:

- one total budget is shared across cleanup classes rather than applied independently to each table/state;
- subsequent runs continue bounded cleanup;
- a third run after the eligible set is exhausted processes zero rows;
- recent expired invitations remain;
- active agreements remain;
- active grants remain; and
- cleanup is idempotent once eligible records are gone.

## 4. Canonical history and durable evidence preservation

Operational K5 consent/grant cleanup does not delete or rewrite source-owned Kingdoms intelligence.

Focused retention evidence creates canonical source tracking and observations under:

- a terminal agreement whose operational share/target rows become eligible;
- an active agreement with an old removed grant; and
- an active agreement with an active grant.

After retention:

- all source `TrackedKingdomAlliance` rows remain;
- all source `KingdomAllianceObservation` rows remain;
- deleting a terminal share may cascade only its K5 target rows; and
- the active share/active target remains usable.

Audit and transactional-outbox evidence for the revoked share remains after the operational sharing rows are deleted.

This keeps operational metadata retention separate from canonical intelligence history and durable business evidence.

## 5. Immediate secret-minimization boundary

P5 does not relax P4 one-time invitation handling.

Invitation plaintext remains response/component-memory-only. Persisted invitation hashes are still erased immediately on accept, decline and revoke. Retention handles only already-expired pending invitation rows and old terminal operational metadata; it is not a substitute for immediate secret erasure.

Pending invitations still use their hash for one-time lookup only while pending and within the accepted consent lifecycle.

## 6. Current-facts capacity evidence

`KingdomSharedIntelligenceCapacityTest` builds one real active same-Kingdom agreement, then creates 300 active explicitly granted source targets with one accepted current observation each.

Only the read path is measured.

The accepted evidence proves:

- `SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` still returns exactly 250 rows at and beyond the cap;
- the current projection uses no more than two SELECTs;
- the JSON-encoded bounded current response stays at or below the reviewed 160,000-byte test ceiling; and
- the recipient still owns zero canonical `kingdom_alliance_observations` rows.

These numbers are regression/capacity fixtures, not production throughput or latency SLOs.

## 7. Shared-history capacity evidence

The same fixture creates 1,000 accepted source observations for one explicitly shared target.

The accepted history traversal proves:

- five pages of exactly 50 items each;
- exactly 250 accepted observations returned across one traversal;
- the continuation cursor is null after the fifth page;
- each page uses no more than two SELECTs — one authorization/context SELECT plus one bounded observation SELECT;
- all five pages together use exactly 10 SELECTs in the fixture;
- each encoded history response stays at or below the reviewed 50,000-byte test ceiling;
- all 1,000 source observations remain canonical; and
- the recipient still owns zero canonical observation rows.

P5 therefore adds volume evidence without weakening the P3 50-row page, 250-observation traversal, opaque target-bound cursor or no-copy rules.

## 8. Authorization and cache boundary

P5 introduces no cache, replica, materialized recipient projection or diagnostic acceleration layer.

Current/history requests continue to execute the accepted live authorization chain. Revocation, target removal, membership/Kingdom context loss and source invalidation therefore retain the existing immediate fail-closed semantics without any new cache invalidation mechanism.

No P5 retention operation can create, reactivate or grant access.

## 9. Operator and scheduler evidence

The supported operator command is:

`php artisan kingdoms:enforce-sharing-retention --limit=500`

The command prints a JSON summary containing:

- `expiredInvitationsPurged`;
- `terminalSharesPurged`;
- `removedTargetsPurged`; and
- `processed`.

The scheduler invokes the bounded command daily at 04:30 with `onOneServer()` and `withoutOverlapping(60)`.

Operators may lower the limit for conservative cleanup. Raising it is bounded to 2000 and does not change eligibility rules.

See the [shared-intelligence retention operations runbook](../operations/kingdoms-shared-intelligence-retention.md).

## 10. Backup, restore and recovery boundary

P5 adds no schema migration. Sharing rows, source observations and durable Audit/outbox evidence remain PostgreSQL-owned and covered by the existing repository backup/restore procedure.

The protected CI recovery leg successfully demonstrated backup and restore with the P5 runtime present.

A restore may reintroduce operational rows that existed at backup time. Recovery does not bypass live authorization: restored terminal/expired/removed rows remain non-authorizing and the bounded retention command may be run again after normal restore validation.

Operators must not use direct SQL deletion/reactivation/retargeting as a retention or recovery shortcut.

## 11. Public integration and scope boundary

P5 adds no:

- public API or webhook sharing;
- external provider/credential;
- cross-Kingdom sharing;
- tenant-directory discovery;
- player/roster, transfer or diplomacy/contact sharing;
- recipient canonical copy or reshare;
- recipient mutation of source facts;
- score/rank/recommendation; or
- automatic decision behavior.

The existing first-party P4 presentation and P1–P3 authorization/data-classification boundaries remain unchanged.

## 12. Protected validation evidence

Runtime candidate `b47f639a275652590304fccef051f78997a0153c` passed:

- Dependency Review `31570931190` — success;
- CodeQL `31570931290` — success;
- CI `31570931267` — success;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations — success;
- Pint — **559 files**;
- PHPStan/Larastan — **394/394, 0 errors**;
- ParaTest/PHPUnit — **451 tests, 10,230 assertions**;
- frontend dependency audit, ESLint, locked Prettier/Tailwind check, Vue TypeScript check and production build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

## 13. Gate decision

`K5-P5` / Slice E is **Complete** at runtime candidate `b47f639a275652590304fccef051f78997a0153c`.

`K5-P6` may be selected next for whole-increment acceptance, but P6 acceptance work is writable only after the exact containing evidence/status head that records P5 Complete / P6 Current independently passes Dependency Review, CodeQL and full CI/recovery.
