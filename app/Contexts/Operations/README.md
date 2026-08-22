# Operations

Operations owns execution-time game coordination and operational planning.

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
- `TerritoryPlanning` owns mutable Alliance/Kingdom spatial plans, planned HQ/Banner/Governor-city/Bear-Trap objects, plan groups/preferences, deterministic layout analysis and immutable published revisions.

## Boundaries

`TerritoryPlanning` does not own Kingdom-map truth. It consumes immutable/versioned map facts and sourced placement rules from `GameWorld/KingdomMaps` by explicit identifiers/contracts. Planning preferences such as a target Bear radius remain Operations state and are never presented as official game rules.

`BattlePlans` remains Event objectives/assignments. It does not store territory objects in metadata. Applicable Events may reference an immutable published TerritoryPlanning revision through an explicit scalar reference/read composition.

Event history, trend, intelligence and evidence projections are not Operations state; they are owned by Intelligence or ReadModels.

The Event management page is a cross-context projection and is owned by `ReadModels/EventManagement`. Territory Command may similarly use `ReadModels/TerritoryPlanning` to compose map, Alliance, Player and plan reads. Owner-context adapters must not import ReadModels.

EventCore relationships that expose operational capability state reference the owning capability models directly (`Participation`, `Polls`, `Rosters`, `BattlePlans`, and `Results`); capability models are never pretended to live under EventCore merely for ORM convenience.

Reminder delivery state is not owned by Operations. Delivery records live under Communications, while cross-context reminder inbox composition lives under ReadModels. Communications may reference the Operations reminder rule that caused a delivery, but Operations never navigates into delivery state.

Platform Event-type administration is Platform-owned orchestration over Operations configuration.

Alliance and Kingdom Event/TerritoryPlanning permission semantics are Operations-owned. Alliance and GameWorld expose current Player-scoped membership/governance facts and transaction-time scope locks; Operations decides what those facts authorize for `events.*` and `territory.*`. GameWorld does not interpret Operations permission vocabulary.

All Alliance/Kingdom Operations authority remains Player-scoped. User is account/platform identity only and never receives game-domain permissions directly.
