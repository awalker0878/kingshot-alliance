# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current — `KINGDOMS-004` and `KINGDOMS-005` Accepted  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/intelligence/transfer, K4 ingestion operations, and accepted K5 consent/grant/current/history/presentation plus bounded operational retention with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain synchronous business workflows; K4 adds background ingestion/maintenance. Accepted K5 adds synchronous sharing consent/target mutations, bounded recipient current/history reads, member-safe presentation, a manager-only sharing workspace, and bounded scheduled retention for old K5 operational metadata.

K5 retention is a repository-owned daily scheduler/Artisan surface. It has no external provider dependency, does not create recipient copies, and does not change live read authorization.

K5-P6 completed whole-increment acceptance without adding a new operational runtime surface.

## 2. Persistent state and ownership

K5 stores `kingdom_intelligence_shares` for directional consent and `kingdom_intelligence_share_targets` for explicit target grant history.

Pending invitations persist a one-way token hash; accept, decline and revoke erase it immediately. The current schema allows that hash to be null for consumed/terminal rows.

Source `TrackedKingdomAlliance` / `KingdomAllianceObservation` rows remain source-owned and canonical. Current/history reads create no recipient observation-history copy. History continuation cursors and invitation plaintext are transient client/request state, not business records.

P5 retention may delete only eligible old pending/terminal/removed K5 operational rows. Active shares/grants, canonical source tracking/observations, Audit events and outbox messages are not eligible.

## 3. Configuration and runtime dependencies

K5 uses existing Laravel/PostgreSQL/auth/Audit/outbox dependencies. `config/kingdoms.php` defines:

- `shared_intelligence.invitation_ttl_hours = 72`, repository-bounded to 1–168 hours;
- `shared_intelligence_retention.expired_invitation_days = 30`;
- `shared_intelligence_retention.terminal_share_days = 180`; and
- `shared_intelligence_retention.removed_target_days = 90`.

Invitation token generation uses local cryptographic randomness and SHA-256 hashing. History continuation uses Laravel encrypted-string protection. K5 current/history/UI/retention requires no external provider or credential.

Retention runtime clamps all configured day windows to at least one day and never allows the terminal-share window below the expired-invitation window.

## 4. Normal flow and background processing

Source manager creates invitation; recipient accepts/declines; source may revoke; active recipient may leave. Source managers may explicitly add/remove one active source tracking target under an active same-Kingdom agreement.

Recipient current facts use `SharedKingdomIntelligenceCurrentQuery`, bounded to 250 grants. Recipient history uses `SharedKingdomIntelligenceHistoryQuery`, capped at 50 rows/page and 250 accepted observations/traversal with an encrypted target-bound cursor.

P4 exposes these through the authenticated member-safe sharing page and manager-only management page. The history UI has no arbitrary `asOf` selector. Invitation plaintext appears only after creation in component memory and can be cleared.

P5 adds `kingdoms:enforce-sharing-retention --limit=500`, scheduled daily at 04:30 with `onOneServer()` and `withoutOverlapping(60)`. One invocation uses one total budget, clamped to 1–2000, and processes expired pending invitations → old terminal agreements → old removed grants.

Supported Alliance→Kingdom changes terminalize affected K5 agreements/pending source invitations in the same transaction, preventing access resume after returning to an old Kingdom.

P6 adds no new background or operator workflow; it validates the complete runtime seam end to end.

## 5. Health, observability and diagnostics

Safe K5 diagnostics are share/target IDs, authorized source/recipient Alliance IDs, captured Kingdom, state, timestamps, retention command limit, and returned per-class/total counts.

The retention command returns JSON keys `expiredInvitationsPurged`, `terminalSharesPurged`, `removedTargetsPurged`, and `processed`.

Do not log invitation plaintext/hash material, encrypted history cursors, current/history observation payload bodies, source tracking IDs, manager notes, diplomacy/contact data, roster/transfer data or K4 provenance.

No dedicated K5 health dashboard/alert exists. The command counts are operator evidence, not a production monitoring system by themselves.

## 6. Failure modes and diagnosis

Expected K5 access failures include invalid/expired/used token, self-share, different-Kingdom activation, duplicate active agreement, stale/terminal agreement, inactive/different-Kingdom participant, non-source/inactive tracking, removed target, unrelated-tenant identifiers, and invalid/tampered/wrong-target history cursor.

Recipient current/history visibility disappears immediately on target removal, share revocation or context invalidation. A stale otherwise-valid history cursor cannot restore access.

Retention-specific diagnosis:

- a lower-than-expected processed count may reflect the one-total-budget limit, configured window, or delete-time state/cutoff recheck preserving a row that changed state;
- a zero-result run is healthy when nothing is eligible;
- an eligible backlog may require repeated bounded runs; and
- any loss of an active share/grant or canonical observation during retention is a stop condition/data-integrity incident, not expected cleanup behavior.

