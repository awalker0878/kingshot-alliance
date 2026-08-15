# Events domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract
**Status:** Current
**Code owner:** `app/Domain/Events`
**Primary authorization boundary:** exact Player, Alliance, or Kingdom target plus a permission for that scope

## 1. Purpose and ownership

Events owns KingShot Event scheduling and operational coordination across Player, Alliance, and Kingdom scopes. It owns the Event Type catalogue, Event metric catalogue, supported-scope configuration, capability configuration, scheduling, participation facts, Event workspaces, results, Event metrics, and historical Event facts.

Authorization owns permission grants. Kingdoms owns durable `Kingdom` and `Player` identity. Notifications owns reminder delivery state. Rallies owns Rally-specific operational persistence when enabled by an Event capability. Contributions consumes Events facts for unified contribution/history reporting without becoming the owner of those facts.

## 2. Scope

In scope:

- Event Type catalogue and per-scope configuration;
- KingShot Event score/component metric catalogue;
- Player, Alliance, and Kingdom Event scheduling;
- templates and recurring occurrences;
- responses, registration, waitlist, attendance, and reminders;
- phases and polls;
- rosters, substitutes, teams, and legions;
- Rally guidance and formations;
- battle objectives and assignments;
- results, scoring, Event metrics, and Event intelligence;
- durable historical Event ownership and occurrence-time Player context;
- agenda/calendar/detail/management/history UI; and
- authenticated Event exports and integration-facing read models.

Kingdom Transfer state remains owned by the Kingdoms transfer workflow. Events may present transfer milestones but does not own that workflow.

## 3. Domain model

Events separates six dimensions:

1. **Event Type** — what activity is taking place.
2. **Scope** — `player`, `alliance`, or `kingdom`.
3. **Target** — the exact durable `Player`, `Alliance`, or `Kingdom` that owns the Event.
4. **Capabilities** — reusable operational modules enabled for a type/scope combination.
5. **Measurement profile** — first-class score semantics plus compatible Event/Alliance/Player component metric definitions.
6. **Permission** — current authority to view, create, or manage the exact target.

`Player` is the durable participant identity used for Event participation and personal history. Alliance membership and Kingdom placement are contextual and may change over time without rewriting historical Event ownership.

## 4. Core invariants

1. Scope and target type always agree.
2. An Event has exactly one target for its scope.
3. Event scope and target identity are immutable after Event creation.
4. Player history follows durable `player_id` across Alliance and Kingdom movement.
5. Alliance Event history follows the immutable Event `alliance_id`, not the current Alliance roster.
6. Kingdom Event history follows the immutable Event `kingdom_id`, not Players' current Kingdom placement.
7. Current authority determines who may view organization-wide history; historical membership/rank/context never grants authority.
8. A permission for one Event scope never authorizes another scope.
9. Alliance rank or specialist roles never imply Kingdom Event authority.
10. Kingdom roles are bound to one exact Kingdom.
11. Player self-service uses authoritative `players.user_id` ownership and requires the target Player to equal the validated active Player context for self-scoped mutations.
12. Event capabilities determine which operational modules are available.
13. System Event Type slugs are stable and immutable.
14. New Events and templates snapshot their resolved schedule source, recurrence policy, minimum repeat interval, schedule fields, instructions, and settings so later catalogue edits do not rewrite existing schedules.
15. Participation facts remain distinct: response, registration, roster selection, confirmation, attendance, result, and metric evidence.
16. Historical display/context snapshots are evidence only and never authorize access.
17. `score`, `rank`, and `outcome` remain first-class result fields; score semantics are defined by exact Event Type scope rather than duplicated as metric rows.
18. Component metric subjects are exactly `event | alliance | player`, and incompatible Event metrics are never silently combined into a universal score.

## 5. Lifecycles and workflows

A user first resolves an authorized creation context. The Event Type catalogue then limits available types to those active for that scope. Scheduling creates an Event and one or more occurrences. Capability configuration determines which workflows are available for each occurrence. The metric catalogue determines which first-class score semantics and normalized component definitions are valid for the exact Event Type/scope.

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
  → results / metrics / intelligence
  → historical Player / Alliance / Kingdom views
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

`EventAuthorization` validates the requested scope, target model, and permission family before delegating to Player, Alliance, or Kingdom authorization. Mutations re-establish current authority through the domain mutation boundary inside the transaction.

Game-domain Event authorship and result attribution use Player identity. Platform Administrator status remains User-scoped and does not grant game-domain Event authority or historical visibility.

Frontend visibility is advisory. Backend authorization is authoritative for every read or mutation.

## 7. Cross-domain contracts

Consumes:

- **Authorization** — Alliance and Kingdom contextual grants;
- **Memberships** — current Alliance membership and R1–R5 rank for current authority/eligibility only;
- **Alliances** — Alliance target, required `kingdom_id`, and time zone;
- **Kingdoms** — Kingdom and durable Player identity;
- **Notifications** — reminder delivery orchestration;
- **Rallies** — Rally-specific operations; and
- **Audit/Platform** — attributable evidence and platform infrastructure.

Exposes Event Type metadata, metric definitions/score semantics, occurrences, participation facts, operational assignments, results, metrics, and historical Event facts to authorized consumers. Contributions consumes those facts through supported read/query contracts.

## 8. Persistence and data ownership

Events owns:

