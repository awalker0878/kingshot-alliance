# Operations context

Status: Current  
Implementation: `app/Contexts/Operations`

Operations owns execution-time game coordination centered on Events and operational scheduling.

## Capabilities

- [Operations authorization](authorization.md)
- [Event core](event-core.md)
- [Participation](participation.md)
- [Planning: polls, rosters and battle plans](planning.md)
- [Rallies](rallies.md)
- [Results](results.md)
- [King Perks](king-perks.md)
- [Reminder policy](reminders.md)

## Boundary

Operations owns live operational state. Analytical projections belong to Intelligence or ReadModels. Reminder delivery state belongs to Communications. Platform may administer Event types but does not own Event execution semantics.