## 7. Recovery, replay and reconciliation

Do not repair K5 by editing invitation hashes, source/recipient IDs, captured Kingdom, grant state, cursor state, observation payloads or retention timestamps.

Revoked/declined agreements are terminal; future collaboration requires a new invitation/agreement. Removed targets require deliberate re-grant by the source manager.

The retention command is safe to retry because eligibility is state/timestamp based and already-deleted rows no longer match. After database restore, old eligible operational rows may be reintroduced; after normal restore validation, rerun retention with a bounded limit as needed.

Supported Kingdom drift persists terminal agreement state; returning to the previous Kingdom does not restore access. History cursors from an unauthorized/terminal share are unusable because every page repeats live authorization.

Source observation correction/invalidation remains K3-owned.

## 8. Backup, restore, migration and rollback

P4 added the forward `030000` migration making `kingdom_intelligence_shares.invitation_token_hash` nullable so consumed/terminal secret-derived values can be erased without rewriting accepted P1 history.

Rollback fills null terminal hashes with deterministic per-share retired placeholders solely to satisfy the historical non-null schema; reapply recognizes those terminal placeholders and restores null. Pending invitation hashes remain intact.

P5 adds no schema migration. Whole-increment protected CI demonstrated clean migrations and backup/restore with the accepted K5 runtime present.

After restore, current/history authorization still depends on live agreement/grant/context state. Restored metadata does not bypass tenant/K3 authorization and no recipient canonical history copy exists. A restore may reintroduce already-purged operational rows; bounded retention may be rerun after restore validation.

## 9. Capacity, query and performance boundaries

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250. P5 realistic-volume evidence creates 300 active explicit grants and proves the current projection remains exactly 250 rows, uses no more than two SELECTs, stays within the reviewed 160,000-byte encoded fixture ceiling, and creates zero recipient canonical observations.

`SharedKingdomIntelligenceHistoryQuery` caps pages at 50 and one traversal at 250 accepted observations. P5 creates 1,000 source observations for one target and proves five 50-row pages, termination at exactly 250, no more than two SELECTs/page (10 across the five-page fixture), a reviewed 50,000-byte encoded page ceiling, and zero recipient canonical observations.

The manager workspace remains bounded to 100 outbound agreements, 100 inbound agreements and 250 source-owned active trackable targets.

Retention uses one total 1–2000 per-run work budget; scheduled default is 500. These are bounded implementation regression/capacity gates, not production throughput/latency SLOs.

## 10. External-service degradation

K5 has no external service dependency. Existing K4 production adapter allowlist remains empty.

Do not use public links, external file sharing, ad hoc APIs or messaging callbacks as workarounds for K5 sharing. History cursors are first-party continuation state, not externally reusable sharing credentials.

Database/runtime degradation during manual retention catch-up should be handled by lowering the supported `--limit`, not bypassing predicates or widening the 2000 cap.

## 11. Safe operator actions and stop conditions

Safe: inspect authorized consent/grant state and timestamps, configured retention windows, standard Audit/outbox evidence, migrations/recovery, retention JSON counts, and advise source/recipient managers to use supported revoke/leave/remove/new-invitation workflows.

Safe manual maintenance: `php artisan kingdoms:enforce-sharing-retention --limit=<1..2000>` after normal environment/database validation. Lower limits are preferred for conservative catch-up.

Stop if recovery/maintenance would require exposing invitation secret material/history cursors, database retarget/reactivation, cross-tenant ID substitution, deleting active shares/grants, deleting canonical observations/Audit/outbox, manual recipient observation copy, public link/feed creation, bypassing target grants, arbitrary historical-window reopening, disabling delete-time eligibility rechecks, or widening to private data classes.

## 12. Evidence, focused runbooks and related documentation

K5 warrants a dedicated capability runbook because it adds recurring destructive retention and capacity boundaries important to safe recovery:

- [Shared-intelligence retention and capacity operations](kingdoms-shared-intelligence-retention.md)

Accepted Kingdoms operational guides remain indexed and authoritative:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

Whole-increment runtime candidate `6f84b51ab27941f0fec2abce71f1f2f6325560e4` passed Dependency Review `31573301975`, CodeQL `31573301988`, and CI `31573301977`: Pint 560 files, PHPStan/Larastan 394/394 zero errors, 452 tests / 10,322 assertions, frontend lint/format/type/build, migrations, image/staging/backup/scan/cleanup success.

Use with [Shared intelligence](../shared-intelligence.md), [Slice E validation](../product/kingdoms-shared-intelligence-slice-e-validation.md), [Slice E security review](../security/kingdoms-shared-intelligence-retention-security-review.md), [K5 whole-increment exit report](../product/kingdoms-shared-intelligence-exit-report.md), [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and [rollback](../../../operations/runbooks/rollback.md).
