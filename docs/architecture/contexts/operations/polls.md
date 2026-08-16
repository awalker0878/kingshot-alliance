# Operations — Polls

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Polls`

Polls owns Event poll definitions, choices, voting state and poll lifecycle used for operational planning.

Polls may reference Operations Event/Player identifiers within the Operations consistency boundary but does not create a separate bounded context.