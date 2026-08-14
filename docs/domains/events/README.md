# Events domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract
**Status:** Current
**Code owner:** `app/Domain/Events`
**Primary authorization boundary:** exact Player, Alliance, or Kingdom target plus a permission for that scope

## 1. Purpose and ownership

Events owns KingShot Event scheduling and operational coordination across Player, Alliance, and Kingdom scopes. It owns the Event Type catalogue, supported-scope configuration, capability configuration, scheduling, participation facts, Event workspaces, and Event results.

Authorization owns permission grants. Kingdoms owns durable `Kingdom` and `Player` identity. Notifications owns reminder delivery state. Rallies owns Rally-specific operational persistence when enabled by an Event capability.

## 2. Scope

In scope:

- Event Type catalogue and per-scope configuration;
- Player, Alliance, and Kingdom Event scheduling;
- templates and recurring occurrences;
- responses, registration, waitlist, attendance, and reminders;
- phases and polls;
- rosters, substitutes, teams, and legions;
- Rally guidance and formations;
- battle objectives and assignments;
- results, scoring, and Event intelligence;
- agenda/calendar/detail/management UI; and
- authenticated Event exports and integration-facing read models.

Kingdom Transfer state remains owned by the Kingdoms transfer workflow. Events may present transfer milestones but does not own that workflow.

## 3. Domain model

Events separates five dimensions:

1. **Event Type** — what activity is taking place.
2. **Scope** — `player`, `alliance`, or `kingdom`.
3. **Target** — `Player`, `Alliance`, or `Kingdom`.
4. **Capabilities** — reusable operational modules enabled for a type/scope combination.
5. **Permission** — authority to view, create, or manage the exact target.

`Player` is the durable participant identity used for Event participation and history. Alliance membership is contextual and may change over time.

## 4. Core invariants

1. Scope and target type always agree.
2. An Event has exactly one target for its scope.
3. A permission for one Event scope never authorizes another scope.
4. Alliance rank or specialist roles never imply Kingdom Event authority.
5. Kingdom roles are bound to one exact Kingdom.
6. Player self-service uses authoritative `players.user_id` ownership and requires the target Player to equal the validated active Player context for self-scoped mutations.
7. R4/R5 Player management is evaluated through the target Player's current Alliance context; the manager's active Player never grants that authority.
8. Event capabilities determine which operational modules are available.
9. System Event Type slugs are stable and immutable.
10. New Events and templates snapshot their resolved schedule source, recurrence policy, minimum repeat interval, schedule fields, instructions, and settings so later catalogue edits do not rewrite existing schedules.
11. Participation facts remain distinct: response, registration, roster selection, confirmation, attendance, and result.

## 5. Lifecycles and workflows

A user first resolves an authorized creation context. The Event Type catalogue then limits available types to those active for that scope. Scheduling creates an Event and one or more occurrences. Capability configuration determines which workflows are available for each occurrence.

Typical operational flow:

```text
creation context
  → event type
  → schedule
  → occurrence
  → responses / registration
  → phases / polls
  → rosters / rallies / objectives
  → attendance
  → results / intelligence
```

## 6. Authorization and tenancy

Event permissions are:

```text
events.player.view
events.player.create
events.player.manage

events.alliance.view
events.alliance.create
events.alliance.manage

events.kingdom.view
events.kingdom.create
events.kingdom.manage

events.types.manage
```

`EventAuthorization` validates the requested scope, target model, and permission family before delegating to Player, Alliance, or Kingdom authorization.

`created_by_user_id` / `updated_by_user_id` record the authenticated account. `created_by_player_id` / `updated_by_player_id` record the validated active Player persona whenever the account owns Player identities; an account with multiple Players must select one before an Event mutation. Accounts with no Player may act with User-only authorship where authorization permits it. The target columns remain independent from authorship.

Frontend visibility is advisory. Backend authorization is authoritative for every read or mutation.

## 7. Cross-domain contracts

Consumes:

