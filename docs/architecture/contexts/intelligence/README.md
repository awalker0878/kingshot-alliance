# Intelligence context

Status: Current  
Implementation: `app/Contexts/Intelligence`

Intelligence owns observational and analytical state derived from game and Alliance activity. It does not duplicate neutral GameWorld identity as writable identity state.

## Capabilities

- [Intelligence authorization](authorization.md)
- [Observations and ingestion](observations-and-ingestion.md)
- [Roster and contributions](roster-and-contributions.md)
- [Event analysis](event-analysis.md)
- [Diplomacy and sharing](diplomacy-and-sharing.md)

## Boundary

Operations owns live Event execution. Intelligence may consume operational facts and produce durable analytical state, but must not mutate Operations aggregates or recreate Player identity ownership.
