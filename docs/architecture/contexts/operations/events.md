# Operations — Events

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Events`

Events owns operational Event identity, scheduling, occurrences and Event lifecycle semantics.

## Boundary

Operations owns live Event execution. Platform `EventAdministration` owns system-wide Event-type administration but not Event execution. `ReadModels/EventAnalysis` composes Event facts with other owner data for history/analytics without mutating Events.

Event writes use capability Actions and current Operations authorization. `EventCore` is not a V3 capability name.