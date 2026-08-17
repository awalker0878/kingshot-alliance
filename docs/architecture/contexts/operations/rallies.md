# Operations — Rallies

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Rallies`

Rallies owns Rally planning/execution associated with Event occurrences.

Rally is a capability inside Operations because its lifecycle and authority are part of execution-time Event coordination. It does not become a peer bounded context simply because it has dedicated models, Actions or routes.

Rally actors/assignments use Player identity and current Operations/Event scope authority. Historical Rally attribution remains attached to the Event/Player identities that were true for the operation and is not rewritten by later membership changes.