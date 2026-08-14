# Rallies interface profile

[← Rallies domain](../README.md)

**Document type:** Living domain interface profile
**Status:** Current
**Owning domain:** Rallies
**Code owner:** `app/Domain/Rallies`
**Primary boundary:** Authenticated Player self-services and exact Event/Alliance Rally operations
**P4 inventory decision:** First-party HTTP, action/query and Event-workspace contracts are documented here

## 1. Boundary purpose and ownership

Rallies exposes Player formation self-service and Event-integrated Rally planning while keeping Rally state owned by the Rallies domain.

## 2. Surface inventory

Self surfaces: create/update/delete `/player/formations` and respond to `/events/{occurrence}/rally-assignments/{assignment}/response`.

Manager surfaces: Alliance guidance, Event recommendations, Rally groups, Player assignment/removal, and participation recording. Event Show/Manage consume `EventRallyQuery`.

## 3. Callers, authorization and tenancy

Self callers use authenticated Player Context. Manager callers are authorized against the Event's exact scope/target; Alliance guidance uses exact Alliance Event-manage permission. Rally Alliance validity is resolved server-side.

## 4. Input and validation contracts

Compositions must total 100%. Hero lists are limited to five. Group joiner capacity is positive when supplied. Roles are lead/joiner/standby. Self response accepts confirmed/declined only; participation accepts participated/absent only.

## 5. Output and disclosure contracts

Show exposes effective guidance/recommendations, group summaries and only the active Player's Rally assignments. Manage exposes Player names/assignments only to an authorized Event manager.

## 6. Internal actions, queries and services

Primary contracts include `SavePlayerFormation`, `SaveRallyGuidanceRule`, `SaveEventRecommendedFormation`, `SaveRallyGroup`, `AssignRallyPlayer`, `RespondRallyAssignment`, `RemoveRallyPlayer`, `RecordRallyParticipation`, `EventRallyQuery`, `RallyAllianceResolver`, and `RallyPlayerEligibility`.

## 7. Events, outbox and cross-domain consumers

Mutations produce audit/outbox evidence with Event target partitioning where an occurrence is involved. Results/Intelligence may consume Rally assignment/participation facts through explicit supported queries.

## 8. Commands, jobs and scheduled work

No Rally-specific command/job is required for current request-driven coordination.

## 9. Files, imports, exports and external dependencies

No Rally file import/export or external provider contract exists. PostgreSQL/SQLite test persistence and first-party Event UI are the runtime dependencies.

## 10. Failure, idempotency, versioning and compatibility

Wrong Event, Alliance, Player, group, recommendation or guidance identifiers fail closed. Assignment changes are transactionally serialized. Interface behavior is versioned with the application release.

## 11. Explicit non-capabilities

No public Rally write API, bot execution, game command dispatch, or implicit authorization from Player selection exists.

## 12. Focused contracts, evidence and related documentation

See [interface documentation standard](../../../product/interface-documentation-standard.md), [interface coverage matrix](../../../product/interface-coverage-matrix.md), [security](../security/README.md), [operations](../operations/README.md), and [testing](../testing/README.md).