- **Authorization** — Alliance and Kingdom contextual grants;
- **Memberships** — active membership and R1–R5 rank;
- **Alliances** — Alliance target and time zone;
- **Kingdoms** — Kingdom and durable player identity;
- **Notifications** — reminder delivery orchestration;
- **Rallies** — Rally-specific operations;
- **Audit/Platform** — attributable evidence and platform administration.

Exposes Event Type metadata, occurrences, participation facts, operational assignments, and results to authorized consumers.

## 8. Persistence and data ownership

Events owns:

- `event_types`;
- `event_type_scopes`;
- `event_type_capabilities`;
- Event schedules, templates, and occurrences;
- responses, registrations, attendance, phases, polls, rosters, objectives, and results as their slices are implemented.

Notifications and Rallies retain persistence specific to their own domains.

## 9. Events, outbox and integrations

Material Event mutations create audit evidence and outbox events. Outbox partitioning follows the Event target context so Player, Alliance, and Kingdom operations can be processed without inventing a different owner.

Integrations owns externally published APIs/webhooks. Internal Event domain events are not automatically public integration contracts.

## 10. HTTP, UI and API surfaces

First-party Event surfaces include:

- Events agenda/calendar;
- Event detail/show;
- Event creation flow;
- Event management workspace;
- participation and registration actions;
- Event Type administration;
- authenticated exports.

The management workspace renders modules from the Event Type capabilities rather than a universal fixed form.

## 11. Background processing

Recurring occurrence materialization, reminder scheduling, notification delivery, and larger Event-derived processing use the shared scheduler/queue infrastructure. Jobs must carry exact target context and remain idempotent.

## 12. Failure, idempotency and concurrency

- scope/permission mismatch fails closed;
- target-model mismatch fails closed;
- stale player/membership linkage fails closed;
- registration/capacity mutations use transactional locking;
- roster assignment enforces occurrence/player uniqueness and capacity where configured;
- reminder delivery uses deterministic idempotency keys;
- repeated safe mutations remain idempotent where the workflow supports it.

## 13. Security and privacy

Event participation, assignments, instructions, and results are private operational data unless a specific surface is explicitly public. Player identity alone grants no permission. Kingdom identity alone grants no permission. Platform administration does not grant Kingdom Event authority unless an explicit Kingdom role is assigned.

See [Events security](security/README.md).

## 14. Observability and operations

Operational diagnostics should include Event Type, scope, target identifier, occurrence identifier, domain action, actor identifier, and outcome without logging private instructions or sensitive participation payloads.

See [Events operations](operations/README.md).

## 15. Testing and architecture enforcement

Tests protect:

- Event Type catalogue integrity;
- scope/target and scope/permission matching;
- R5/R4/Event Coordinator Alliance authority;
- exact-Kingdom authorization;
- Player self-service and manager authority;
- recurrence/time-zone handling;
- registration and roster concurrency;
- capability-driven module availability;
- tenant/scope isolation;
- accessibility and localization of Event surfaces;
- bounded-query behavior for calendar, attention, reminders, and Player intelligence.

## 16. Explicit non-capabilities

Events does not infer permissions from display names, game leadership labels, Kingdom identity, or Platform status. It does not duplicate the Kingdom Transfer state machine or own generic notification delivery infrastructure.

## 17. Capability documents

- [EVENTS-002 implementation plan](product/events-002-scoped-event-operations-implementation-plan.md)
- [Event registration and attendance](registration-and-attendance.md)
- [Event phases and polls](polls-and-phases.md)
- [Event rosters](rosters.md)
- [Event battle plans](battle-plans.md)
- [Event results and Player intelligence](results-and-intelligence.md)

- [Events security](security/README.md)
- [Events operations](operations/README.md)
- [Events interfaces](interfaces/README.md)
- [Events testing](testing/README.md)

## 18. Related documentation

- [Authorization](../authorization/README.md)
- [Kingdom-scoped roles](../authorization/kingdom-scoped-roles.md)
- [Memberships](../memberships/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Alliances](../alliances/README.md)
- [Rallies](../rallies/README.md)
- [Notifications](../notifications/README.md)
- [`app/Domain/Events/README.md`](../../../app/Domain/Events/README.md)
