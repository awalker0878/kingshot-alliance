# Operations — Rosters

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Rosters`

Rosters owns Event roster planning, assignments and participation-oriented roster state.

Roster planning references Player identity. It does not own authoritative Alliance membership, and it is distinct from `Intelligence/Roster`, which owns analytical/history projections.