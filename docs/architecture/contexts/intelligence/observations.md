# Intelligence — Observations

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Observations`

Observations owns durable observed game facts and their provenance.

Observed Player/Alliance/Kingdom facts reference stable identifiers but do not become a second writable source of GameWorld identity or Alliance membership.