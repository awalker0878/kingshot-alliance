# Operations — Events

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Events`

Events owns operational Event identity, scheduling, occurrences and Event lifecycle semantics.

## Boundary

Operations owns live Event execution. Platform `EventAdministration` owns system-wide Event-type administration but not Event execution. `ReadModels/EventAnalysis` composes Event facts with other owner data for history/analytics without mutating Events.

Event writes use capability Actions and current Operations authorization. `EventCore` is not a V3 capability name.

Bulk cancellation accepts at most 50 explicit Event IDs rather than a filter expression. Preview checks current lifecycle and management authority, while commit delegates each eligible item to `CancelEvent`, which reacquires target and authority facts inside its own transaction. Completed and already-cancelled Events remain explicit per-item outcomes; successful cancellation retains its normal audit and outbox evidence, complemented by one aggregate bulk receipt.
