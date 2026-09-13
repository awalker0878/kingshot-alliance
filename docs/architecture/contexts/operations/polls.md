# Operations — Polls

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Polls`

Polls owns Event poll definitions, choices, voting state and poll lifecycle used for operational planning.

Polls may reference Operations Event/Player identifiers within the Operations consistency boundary but does not create a separate bounded context.

## Command bounds

The Poll owner validates at most 50 options and a raw list of 1–20 selected option ULIDs before normalization or locks. Current schema field lengths apply to direct owner calls. Option metadata is a flat map of at most 20 scalar values, with 64-byte keys and 500-byte strings. The only supported setting is a nullable deadline reminder of 1–10,080 whole minutes.

After voting starts, options, poll type and maximum choices are immutable; supported lifecycle edits such as closing remain available. Before voting, changing type requires replacement options validated under the new type. Current scope, voting windows and atomic audit/outbox persistence remain authoritative. See [ADR-0073](../../adr/0073-bounded-poll-owner-inputs.md).


## Read catalogues

Current-authorized member and manager views page independently through 25 phases and 25 polls, with exact totals and scoped finite continuations. Only displayed polls' options are loaded; SQL aggregates retained votes into those bounded choices. Open member results remain hidden and selected votes reflect only the current eligible Governor. Corrupt option cardinality rejects explicitly. See [ADR-0077](../../adr/0077-event-phase-poll-catalogues-and-vote-aggregates.md).
