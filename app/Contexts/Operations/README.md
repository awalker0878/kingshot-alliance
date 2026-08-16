# Operations

Operations owns execution-time game coordination around Events.

## Capability modules

- `EventCore` owns Event identity, scope, schedule, recurrence, occurrences, phases, templates, capability selection and Event command endpoints.
- `Participation` owns registration, responses, attendance and frozen Player context.
- `Polls` owns Event polls and voting.
- `Rosters` owns Event roster planning, assignments and participation state.
- `BattlePlans` owns objectives and objective assignments.
- `Results` owns operational Event results, result metrics and metric capture.
- `Rallies` owns Rally planning and execution attached to Event occurrences.
- `KingPerks` owns Kingdom of Power appointment/skill planning and live scheduling.
- `Reminders` owns Event reminder rules and scheduling policy only.

## Boundaries

Event history, trend, intelligence and evidence projections are not Operations state; they are owned by Intelligence or ReadModels.

The Event management page is a cross-context projection and is owned by `ReadModels/EventManagement`. It may compose Operations facts with Intelligence. The Operations command controller does not import Intelligence, Communications, Platform or ReadModels.

EventCore relationships that expose operational capability state reference the owning capability models directly (`Participation`, `Polls`, `Rosters`, `BattlePlans`, and `Results`); capability models are never pretended to live under EventCore merely for ORM convenience.

Reminder delivery state is not owned by Operations. Delivery records live under Communications, while cross-context reminder inbox composition lives under ReadModels. Communications may reference the Operations reminder rule that caused a delivery, but Operations never navigates into delivery state.

Platform Event-type administration is Platform-owned orchestration over Operations configuration.

Alliance and Kingdom Event permission semantics are Operations-owned. Alliance and GameWorld expose current Player-scoped membership/governance facts and transaction-time scope locks; Operations decides what those facts authorize for `events.*`. GameWorld does not interpret Operations permission vocabulary.

All Alliance/Kingdom Event authority remains Player-scoped. User is account/platform identity only and never receives game-domain Event permissions directly.
