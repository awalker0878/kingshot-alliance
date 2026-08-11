# Rallies operations profile

[← Rallies domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary operational boundary:** Alliance-private rally guidance, formations, groups, assignments and participation state

## 1. Operational purpose and runtime shape

Rallies is synchronous Alliance-private coordination state. It has no accepted scheduler, queue worker, public API, bot or game-automation process.

## 2. Persistent state and ownership

Durable PostgreSQL state covers rallies/guidance, formations/groups, assignments and participation records owned by the active Alliance. Member identity/lifecycle is consumed from Memberships.

## 3. Configuration and runtime dependencies

Primary dependencies are PostgreSQL, active tenant context and Memberships/Authorization contracts. Rallies has no domain-specific external provider or runtime environment variable.

## 4. Normal flow and background processing

Authorized managers/members use supported request flows to create/update coordination state and participation. Assignments re-resolve members under the active Alliance. There is no background executor and no accepted action that sends commands to the game.

## 5. Health, observability and diagnostics

Inspect Rally/group/formation/assignment IDs, active member state, tenant ownership, timestamps and audit/request/trace correlation for mutations. Shared readiness covers database/cache runtime.

## 6. Failure modes and diagnosis

Typical issues are inactive/missing member assignment, cross-Alliance identifier use, stale/deleted coordination state, authorization denial, concurrent update conflict or PostgreSQL failure. Do not interpret missing game-side effects as a Rallies runtime failure because no game automation exists.

## 7. Recovery, replay and reconciliation

Restore PostgreSQL/tenant context and repeat supported request actions only after current state is re-read. Reconcile assignments against active Memberships state. Do not create synthetic participation or automate game actions to “catch up.”

## 8. Backup, restore, migration and rollback

Rallies state is PostgreSQL-backed and follows shared backup/restore and immutable-image rollback. After recovery verify representative rally/group/formation state, member assignments and tenant isolation. Schema reversal after real data requires explicit compatibility/data-loss review.

## 9. Capacity, query and performance boundaries

Rallies queries must remain tenant-bounded. Repository tests protect correctness/query behavior but do not establish production rally/member capacity or event-time traffic SLOs.

## 10. External-service degradation

There is no accepted external game or messaging dependency. Database/session dependency failures may block application coordination, but operators must not introduce unofficial bots/scrapers as a recovery path.

## 11. Safe operator actions and stop conditions

Safe actions are restore dependencies, verify active member/tenant state and use supported edits. Stop if recovery requires cross-tenant reassignment, fabricating participation, bypassing membership/permission rules, or introducing game automation.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, request/trace IDs, Rally/group/assignment IDs, affected membership IDs, before/after status and validation outcome. No focused P3 Rallies runbook is required. See [observability](../../../operations/observability.md), [backup/restore](../../../operations/runbooks/backup-restore.md), and the [Rallies security profile](../security/README.md).