- `event_types`;
- `event_type_scopes`, including first-class score semantics;
- `event_type_capabilities`;
- `event_metric_definitions`;
- Event schedules, templates, and occurrences;
- responses, registrations, attendance, phases, polls, rosters, objectives, and results;
- normalized Event/Alliance/Player metric values; and
- occurrence-time historical Player context required to preserve the meaning of past participation.

Notifications and Rallies retain persistence specific to their own domains. Contributions does not duplicate Events facts into a second canonical ledger merely for reporting.

## 9. Events, outbox and integrations

Material Event mutations create audit evidence and outbox events. Outbox partitioning follows the Event target context so Player, Alliance, and Kingdom operations can be processed without inventing a different owner.

Integrations owns externally published APIs/webhooks. Internal Event domain events are not automatically public integration contracts. Imported metric values, when implemented, remain subject to Events-owned definition/subject/dimension/target validation.

## 10. HTTP, UI and API surfaces

First-party Event surfaces include:

- Events agenda/calendar;
- Event detail/show;
- Event creation flow;
- Event management workspace;
- participation and registration actions;
- Player cross-scope Event/contribution history;
- Alliance historical Event intelligence;
- Kingdom historical Event intelligence;
- Event Type administration; and
- authenticated exports.

The management workspace renders modules from the Event Type capabilities rather than a universal fixed form. Result/history UI uses persisted Event Type-scope score semantics and compatible component metric definitions. Metric localization keys are defined in P2; user-facing translations are added with the result/history UI that consumes them rather than changing every locale before a metric surface exists.

## 11. Background processing

Recurring occurrence materialization, reminder scheduling, notification delivery, and larger Event-derived processing use the shared scheduler/queue infrastructure. Jobs must carry exact target context and remain idempotent.

Derived Event metric processing must use authoritative occurrence facts and the exact persisted metric definition rather than reconstructing meaning from labels or UI state.

## 12. Failure, idempotency and concurrency

- scope/permission mismatch fails closed;
- target-model mismatch fails closed;
- stale Player/membership/Kingdom authority fails closed;
- Event target mutation after creation fails closed;
- unknown/incompatible metric definitions or dimensions fail closed;
- registration/capacity mutations use transactional locking;
- roster assignment enforces occurrence/Player uniqueness and capacity where configured;
- reminder delivery uses deterministic idempotency keys;
- result/metric writes serialize on their natural Event/occurrence/result aggregates; and
- repeated safe mutations remain idempotent where the workflow supports it.

## 13. Security and privacy

Event participation, assignments, instructions, results, metrics, and historical intelligence are private operational data unless a specific surface is explicitly public. Player identity alone grants no permission. Kingdom identity alone grants no permission. Platform administration does not grant Alliance/Kingdom Event authority.

Historical organization queries authorize the current active Player against the immutable Event target. They never derive access from historical context snapshots or a Player's former membership/role.

See [Events security](security/README.md).

## 14. Observability and operations

Operational diagnostics should include Event Type, scope, target identifier, occurrence identifier, domain action, actor Player identifier, metric subject/key/dimension when applicable, provenance source, and outcome without logging private instructions or sensitive participation payloads.

See [Events operations](operations/README.md).

## 15. Testing and architecture enforcement

Tests protect:

- Event Type catalogue integrity;
- metric catalogue/score semantic integrity;
- scope/target and scope/permission matching;
- R5/R4/Event Coordinator Alliance authority;
- exact-Kingdom authorization;
- Player self-service and manager authority;
- recurrence/time-zone handling;
- registration and roster concurrency;
- capability-driven module availability;
- tenant/scope isolation;
- historical Player/Alliance/Kingdom ownership across membership and Kingdom changes;
- sibling Player history isolation;
- accessibility and localization of Event surfaces; and
- bounded-query behavior for calendar, attention, reminders, and Player intelligence.

## 16. Explicit non-capabilities

Events does not infer permissions from display names, historical affiliation, game leadership labels, Kingdom identity, or Platform status. It does not duplicate the Kingdom Transfer state machine, own generic notification delivery infrastructure, or generate an unexplained universal contribution score by adding incompatible Event metrics.

Custom Events do not automatically inherit arbitrary KingShot component metrics; custom metric definition is a separate future capability if required.

## 17. Capability documents

- [EVENTS-002 implementation plan](product/events-002-scoped-event-operations-implementation-plan.md)
- [Event registration and attendance](registration-and-attendance.md)
- [Event phases and polls](polls-and-phases.md)
- [Event rosters](rosters.md)
- [Event battle plans](battle-plans.md)
- [Event results and Player intelligence](results-and-intelligence.md)
- [Event contribution and historical intelligence](event-contribution-history.md)
- [KingShot Event metric catalogue](event-metric-catalogue.md)

- [Events security](security/README.md)
- [Events operations](operations/README.md)
- [Events interfaces](interfaces/README.md)
- [Events testing](testing/README.md)

## 18. Related documentation

- [ADR 0011 — Historical Event and contribution ownership](../../adr/0011-event-history-and-contribution-ownership.md)
- [Contributions](../contributions/README.md)
- [Authorization](../authorization/README.md)
- [Kingdom-scoped roles](../authorization/kingdom-scoped-roles.md)
- [Memberships](../memberships/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Alliances](../alliances/README.md)
- [Rallies](../rallies/README.md)
- [Notifications](../notifications/README.md)
- [`app/Domain/Events/README.md`](../../../app/Domain/Events/README.md)
