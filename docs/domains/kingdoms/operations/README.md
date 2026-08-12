# Kingdoms operations profile

[← Kingdoms domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P1 consent foundation validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary operational boundary:** Alliance-scoped roster/intelligence/transfer, K4 ingestion operations, and K5 consent metadata with shared deployment/recovery infrastructure

## 1. Operational purpose and runtime shape

K1–K3 remain synchronous business workflows; K4 adds background ingestion/maintenance. K5-P1 adds synchronous first-party sharing-consent mutations only.

P1 has no shared observation read surface, background job, scheduler entry, operator command or external service dependency.

## 2. Persistent state and ownership

K5-P1 adds `kingdom_intelligence_shares`: directional source/recipient/captured-Kingdom consent state plus hash-only invitation token identity and consent timestamps/actors.

It stores no selected game-Alliance target or observation payload/history. Existing K1–K4 data ownership is unchanged.

## 3. Configuration and runtime dependencies

K5 uses existing Laravel/PostgreSQL/auth/Audit/outbox dependencies. `config/kingdoms.php` defines a 72-hour invitation TTL, clamped by the creation action to 1–168 hours.

Invitation token generation uses local cryptographic randomness and SHA-256 hashing; no external dependency or credential service is introduced.

## 4. Normal flow and background processing

Source manager creates an invitation; recipient manager accepts/declines; source may revoke; active recipient may leave. All HTTP mutations are password-confirmed and actions enforce `kingdoms.manage`.

Acceptance is transactional and row-locked. There is no K5 background processing in P1. Existing K4 scheduler/queue behavior is unchanged.

## 5. Health, observability and diagnostics

Safe P1 diagnostic fields are share ID, authorized source/recipient Alliance IDs, captured Kingdom, state and consent/expiry/use timestamps.

Do not log invitation plaintext. Do not log future shared observation payloads, tracking notes, diplomacy/contact data, roster/transfer data or K4 provenance through K5 diagnostics.

No K5 health command/dashboard exists in P1.

## 6. Failure modes and diagnosis

Expected P1 failure modes include missing current source Kingdom, invalid/expired/used token, self-share, different-Kingdom acceptance, duplicate active directional agreement, insufficient permission/recent-password assurance and cross-tenant submitted share ID.

A different-Kingdom failed acceptance leaves the invitation unconsumed. Decline/revoke/leave remain valid access-reducing transitions after drift.

## 7. Recovery, replay and reconciliation

Do not repair sharing state by editing token hashes, recipient IDs, captured Kingdom, consent timestamps or state directly.

A revoked/declined agreement is terminal. Future collaboration requires a new invitation/agreement. Failed/expired invitation acceptance is not replayed by operators; the source creates a new invitation when appropriate.

No P1 reconciliation job exists. Same-Kingdom validity for later reads remains a P2+ authorization requirement.

## 8. Backup, restore, migration and rollback

The K5 consent migration is the newest Kingdoms dependency after K4 scheduling. Full Kingdom migration evidence now drops/reapplies it in the correct dependency order.

Clean PostgreSQL migrations, immutable image, staging and backup/restore all passed for P1 candidate `9ef1d46b1db69708d575e82d8548145cf7769e68`.

After restore, consent rows may exist in pending/active/terminal states, but P1 still contains no shared-data read path. Operators must not assume an active agreement means observation data is exposed.

## 9. Capacity, query and performance boundaries

P1 introduces no recipient shared-data query or new read-performance gate. Consent mutations are bounded single-agreement operations.

P2 must establish bounded current-fact projection behavior; P5 owns realistic-volume cross-tenant query/capacity gates and invitation-retention operations.

## 10. External-service degradation

K5-P1 has no external service dependency. Existing K4 production adapter allowlist remains empty.

Do not use public links, external file sharing, ad hoc APIs or messaging-service callbacks as workarounds for K5 consent/data sharing.

## 11. Safe operator actions and stop conditions

Safe: inspect consent state/timestamps and standard Audit/outbox evidence; verify migrations/recovery; advise source to revoke or issue a new invitation using supported first-party actions.

Stop if recovery would require exposing invitation plaintext, database retarget/reactivation, cross-tenant ID substitution, manual shared observation copy, public link/feed creation, or widening to private data classes.

## 12. Evidence, focused runbooks and related documentation

**P3 inventory decision:** Kingdoms retains domain-owned focused operational guides; K5-P1 does not yet justify a dedicated runbook because it adds no background/operator surface. Shared queue/deployment/backup mechanics remain top-level Operations-owned.

P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`: 541 Pint files, PHPStan 384/384 zero errors, 434 tests / 9,911 assertions, frontend/build, migrations, image/staging/backup/scan success.

Use with [Shared intelligence](../shared-intelligence.md), [Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md), [Slice A security review](../security/kingdoms-shared-intelligence-foundation-security-review.md), [background processing](../../../operations/background-processing.md), [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and [rollback](../../../operations/runbooks/rollback.md).