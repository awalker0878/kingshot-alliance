# Intelligence — Roster

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Roster`

Roster owns analytical/history projections about roster/player composition.

It is not the authoritative source for current Alliance membership; that belongs to `Alliance/Membership`. It is also distinct from `Operations/Rosters`, which owns Event roster planning.

## Observation boundary

Manual, controlled CSV, and approved ingestion sources append observations with capture time and human or machine provenance. They never overwrite history or claim to be current game state.

The Roster read model compares each observation only with the immediately preceding observation for the same roster identity. Power differences use decimal-safe arithmetic; observed name, free-text progression label, and Alliance-tag changes retain before/after values. A difference identifies an observation change, not the time an in-game change occurred.

Progression labels remain unnormalized until a verified, versioned game-level catalogue exists. See `/docs/reference/player-progression.md` for operator and trust semantics.
