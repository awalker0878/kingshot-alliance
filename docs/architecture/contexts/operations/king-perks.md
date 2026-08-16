# King Perks capability

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/KingPerks`

King Perks is an Operations capability, not a peer bounded context. It coordinates Kingdom of Power appointment and King Skill planning against operational Event timing.

## Core rules

- appointment occupancy is 30 minutes;
- Player cooldown is 60 minutes and is anchored after the appointment end;
- position cancellation blocks that position for 30 minutes;
- the scheduler must validate occupancy/cooldown against the actual planned time rather than merely storing an arbitrary slot;
- source Event/phase timing remains Operations-owned;
- delivery/reminder attempts are Communications-owned, while reminder scheduling policy remains Operations-owned.

## Authority

King Perks uses Operations/Kingdom scope authority derived from the active Player and concrete Kingdom scope. Platform Administrator does not grant a game-domain bypass.

## Lifecycle concepts

Planning supports plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. Durable event/outbox publication should reflect completed persisted transitions rather than acting as a second source of truth.