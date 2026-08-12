# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P4 first-party shared-intelligence presentation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/intelligence/transfer, K4 ingestion operations, and K5 consent/grant/current/history plus first-party presentation with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain synchronous business workflows; K4 adds background ingestion/maintenance. K5 through P4 adds synchronous sharing consent/target mutations, bounded recipient current/history reads, member-safe presentation and a manager-only sharing workspace.

K5 still has no dedicated background job, scheduler entry, operator command or external provider dependency. P5 retention/operations/capacity hardening is selected but remains locked behind the P4 Complete / P5 Current transition gate.

## 2. Persistent state and ownership

K5 stores `kingdom_intelligence_shares` for directional consent and `kingdom_intelligence_share_targets` for explicit target grant history.

Pending invitations persist a one-way token hash; accept, decline and revoke erase it immediately. The current schema allows that hash to be null for consumed/terminal rows.

Source `KingdomAllianceObservation` rows remain source-owned and canonical. Current/history reads create no recipient observation-history copy. History continuation cursors and invitation plaintext are transient client/request state, not business records.

## 3. Configuration and runtime dependencies

K5 uses existing Laravel/PostgreSQL/auth/Audit/outbox dependencies. `config/kingdoms.php` defines a 72-hour invitation TTL, clamped to 1–168 hours.

Invitation token generation uses local cryptographic randomness and SHA-256 hashing. History continuation uses Laravel encrypted-string protection. K5 current/history/UI requires no external provider or credential.

## 4. Normal flow and background processing

Source manager creates invitation; recipient accepts/declines; source may revoke; active recipient may leave. Source managers may explicitly add/remove one active source tracking target under an active same-Kingdom agreement.

Recipient current facts use `SharedKingdomIntelligenceCurrentQuery`, bounded to 250 grants. Recipient history uses `SharedKingdomIntelligenceHistoryQuery`, capped at 50 rows/page and 250 accepted observations/traversal with an encrypted target-bound cursor.

P4 exposes these through the authenticated member-safe sharing page and manager-only management page. The history UI has no arbitrary `asOf` selector. Invitation plaintext appears only after creation in component memory and can be cleared.

Supported Alliance→Kingdom changes terminalize affected K5 agreements/pending source invitations in the same transaction, preventing access resume after returning to an old Kingdom.

There is no K5 background processing through P4. Existing K4 scheduler/queue behavior is unchanged.

## 5. Health, observability and diagnostics

Safe K5 diagnostics are share/target IDs, authorized source/recipient Alliance IDs, captured Kingdom, state and timestamps. Current/history payloads, cursors and invitation secret material are not operational log payloads.

Do not log invitation plaintext/hash material, encrypted history cursors, current/history observation payload bodies, source tracking IDs, manager notes, diplomacy/contact data, roster/transfer data or K4 provenance.

No K5 health command/dashboard exists yet.

## 6. Failure modes and diagnosis

Expected K5 failures include invalid/expired/used token, self-share, different-Kingdom activation, duplicate active agreement, stale/terminal agreement, inactive/different-Kingdom participant, non-source/inactive tracking, removed target, unrelated-tenant identifiers, and invalid/tampered/wrong-target history cursor.

Recipient current/history visibility disappears immediately on target removal, share revocation or context invalidation. A stale otherwise-valid history cursor cannot restore access.

A source invalidation may cause current facts to fall back to an older accepted observation and removes the invalidated row from subsequent history pages. This is canonical K3 behavior, not data loss.

## 7. Recovery, replay and reconciliation

Do not repair K5 by editing invitation hashes, source/recipient IDs, captured Kingdom, grant state, cursor state or observation payloads.

Revoked/declined agreements are terminal; future collaboration requires a new invitation/agreement. Removed targets require deliberate re-grant by the source manager.

Supported Kingdom drift persists terminal agreement state; returning to the previous Kingdom does not restore access. History cursors from an unauthorized/terminal share are unusable because every page repeats live authorization.

P4 adds no operator replay/reconciliation job. Source observation correction/invalidation remains K3-owned.

## 8. Backup, restore, migration and rollback

P4 adds the forward `030000` migration making `kingdom_intelligence_shares.invitation_token_hash` nullable so consumed/terminal secret-derived values can be erased without rewriting accepted P1 history.

Rollback fills null terminal hashes with deterministic per-share retired placeholders solely to satisfy the historical non-null schema; reapply recognizes those terminal placeholders and restores null. Pending invitation hashes remain intact. Focused down→up evidence preserves terminal state/recipient binding.

Clean PostgreSQL migrations, immutable image, staging and backup/restore all passed for P4 candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f`.

After restore, current/history authorization still depends on live agreement/grant/context state. Restored metadata does not bypass tenant/K3 authorization and no recipient canonical history copy exists.

## 9. Capacity, query and performance boundaries

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250. The focused 12-target current fixture proves no more than two SELECTs.

`SharedKingdomIntelligenceHistoryQuery` caps pages at 50 and one traversal at 250 accepted observations. The focused 260-observation fixture proves five 50-row pages, termination at exactly 250, and no more than two SELECTs per page using keyset pagination.

The manager workspace is bounded to 100 outbound agreements, 100 inbound agreements and 250 source-owned active trackable targets.

These are bounded implementation gates, not production throughput SLOs. Realistic-volume current/history capacity, bounded retention operations, diagnostics and any authorization-safe caching remain P5 work.

## 10. External-service degradation

K5 through P4 has no external service dependency. Existing K4 production adapter allowlist remains empty.

Do not use public links, external file sharing, ad hoc APIs or messaging callbacks as workarounds for K5 sharing. History cursors are first-party continuation state, not externally reusable sharing credentials.

## 11. Safe operator actions and stop conditions

Safe: inspect authorized consent/grant state and timestamps, standard Audit/outbox evidence, migrations/recovery, and advise source/recipient managers to use supported revoke/leave/remove/new-invitation workflows.

Stop if recovery would require exposing invitation secret material/history cursors, database retarget/reactivation, cross-tenant ID substitution, manual recipient observation copy, public link/feed creation, bypassing target grants, arbitrary historical-window reopening or widening to private data classes.

## 12. Evidence, focused runbooks and related documentation

**P4 inventory decision:** Kingdoms retains domain-owned focused operational guides. K5-P4 still does not justify a dedicated runbook because it adds no background/operator surface; shared deployment/backup mechanics remain top-level Operations-owned. P5 may add a focused retention runbook only if its selected operational work warrants one.

Accepted Kingdoms operational guides remain indexed and authoritative:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

P4 runtime candidate `9a095ae62e9b913ece6d619c3744574f0b91fd6f` passed Dependency Review `31569202741`, CodeQL `31569202422`, and CI `31569202418`: Pint 556 files, PHPStan 393/393 zero errors, 448 tests / 10,160 assertions, frontend lint/format/type/build, migrations, image/staging/backup/scan/cleanup success.

Use with [Shared intelligence](../shared-intelligence.md), [Slice D validation](../product/kingdoms-shared-intelligence-slice-d-validation.md), [Slice D security review](../security/kingdoms-shared-intelligence-presentation-security-review.md), [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and [rollback](../../../operations/runbooks/rollback.md).
