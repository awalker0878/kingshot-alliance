# Rallies domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Rallies`  
**Primary authorization boundary:** `alliance.view` for member Rally guidance; `events.manage` for Rally coordination/mutation

## 1. Purpose and ownership

Rallies owns Alliance-scoped Rally guidance, member saved formations, Event-specific recommended formations, Rally groups, lead/joiner/standby assignments, and Rally participation records.

Rallies is coordinated around Events occurrences but does not own Event schedules, recurrence, registration/waitlist capacity, or Event attendance.

## 2. Scope

### In scope

- effective-dated Rally guidance;
- troop ratios, hero recommendations, lead/joiner guidance, source/rationale, and notes;
- member saved formations;
- Event-occurrence recommended formations;
- Rally groups and joiner-capacity configuration;
- lead/joiner assignments and standby behavior; and
- Rally participation (`participated`/`no-show`).

### Out of scope

- Event recurrence/scheduling;
- Event registration/waitlist/attendance;
- Notifications reminder-delivery state;
- hard-coded game advice in controllers/UI; and
- automatic in-game Rally execution.

## 3. Domain model

### Guidance

Guidance is configuration data rather than hard-coded game logic. A guidance record can contain:

- troop ratio;
- hero recommendations;
- lead requirements;
- joiner guidance;
- notes;
- effective-from date;
- optional effective-until date;
- source; and
- rationale.

### Member saved formation

A member can save a named formation with:

- optional hero names;
- infantry percentage;
- cavalry percentage;
- archer percentage;
- optional notes; and
- optional default status.

The three troop percentages must total exactly **100%**.

### Event recommended formation

For an Event occurrence, coordinators can create a named formation for a role such as lead or joiner. It may link to an effective-dated guidance rule and includes its own troop ratio/heroes/notes.

### Rally group and assignment

A Rally group belongs to an Event occurrence, may define maximum joiners, and may reference a recommended formation.

Assignments associate active Alliance members with lead/joiner roles and optional numbered slots. When configured joiner capacity is reached, additional joiners become `standby` rather than silently overbooking the group.

### Participation

Coordinators record Rally participation as `participated` or `no-show`, distinct from Events-owned attendance.

## 4. Core invariants

1. All Rally records are scoped to the active Alliance and applicable Event occurrence.
2. Guidance is configuration/evidence-driven, not embedded as controller/UI constants.
3. Effective dates/source/rationale should be preserved when recommendations change.
4. Saved/recommended formation troop percentages total exactly 100%.
5. Rally group capacity never silently overbooks joiners; excess assignments are standby.
6. Only active same-Alliance memberships may be assigned to Rally roles.
7. Rally participation remains distinct from Event attendance.
8. Rallies does not acquire Event scheduling/registration ownership merely because it references an occurrence.
9. A coordinator assignment is workflow responsibility, not a new authorization grant.

## 5. Lifecycles and workflows

### View Rally guidance

Event detail presents Rallies-owned:

- recommended formations;
- troop percentages and hero recommendations;
- guidance source/rationale and effective dates;
- Rally groups/current assignments; and
- the member's saved formations.

### Save member formation

From Event detail a member creates a named formation, optional heroes, 100%-total troop ratio, notes, and optional default status.

If a formation fails validation, infantry + cavalry + archer must be corrected to exactly 100%.

### Maintain effective-dated guidance

Authorized coordinators create/update guidance with explicit effective dates and source/rationale when recommendations change so members can understand why current advice is displayed.

### Configure Event recommended formation

A coordinator creates role-oriented recommended formations for an occurrence and may link them to effective-dated guidance.

### Create Rally group

Create one or more groups for an Event occurrence. A group may define maximum joiners and recommended formation.

### Assign Rally roles

Assign active Alliance members to lead/joiner roles and optional numbered slots. If joiner capacity is full, the assignment becomes standby.

### Record Rally participation

Coordinators record `participated` or `no-show` for Rally participation. This is a privileged mutation and remains separate from Events-owned Event attendance.

## 6. Authorization and tenancy

Member-safe Rally guidance/formation views require authenticated/verified active-Alliance context and `alliance.view` through the Event/member workspace.

Rally coordination/mutation requires `events.manage`; privileged mutations additionally require recent password confirmation.

Submitted occurrence/group/assignment/membership/formation identifiers are re-resolved beneath the active Alliance.

## 7. Cross-domain contracts

### Consumes

- **Events** — occurrence identity/context; Event registration/attendance remain Events-owned.
- **Alliances** — active tenant and time-zone/context.
- **Memberships** — active same-Alliance member identity for assignments/saved formations.
- **Authorization** — `alliance.view`/`events.manage`.
- **Audit/Platform** — privileged evidence/outbox foundation.

### Exposes

- Rally guidance and recommended formations shown by Event detail;
- Rally group/assignment coordination state; and
- Rally participation state for first-party reporting where an explicit supported contract exists.

## 8. Persistence and data ownership

Rallies owns guidance, saved formations, occurrence-specific recommended formations, Rally groups, Rally assignments, and Rally participation records.

Events owns Event/occurrence/registration/attendance persistence. Rallies references those identities rather than duplicating their lifecycle truth.

## 9. Events, outbox and integrations

Privileged Rally mutations are auditable/use the shared outbox where required. A Rally domain event does not automatically create a public external webhook contract.

No accepted public Rally write API or automated game execution contract exists.

## 10. HTTP, UI and API surfaces

Rally information is presented within the first-party Event detail/coordinator workflows. Members can view relevant guidance/saved formations; coordinators manage guidance/formations/groups/assignments/participation.

No standalone public Rally API is currently documented.

## 11. Background processing

Current Rally configuration/coordination is request-driven. Rallies does not own Event reminder scheduler commands and does not execute game actions in a background bot.

## 12. Failure, idempotency and concurrency

- Formation validation rejects ratios not totaling exactly 100%.
- Group capacity yields standby state rather than silent overbooking.
- Cross-Alliance or wrong-occurrence identifiers fail closed when re-resolved.
- Assignment requires an active same-Alliance membership.
- Privileged mutations require fresh authorization/password confirmation rather than trusting UI state.

## 13. Security and privacy

Rally pages, saved formations, groups, assignments, and participation are Alliance-scoped. Submitted IDs are re-resolved under active tenant context.

Game guidance/rationale should not contain unrelated private member data or secrets.

## 14. Observability and operations

Rally issues should be diagnosed through occurrence identity, active-Alliance/member scope, guidance effective dates, group capacity, assignment state, and participation state—not by direct database edits.

See [Events](../events/README.md), [Operations](../../operations/README.md), and [Security baseline](../../security/security-baseline.md).

## 15. Testing and architecture enforcement

Tests should protect:

- 100% formation-ratio validation;
- guidance effective-date/source behavior;
- active same-Alliance assignment scope;
- group capacity/standby behavior;
- participation state;
- cross-tenant isolation; and
- the architecture separation between Rallies ownership and Events scheduling/attendance ownership.

## 16. Explicit non-capabilities

Rallies does not:

- own Event recurrence/registration/attendance;
- send Event reminders;
- hard-code game guidance as controller/UI business logic; or
- execute automated in-game Rally actions.

## 17. Capability documents

No separate Rallies capability files are required at present. The root contract is the canonical owner for guidance, formations, groups, assignments, and participation.

## 18. Related documentation

- [Events domain](../events/README.md)
- [Notifications domain](../notifications/README.md)
- [Memberships domain](../memberships/README.md)
- [Authorization domain](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Rallies/README.md`](../../../app/Domain/Rallies/README.md)
