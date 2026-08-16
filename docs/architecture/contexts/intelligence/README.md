# Intelligence context

Status: Current  
Implementation: `app/Contexts/Intelligence`

Intelligence owns observational, historical and analytical state derived from game/alliance activity. It does not duplicate neutral GameWorld identity as writable identity state.

## Capabilities

- [Observations and ingestion](observations-and-ingestion.md)
- [Roster and contributions](roster-and-contributions.md)
- [Event analysis](event-analysis.md)
- [Diplomacy and sharing](diplomacy-and-sharing.md)

`Access` owns Intelligence-specific authorization semantics.

## Boundary

Operations owns live Event execution. Intelligence may consume operational facts and produce durable analytical/history state, but must not mutate Operations aggregates or recreate Player identity ownership.