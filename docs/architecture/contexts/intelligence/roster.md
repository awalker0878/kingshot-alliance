# Intelligence — Roster

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Roster`

Roster owns analytical/history projections about roster/player composition.

It is not the authoritative source for current Alliance membership; that belongs to `Alliance/Membership`. It is also distinct from `Operations/Rosters`, which owns Event roster planning.