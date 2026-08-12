# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P2 current-fact sharing validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/intelligence/transfer, K4 ingestion operations, and K5 consent/grant/current-fact state with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain synchronous business workflows; K4 adds background ingestion/maintenance. K5-P1/P2 adds synchronous first-party sharing consent/target mutations plus a bounded internal recipient current-fact query.

K5 still has no background job, scheduler entry, operator command, external provider dependency or bounded shared-history query.

## 2. Persistent state and ownership

K5 stores `kingdom_intelligence_shares` for directional consent and `kingdom_intelligence_share_targets` for explicit target grant history.

Source `KingdomAllianceObservation` rows remain source-owned and canonical. K5 stores no recipient observation-history copy.

## 3. Configuration and runtime dependencies

K5 uses existing Laravel/PostgreSQL/auth/Audit/outbox dependencies. `config/kingdoms.php` defines a 72-hour invitation TTL, clamped to 1–168 hours.

Invitation token generation uses local cryptographic randomness and SHA-256 hashing. P2 current projection requires no external provider or credential.

## 4. Normal flow and background processing

Source manager creates invitation; recipient accepts/declines; source may revoke; active recipient may leave. Source managers may explicitly add/remove one active source tracking target under an active same-Kingdom agreement.

Recipient current facts use the internal `SharedKingdomIntelligenceCurrentQuery`, bounded to 250 grants and latest accepted source observations only.

Supported Alliance→Kingdom changes terminalize affected K5 agreements/pending source invitations in the same transaction, preventing access resume after returning to an old Kingdom.

There is no K5 background processing in P2. Existing K4 scheduler/queue behavior is unchanged.

## 5. Health, observability and diagnostics

Safe K5 diagnostics are share/target IDs, authorized source/recipient Alliance IDs, captured Kingdom, state and timestamps. The current query exposes only safe factual display/current observation fields.

Do not log invitation plaintext, shared observation payload bodies, source tracking IDs, manager notes, diplomacy/contact data, roster/transfer data or K4 provenance through K5 diagnostics.

No K5 health command/dashboard exists yet.

## 6. Failure modes and diagnosis

Expected K5 failures include invalid/expired/used token, self-share, different-Kingdom activation, duplicate active agreement, stale/terminal agreement, inactive/different-Kingdom participant, non-source/inactive tracking, removed target and unrelated-tenant submitted identifiers.

Recipient current visibility is expected to disappear immediately on target removal, share revocation or context invalidation.

A source invalidation may cause the recipient current fact to fall back to the latest older accepted observation; this is canonical behavior, not data loss.

## 7. Recovery, replay and reconciliation

Do not repair K5 by editing token hashes, recipient/source IDs, captured Kingdom, grant state or observation payloads.

Revoked/declined agreements are terminal; future collaboration requires a new invitation/agreement. Removed targets require deliberate re-grant by the source manager.

Supported Kingdom drift persists terminal agreement state; returning to the previous Kingdom does not restore access.

P2 adds no operator replay/reconciliation job. Source observation correction/invalidation remains K3-owned.

## 8. Backup, restore, migration and rollback

K5 migrations now include consent plus explicit target-grant state. The target table is dropped before its parent share/tracking dependencies and reapplied afterward in both complete and focused Kingdoms rollback tests.

Clean PostgreSQL migrations, immutable image, staging and backup/restore all passed for P2 candidate `1a022e909cd246197510449a761a4856ce12b118`.

After restore, K5 authorization still depends on live agreement/grant/context state. Restored metadata does not bypass tenant/K3 authorization and no recipient canonical history copy exists.

## 9. Capacity, query and performance boundaries

`SharedKingdomIntelligenceCurrentQuery::CURRENT_LIMIT` is 250.

The focused 12-target fixture proves no more than two SELECTs for the current projection: one recipient/share/grant/context query plus one latest accepted observation query.

This is a bounded implementation gate, not a production throughput SLO. Realistic-volume current/history capacity, diagnostics and any authorization-safe caching remain P5 work.

## 10. External-service degradation

K5-P2 has no external service dependency. Existing K4 production adapter allowlist remains empty.

Do not use public links, external file sharing, ad hoc APIs or messaging callbacks as workarounds for K5 sharing.

## 11. Safe operator actions and stop conditions

Safe: inspect authorized consent/grant state and timestamps, standard Audit/outbox evidence, migrations/recovery, and advise source/recipient managers to use supported revoke/leave/remove/new-invitation workflows.

Stop if recovery would require exposing invitation plaintext, database retarget/reactivation, cross-tenant ID substitution, manual recipient observation copy, public link/feed creation, bypassing target grants or widening to private data classes.

## 12. Evidence, focused runbooks and related documentation

**P3 inventory decision:** Kingdoms retains domain-owned focused operational guides. K5-P2 still does not justify a dedicated runbook because it adds no background/operator surface; shared deployment/backup mechanics remain top-level Operations-owned.

Accepted Kingdoms operational guides remain indexed and authoritative:

- [Roster intelligence operations](kingdoms-roster-intelligence.md)
- [Transfer planning operations](kingdoms-transfer-planning.md)
- [Alliance intelligence operations](kingdoms-alliance-intelligence.md)
- [Automated ingestion operations](kingdoms-automated-ingestion.md)

P2 runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and CI `31562753430`: Pint 550 files, PHPStan 390/390 zero errors, 440 tests / 10,025 assertions, frontend/build, migrations, image/staging/backup/scan success.

Use with [Shared intelligence](../shared-intelligence.md), [Slice B validation](../product/kingdoms-shared-intelligence-slice-b-validation.md), [Slice B security review](../security/kingdoms-shared-intelligence-current-facts-security-review.md), [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and [rollback](../../../operations/runbooks/rollback.md).
