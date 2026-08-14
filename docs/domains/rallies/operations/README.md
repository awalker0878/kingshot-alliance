# Rallies operations profile

[← Rallies domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary operational boundary:** Player formations and Event-occurrence Rally plans partitioned by exact Rally Alliance and Event scope target

## 1. Operational purpose and runtime shape

Rallies is synchronous first-party coordination state backed by the application database and rendered in Event Show/Manage workspaces.

## 2. Persistent state and ownership

Persistent state is `player_formations`, `rally_guidance_rules`, `event_recommended_formations`, `rally_groups`, and `rally_assignments`.

## 3. Configuration and runtime dependencies

Dependencies are database persistence, Event/Alliance/Player records, authorization services, Player Context, audit and outbox services. No Rally-specific environment variable is required.

## 4. Normal flow and background processing

Players maintain their formations; managers maintain guidance/plans/groups/assignments and record participation. There is no Rally background executor.

## 5. Health, observability and diagnostics

Diagnose by Event/occurrence, operating Alliance, Rally group, Player, role/status, composition, actor evidence and outbox partition.

## 6. Failure modes and diagnosis

Expected failures include invalid composition, unauthorized target, Player ineligible for Rally Alliance, full joiner capacity, active lead/slot collision, conflicting assignment and missing active Player Context.

## 7. Recovery, replay and reconciliation

Re-read current Event/Alliance/Player state before retrying supported mutations. Do not fabricate assignment/participation evidence during recovery.

## 8. Backup, restore, migration and rollback

Rally state follows shared PostgreSQL backup/restore and application rollback procedures. Verify foreign keys, composition constraints and representative multi-Alliance Kingdom plans after restore.

## 9. Capacity, query and performance boundaries

Queries are occurrence/Alliance bounded. Assignment writes serialize on occurrence/group rows. Large Kingdom events should be monitored for candidate-list and assignment-query cost.

## 10. External-service degradation

No external game or messaging dependency exists in the Rally domain.

## 11. Safe operator actions and stop conditions

Safe actions are database/service restoration and supported first-party edits. Stop before direct state fabrication, ownership reassignment, authorization bypass or automated game commands.

## 12. Evidence, focused runbooks and related documentation

Retain release SHA, request/trace ID, Event/occurrence, Alliance, group, Player and actor identifiers. See [shared operations](../../../operations/README.md), [security](../security/README.md), and [testing](../testing/README.md).
