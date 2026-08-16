# Rallies

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Rallies`

Rallies owns Rally planning/execution associated with Event occurrences.

Rally is a capability inside Operations because its lifecycle and authority are part of execution-time Event coordination. It does not become a peer context simply because it has dedicated models/actions/routes.

Rally actors/assignments use Player identity and current Event scope authority. Historical Rally attribution should remain attached to the Event/Player identities that were true for the operation, not rewritten by later membership changes.