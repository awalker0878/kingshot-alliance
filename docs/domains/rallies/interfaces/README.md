# Rallies interfaces

[← Rallies domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary boundary:** Alliance Rally guidance/formations/groups/assignments/participation exposed through first-party Event workspace adapters  
**P4 inventory decision:** Profile only

## 1. Boundary purpose and ownership

Rallies owns Rally-specific guidance, reusable/member formations, recommended Event formations, Rally groups, assignments, and recorded participation. Its current HTTP adapter surface is intentionally embedded in Event calendar/management controllers/routes because Rally coordination is presented in the Event workspace.

Physical controller placement does not move Rally semantic ownership into Events. Events owns occurrence/attendance facts; Rallies owns Rally coordination state and actions.

## 2. Surface inventory

Material first-party adapter routes in `routes/web.php` include:

- `POST /alliance/formations` — member saved formation;
- `POST /alliance/rally-guidance` — manager guidance rule;
- `POST /alliance/events/{occurrence}/formations` — recommended formation;
- `POST /alliance/events/{occurrence}/rally-groups` — Rally group;
- `PUT /alliance/rally-groups/{group}/assignments` — member assignment; and
- `PATCH /alliance/rally-assignments/{assignment}/participation` — participation result.

Event occurrence detail also serializes recommended formations, guidance, Rally groups/assignments, and the current member's saved formations for first-party presentation.

## 3. Callers, authorization and tenancy

Member saved-formation work requires authenticated, verified active-Alliance context. Manager Rally guidance/group/assignment/participation mutations are inside the Event privileged route group and require the applicable Event/Rally management authorization plus recent password confirmation.

All occurrence/group/assignment/membership references are resolved within the active Alliance. A coordinator or Rally assignment does not itself grant authorization.

## 4. Input and validation contracts

Member formation input validates name, up to five hero labels, troop percentages, optional notes, and default state; the owning `FormationComposition` contract enforces valid composition semantics.

Manager inputs validate guidance sources/effective dates, recommended formation content/order, group limits/notes, assignment membership/role/slot, and participation state through Rallies-owned actions/value objects.

An Event occurrence identifier is context for Rally coordination, not permission to mutate unrelated Event/Rally state.

## 5. Output and disclosure contracts

Event detail payloads may present member-safe recommended formations/guidance and Rally groups/assignments for the active Alliance, plus the caller's own saved formations. Manager-private edit/evidence state remains subject to owning permissions.

Rallies has no accepted external `/api/v1` route or dedicated public export schema. Integrations does not currently expose a Rally machine-read scope.

## 6. Internal actions, queries and services

Supported Rallies contracts include saved-formation actions/value objects, guidance-rule creation, recommended formation creation, group/assignment actions, and participation recording.

Events controllers call these supported actions as first-party adapters. Events remains owner of occurrence/registration/attendance persistence; Contributions may consume Event attendance rather than Rally participation unless an explicit contract says otherwise.

## 7. Events, outbox and cross-domain consumers

Material Rally mutations may record Audit/Platform-outbox evidence. Producer event meaning remains Rallies-owned.

Generic internal outbox publication does not create a public Rally webhook contract. Any future external Rally event would require explicit Integrations eligibility/payload documentation.

## 8. Commands, jobs and scheduled work

Rallies has no domain-specific CLI command, queue job, or scheduler workflow in the current runtime. Rally coordination is synchronous first-party request/action behavior.

Notifications/Event reminder jobs do not own or mutate Rally assignment/participation state merely because Rally data appears in an Event workspace.

## 9. Files, imports, exports and external dependencies

Rallies has no current import/export/media contract. It depends on Events occurrence context, Memberships, Authorization, PostgreSQL, and shared Audit/outbox infrastructure.

Operational diagnosis is documented in [Rallies operations](../operations/README.md).

## 10. Failure, idempotency, versioning and compatibility

Cross-tenant Event/group/assignment/member identifiers fail closed. Formation composition and group/assignment constraints are enforced by owning value objects/actions rather than presentation code.

The Event-workspace adapter route families are first-party compatibility contracts, but there is no accepted external Rally API version. Changes that move route ownership must preserve Rallies semantic/domain ownership and update Events/Rallies docs/tests together.

## 11. Explicit non-capabilities

Rallies does not:

- own Event schedules/occurrence/registration/attendance state;
- grant authorization through coordinator/assignment status;
- provide a public/external Rally API or export;
- run automated in-game Rally execution;
- scrape/import game state; or
- transfer Rally persistence ownership into Events merely because Event controllers adapt the HTTP surface.

## 12. Focused contracts, evidence and related documentation

No new focused P4 interface contract is required; the current Rally surface is one coherent first-party adapter/action boundary.

Related documentation:

- [Rallies domain](../README.md)
- [Rallies security](../security/README.md)
- [Rallies operations](../operations/README.md)
- [Events interfaces](../../events/interfaces/README.md)
- [Event registration and attendance](../../events/registration-and-attendance.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
