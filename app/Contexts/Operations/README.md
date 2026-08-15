# Operations

Operations owns execution-time game coordination around Events.

## Capability modules

- `EventCore` owns Event identity, scope, schedule, recurrence, occurrences, phases, templates and capability selection.
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

Reminder delivery state is not owned by Operations. Delivery records live under Communications, while cross-context reminder inbox composition lives under ReadModels. Operations does not navigate to delivery records through ORM relationships.

Platform Event-type administration is Platform-owned orchestration over Operations configuration. Operations does not depend on Platform administration authority.

All Alliance/Kingdom Event authority remains Player-scoped. User is account/platform identity only and never receives game-domain Event permissions directly.
